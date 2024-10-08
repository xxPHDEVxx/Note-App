<?php

// Inclusion des dépendances et des classes nécessaires
require_once "framework/Controller.php";
require_once "framework/View.php";
require_once "model/User.php";
require_once "framework/Tools.php";
require_once "model/ChecklistNoteItem.php";
require_once "model/NoteShare.php";
require_once "model/NoteLabel.php";
require_once "model/ChecklistNote.php";
require_once "model/Util.php";


// Définition de la classe ControllerNote, héritant de la classe Controller
class ControllerNote extends Controller
{
    // Méthode pour afficher la page principale des notes de l'utilisateur
    public function index(): void
    {
        // Récupération de l'utilisateur connecté
        $user = $this->get_user_or_redirect();

        // Récupération des notes épinglées et non épinglées de l'utilisateur
        $notes_pinned = $user->get_notes_pinned();
        $notes_unpinned = $user->get_notes_unpinned();

        // Affichage de la vue avec les données récupérées
        (new View("notes"))->show([
            "currentPage" => "my_notes",
            "notes_pinned" => $notes_pinned,
            "notes_unpinned" => $notes_unpinned,
            "user" => $user,
            "sharers" => $user->shared_by()
        ]);
    }

    // Méthode pour déplacer une note vers le haut
    public function move_up(): void
    {
        // Récupération de l'utilisateur connecté
        $user = $this->get_user_or_redirect();

        // Vérification de la présence de l'identifiant de la note à déplacer
        if (isset($_POST["up"]) && $_POST["up"] != "") {
            $id = $_POST["up"];
            $note = Note::get_note_by_id($id);
            if ($note === false)
                throw new Exception("undefined note");
            $other = $note->get_note_up($user, $id, $note->get_weight(), $note->isPinned());
            $note->move_db($other);
            $this->redirect("note", "index");
        } else {
            throw new Exception("Missing ID");
        }
    }

    // Méthode pour déplacer une note vers le bas
    public function move_down(): void
    {
        // Récupération de l'utilisateur connecté
        $user = $this->get_user_or_redirect();

        // Vérification de la présence de l'identifiant de la note à déplacer
        if (isset($_POST["down"]) && $_POST["down"] != "") {
            $id = $_POST["down"];
            $note = Note::get_note_by_id($id);
            if ($note === false)
                throw new Exception("undefined note");
            $other = $note->get_note_down($user, $id, $note->get_weight(), $note->isPinned());
            $other->move_db($note);
            var_dump($_POST["down"]);
            $this->redirect("note", "index");
        } else {
            throw new Exception("Missing ID");
        }
    }

    // Méthode pour gérer le partage des notes avec d'autres utilisateurs
    public function shares()
    {
        // Initialisation des variables
        $errors = [];
        $note = "";
        $notes_coded = "";
        $labels_checked_coded = "";
        $connected = $this->get_user_or_redirect();

        // Vérification de la présence de l'identifiant de la note à partager dans l'URL
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = filter_var($_GET['param1'], FILTER_VALIDATE_INT);
            if ($note_id === false) {
                $errors = "invalid note";
            } else {
                $note = Note::get_note_by_id($note_id);
            }

            // Vérification des autorisations de partage
            if ($note->owner != $connected->id) {
                $err = "pas la bonne personne connecté";
                Tools::abort($err);
            }

            // Récupération des utilisateurs partageant déjà la note et des autres utilisateurs
            $sharers = $note->get_shared_users();
            $others = [];
            $all_users = User::get_users();
            // Parcourir tous les utilisateurs
            foreach ($all_users as $us) {
                $is_shared = false;
                // Vérifier si l'utilisateur est déjà partagé avec la note
                foreach ($sharers as $shared_user) {
                    if ($shared_user[0] == $us->id) {
                        $is_shared = true;
                    }
                }
                // Si l'utilisateur n'est pas partagé et qu'il n'est pas celui connecté, l'ajouter à la liste
                if (!$is_shared && $us->id != $connected->id) {
                    $others[] = $us;
                }
            }

            // Vérification des données postées pour le partage de la note
            if (isset($_POST['user'], $_POST['editor']) && ($_POST["user"] == "null" || $_POST["editor"] == "null")) {
                $errors[] = "erreurs";
            }

            if (isset($_GET["param2"]) && isset($_GET["param3"])) {
                $notes_coded = $_GET["param2"];
                $labels_checked_coded = $_GET["param3"];
            }

            // Si les données postées sont valides, partager la note
            if (isset($_POST['user'], $_POST['editor']) && empty($errors)) {
                $nv_us = User::get_user_by_id($_POST['user']);
                $editor = ($_POST['editor'] == 1) ? true : false;
                ;
                $note_share = new NoteShare($note_id, $nv_us->id, $editor);
                $note_share->persist();
                $this->redirect("note", "shares", $note_id, $notes_coded, $labels_checked_coded);
            }
        }

        // Affichage de la vue de partage avec les données récupérées
        (new View("share"))->show([
            "sharers" => $sharers,
            "others" => $others,
            "note" => $note,
            "notes_coded" => $notes_coded,
            "labels_checked_coded" => $labels_checked_coded
        ]);
    }

    public function toggle_permission()
    {
        // Récupération de l'utilisateur connecté
        $this->get_user_or_redirect();

        // Vérification de la présence de l'identifiant de la note dans l'URL
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = Tools::sanitize($_GET["param1"]);

            // Exécution du formulaire de suppression et de bascule
            if (isset($_POST["action"])) {
                $action = $_POST["action"];
                // Exécuter les actions en fonction de la valeur soumise
                if ($action == "toggle") {
                    // Récupération de la note partagée existante et modification
                    $sharer = User::get_user_by_id($_POST['share']);
                    $edit = ($_POST['edit'] == 0) ? true : false;
                    $note_sh = NoteShare::get_share_note($note_id, $sharer->id);
                    $note_sh->editor = $edit;
                    $note_sh->persist();
                    $this->redirect("note", "shares", $note_id);
                } elseif ($action == "delete") {
                    // Récupération de la note partagée existante et suppression
                    $sharer = User::get_user_by_id($_POST['share']);
                    $note_sh = NoteShare::get_share_note($note_id, $sharer->id);
                    $note_sh->delete();
                    $this->redirect("note", "shares", $note_id);
                }
            }
        }
    }

    public function toggle_js()
    {
        // Récupération de l'utilisateur connecté
        $this->get_user_or_redirect();

        // Récupération de l'identifiant de la note et de l'utilisateur partageant la note
        $note_id = Tools::sanitize($_POST["note"]);
        $sharer = User::get_user_by_id($_POST['share']);
        // Conversion de la valeur d'édition en booléen
        $edit = ($_POST['edit'] == 0) ? true : false;
        // Récupération de la note partagée existante et mise à jour
        $note_sh = NoteShare::get_share_note($note_id, $sharer->id);
        $note_sh->editor = $edit;
        $note_sh->persist();
        $this->redirect("note", "shares", $note_id);
    }

    public function delete_js()
    {
        // Récupération de l'utilisateur connecté
        $this->get_user_or_redirect();
        // Récupération de l'identifiant de la note et de l'utilisateur partageant la note
        $note_id = Tools::sanitize($_POST["note"]);
        $sharer = User::get_user_by_id($_POST['share']);
        // Récupération de la note partagée existante et suppression
        $note_sh = NoteShare::get_share_note($note_id, $sharer->id);
        $note_sh->delete();
        $this->redirect("note", "shares", $note_id);
    }

    public function add_note(): void
    {
        // Affichage de la vue pour ajouter une note
        (new view("add_text_note"))->show();
    }


    function extractIdsFromString($string)
    {
        // Initialiser un tableau pour stocker les IDs extraits
        $ids = array();

        // Séparer la chaîne en éléments individuels en utilisant la virgule comme délimiteur
        $elements = explode(",", $string);

        // Parcourir chaque élément et extraire l'ID en supprimant le préfixe "note_"
        foreach ($elements as $element) {
            // Supprimer le préfixe "note_"
            $id = substr($element, strpos($element, "_") + 1);

            // Ajouter l'ID à notre tableau d'IDs
            $ids[] = $id;
        }

        // Retourner le tableau d'IDs extraits
        return $ids;
    }

    public function drag_and_drop()
    {
        // Vérifie si les données nécessaires sont présentes dans la requête POST
        if (
            isset(
            $_POST['moved'],
            $_POST['update'],
            $_POST['source'],
            $_POST['target'],
            $_POST['sourceItems'],
            $_POST['targetItems']
        )
        ) {
            // Récupère l'ID de la note déplacée
            $note_id = $_POST['moved'];

            // Récupère l'objet de la note à partir de l'ID
            $note = Note::get_note_by_id($note_id);

            // Extrait les IDs des éléments source et target à partir des chaînes JSON
            $source_ids = $this->extractIdsFromString($_POST['sourceItems']);
            $target_ids = $this->extractIdsFromString($_POST['targetItems']);

            // Détermine si la cible est "pinned" ou "unpinned"
            $target = $_POST['target'] == "pinned" ? 1 : 0;

            // Détermine si la source est "pinned" ou "unpinned"
            $source = $_POST['source'] == "pinned" ? 1 : 0;

            // Si la cible est différente de la source, effectue l'opération de "pin" ou "unpin"
            if ($target != $source) {
                // Si la cible est "pinned", épingle la note, sinon désépingle la note
                $target == 1 ? $note->pin() : $note->unpin();
                // Met à jour l'ordre des notes dans les listes source et target
                $note->new_order($source_ids);
                $note->new_order($target_ids);
            } else {
                // Si la cible est égale à la source, met simplement à jour l'ordre dans la source
                $note->new_order($source_ids);
            }
        }
    }



    public function add_checklist_note()
    {
        $user = $this->get_user_or_redirect();
        $errors = [];
        $duplicateErrors = [];
        $duplicateItems = [];

        // Initialisation du tableau pour les éléments non vides
        $non_empty_items = [];

        // Vérification du titre
        if (isset($_POST['title'])) {
            if ($_POST['title'] == "") {
                $errors['title'] = "Title required";
            } else {
                $title = Tools::sanitize($_POST['title']);
                $note = new ChecklistNote(
                    0,
                    $title,
                    $user->id,
                    date("Y-m-d H:i:s"),
                    false,
                    false,
                    $user->get_max_weight()
                );
                $titleErrors = $note->validate_title();
                if (!empty($titleErrors)) {
                    $errors['title'] = implode($titleErrors);
                }
            }
        }

        // Vérification des éléments
        if (isset($_POST['items'])) {
            $items = $_POST['items'];
            foreach ($items as $key => $item) {
                if (!empty($item)) {
                    //on crée une instance pour vérifier la longueur de l'item
                    $checklistItem = new ChecklistNoteItem(0, 0, $item, 0);
                    $contentErrors = $checklistItem->validate_item();
                    if (!empty($contentErrors)) {
                        $errors["item_$key"] = implode($contentErrors);
                    } else {
                        if (in_array($item, $duplicateItems)) {
                            $duplicateErrors["item_$key"] = "Items must be unique.";
                        } else {
                            $non_empty_items[$key] = $item;
                            $duplicateItems[] = $item;
                        }
                    }
                }
            }
        }

        // Combinaison des erreurs de doublons avec les autres erreurs
        $errors = array_merge($errors, $duplicateErrors);

        // Vérification finale et persistance
        if (empty($errors)) {
            if (isset($note)) {
                $note->persist();
                $note->new();

                foreach ($non_empty_items as $key => $content) {
                    $checklistNoteId = $note->note_id;
                    $checked = false;

                    $checklistItem = new ChecklistNoteItem(
                        0,
                        $checklistNoteId,
                        $content,
                        $checked
                    );

                    $checklistItem->persist();
                }

                $this->redirect("note", "open_note", $note->note_id);
            }
        }

        // Afficher la vue avec les erreurs
        (new View("add_checklist_note"))->show(["errors" => $errors]);
    }


    // Supprime une note
    public function delete_note()
    {
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = Tools::sanitize($_GET["param1"]);
            $note = Note::get_note_by_id($note_id);
            $user = $this->get_user_or_redirect();
            if ($user->id == $note->owner) {
                (new View('delete_confirmation'))->show(['note' => $note]);
            } else {
                throw new Exception("vous n'êtes pas l'auteur de cette note");
            }
        } else {
            throw new Exception("Missing ID");
        }
    }

    public function delete_confirmation()
    {
        // Vérification de la méthode de requête
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Vérification de la demande de suppression
            if (isset($_POST["delete"])) {
                // Vérification de la présence de l'identifiant de la note dans l'URL
                if (isset($_GET['param1'])) {
                    $note_id = Tools::sanitize($_GET["param1"]);
                    // Récupération de la note par son identifiant
                    $note = Note::get_note_by_id($note_id);
                    // Récupération de l'utilisateur connecté
                    $user = $this->get_user_or_redirect();
                    $user_id = $user->id;
                    // Vérification si l'utilisateur est le propriétaire de la note
                    if ($user_id == $note->owner) {
                        // Suppression de la note
                        $note->delete($user);
                        // Redirection vers les archives de l'utilisateur
                        $this->redirect("user", "my_archives");
                    } else {
                        throw new Exception("Vous n'êtes pas autorisé à supprimer cette note.");
                    }
                } else {
                    throw new Exception("Identifiant de la note manquant");
                }
            } else {
                // Si l'utilisateur annule la suppression, redirige vers la page de la note
                if (isset($_GET['param1'])) {
                    $note_id = $_GET['param1'];
                    $this->redirect("note", "open_note", $note_id);
                } else {
                    throw new Exception("Identifiant de la note manquant");
                }
            }
        }
    }

    public function edit_checklist()
    {
        $user = $this->get_user_or_redirect();
        $errors = [];
        $errorsItem = [];
        $notes_coded = "";
        $labels_checked_coded = "";

        // paramètres pour navigation search
        if (isset($_GET["param2"]) && isset($_GET["param3"])) {
            $notes_coded = $_GET["param2"];
            $labels_checked_coded = $_GET["param3"];
        }

        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = Tools::sanitize($_GET["param1"]);
            // Récupération de la note par son identifiant
            $note = CheckListNote::get_note_by_id($note_id);
            // Vérification si la note existe
            if ($note === false) {
                throw new Exception("Undefined note");
            }
            $user_id = $user->id;
            $archived = $note->in_my_archives($user_id);
            // Vérification si la note est épinglée par l'utilisateur
            $pinned = $note->is_pinned($user_id);
            // Vérification si la note est partagée comme éditeur par l'utilisateur
            $is_shared_as_editor = $note->is_shared_as_editor($user_id);
            // Vérification si la note est partagée comme lecteur par l'utilisateur
            $is_shared_as_reader = $note->is_shared_as_reader($user_id);
            // Récupération du contenu de la note
            $body = $note->get_content();

            // if (isset($_GET["param2"]) && isset($_GET["param3"])) {
            //     $notes_coded = $_GET["param2"];
            //     $labels_checked_coded = $_GET["param3"];
            // }



            //verification si champ titre vide + si c'est le titre initial
            if (isset($_POST['title']) && $_POST['title'] == "") {
                $errors["title"] = "Title required";
            }

            if (isset($_POST['title']) && $_POST['title'] != $note->title) {
                $title = Tools::sanitize($_POST["title"]);
                $note = Note::get_note_by_id($note_id);
                $note->title = $title;
                $note->persist();
                $errors["title"] = implode($note->validate_title());
            }

            // Suppression d'un élément de la liste de contrôle
            if (isset($_POST['delete']) && $_POST['delete']) {
                $item_id = $_POST["delete"];
                $item = CheckListNoteItem::get_item_by_id($item_id);
                if ($item === false) {
                    throw new Exception("Undefined checklist item");
                }
                $item->delete();
                $note = Note::get_note_by_id($item->checklist_note);
                $date = new DateTime();
                $note->edited_at = $date->format('Y-m-d H:i:s');
                $note->persist();
                // mise à jour notes après modification pour navigation search
                if ($labels_checked_coded != "")
                    $notes_coded = Util::url_safe_encode($user->get_notes_search(Util::url_safe_decode($labels_checked_coded)));
                $this->redirect("note", "edit_checklist", $note_id, $notes_coded, $labels_checked_coded);
            }

            // Ajout d'un nouvel élément à la liste de contrôle
            if (isset($_POST['new']) && $_POST["new"] != "") {
                $new_item_content = Tools::sanitize($_POST['new']);
                $new_item = new CheckListNoteItem(0, $note->note_id, $new_item_content, false);

                if (!$new_item->is_unique()) {
                    $errors["items"] = "item must be unique";
                }
                $contentErrors = $new_item->validate_item();
                if (!empty($contentErrors)) {
                    $errors["items"] = implode($contentErrors);
                }

                //si item oke -> modif db
                if (empty($errors['items'])) {
                    $new_item->persist();
                    // mise à jour notes après modification pour navigation search
                    if ($labels_checked_coded != "")
                        $notes_coded = Util::url_safe_encode($user->get_notes_search(Util::url_safe_decode($labels_checked_coded)));
                    $this->redirect("note", "edit_checklist", $note_id, $notes_coded, $labels_checked_coded);
                    exit;
                }
            }
        }
        if (isset($_POST["save"])) {

            //action edit item 
            // Vérification des éléments
            if (isset($_POST['items'])) {
                foreach ($_POST['items'] as $key => $item) {
                    $checklistItem = ChecklistNoteItem::get_item_by_id($key);
                    $checklistItem->content = $item;
                    if (!$checklistItem->is_unique()) {
                        $errorsItem["item_$key"] = "item must be unique";
                    } else {
                        $contentErrors = $checklistItem->validate_item();
                        if (!empty($contentErrors)) {
                            $errorsItem["item_$key"] = implode("; ", $contentErrors);
                        }
                        if (empty($errorsItem["item_$key"])) {
                            $checklistItem->persist();
                        }
                    }
                }
                $note = Note::get_note_by_id($checklistItem->checklist_note);
                $date = new DateTime();
                $note->edited_at = $date->format('Y-m-d H:i:s');
                $note->persist();
            }
            $errors = array_merge($errors, $errorsItem);
            if (empty($errors["title"]) && empty($errorsItem)) {
                $note->persist();
                // mise à jour notes après modification pour navigation search
                if ($labels_checked_coded != "")
                    $notes_coded = Util::url_safe_encode($user->get_notes_search(Util::url_safe_decode($labels_checked_coded)));
                $this->redirect("note", "open_note", $note->note_id, $notes_coded, $labels_checked_coded);
            }
        }
        // Affichage de la vue d'édition de la liste de contrôle
        (new View("edit_checklist_note"))->show([
            "note" => $note,
            "note_id" => $note_id,
            "created" => $this->get_created_time($note_id),
            "edited" => $this->get_edited_time($note_id),
            "archived" => $archived,
            "is_shared_as_editor" => $is_shared_as_editor,
            "is_shared_as_reader" => $is_shared_as_reader,
            "content" => $body,
            "pinned" => $pinned,
            "user_id" => $user_id,
            "errors" => $errors,
            "notes_coded" => $notes_coded,
            "labels_checked_coded" => $labels_checked_coded
        ]);
    }


    public function save_edit_text_note()
    {
        // Récupération de l'utilisateur connecté
        $user = $this->get_user_or_redirect();
        // Initialisation des tableaux d'erreurs
        $content_errors = [];
        $title_errors = [];
        $errors = [];
        $notes_coded = "";
        $labels_checked_coded = "";

        // Vérification si le titre est vide
        if (isset($_POST['title']) && $_POST['title'] == "") {
            array_push($title_errors, "Title required");
        }

        // Vérification de la présence des données nécessaires
        if (isset($_GET['param1'], $_POST['title'], $_POST['content'])) {
            $note_id = (int) $_GET['param1'];
            // Vérification de la validité de l'identifiant de la note
            if ($note_id > 0) {
                // Récupération de la note par son identifiant
                $note = TextNote::get_note_by_id($note_id);

                // Vérification si la note existe et si l'utilisateur est le propriétaire
                if ($note && $note->owner == $user->id) {
                    // Mise à jour du titre et du contenu de la note
                    $note->title = Tools::sanitize($_POST['title']);
                    $note->set_content(Tools::sanitize($_POST['content']));

                    // Validation du titre et du contenu de la note
                    if ($note->validate_title() != null)
                        array_push($title_errors, $note->validate_title()[0]);
                    $content_errors = $note->validate_content();

                    if (isset($_GET["param2"]) && isset($_GET["param3"])) {
                        $notes_coded = $_GET["param2"];
                        $labels_checked_coded = $_GET["param3"];
                    }

                    // Si des erreurs sont présentes, affichage de la vue avec les erreurs
                    if (!empty($content_errors) || !empty($title_errors)) {
                        (new View("edit_text_note"))->show([
                            "note" => $note,
                            "note_id" => $note_id,
                            "created" => $this->get_created_time($note_id),
                            "edited" => $this->get_edited_time($note_id),
                            "content" => $note->get_content(),
                            "title" => $note->title,
                            'errors' => $errors,
                            'content_errors' => $content_errors,
                            'title_errors' => $title_errors,
                            "notes_coded" => $notes_coded,
                            "labels_checked_coded" => $labels_checked_coded
                        ]);
                        exit();
                    }

                    // màj date d'édition
                    $date = new DateTime();
                    $note->edited_at = $date->format('Y-m-d H:i:s');
                    // Si tout est valide, mise à jour de la note et redirection vers la page de la note
                    $note->update();
                    // mise à jour notes après modification pour navigation search
                    if ($labels_checked_coded != "")
                        $notes_coded = Util::url_safe_encode($user->get_notes_search(Util::url_safe_decode($labels_checked_coded)));
                    $this->redirect("note", "open_note", $note_id, $notes_coded, $labels_checked_coded);
                } else {
                    $errors = "Note introuvable ou vous n'avez pas la permission de la modifier.";
                }
            } else {
                $errors = "ID de note invalide.";
            }
        } else {
            $errors = "Les informations requises sont manquantes.";
        }
    }

    public function save_add_text_note()
    {
        // Récupération de l'utilisateur connecté
        $user = $this->get_user_or_redirect();
        // Initialisation des tableaux d'erreurs
        $content_errors = [];
        $title_errors = [];
        $errors = [];
        $title = "";
        $content = "";

        // Vérification si le titre est vide
        if (isset($_POST['title']) && $_POST['title'] == "") {
            array_push($title_errors, "Title required");
        }

        // Vérification de la méthode de requête
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Vérification de la présence des données nécessaires
            if (isset($_POST['title'], $_POST['content'])) {
                // Récupération et nettoyage du titre et du contenu de la note
                $title = Tools::sanitize($_POST['title']);
                $content = Tools::sanitize($_POST['content']);

                // Création d'une nouvelle note de type texte
                $note = new TextNote(
                    0,
                    $title,
                    $user->id,
                    date("Y-m-d H:i:s"),
                    false,
                    false,
                    $user->get_max_weight()
                );
                $note->set_content($content);

                // Validation du contenu de la note
                $content_errors = $note->validate_content();
                if ($note->validate_title() != null)
                    array_push($title_errors, $note->validate_title()[0]);

                // Si les données sont valides, enregistrement de la note et redirection
                if (empty($title_errors) && empty($content_errors)) {
                    $result = $note->persist();
                    if ($result instanceof TextNote) {
                        $note->update();
                        $this->redirect("note", "open_note", $result->note_id);
                        exit();
                    } else {
                        $errors[] = "Erreur lors de la sauvegarde de la note.";
                    }
                }
            } else {
                $errors[] = "Les informations requises pour le titre ou le contenu sont manquantes.";
            }
        }

        // Affichage de la vue d'ajout de note de texte
        (new View("add_text_note"))->show([
            'note' => $note,
            'user' => $user,
            'errors' => $errors,
            'title' => $title,
            'content' => $content,
            'content_errors' => $content_errors,
            'title_errors' => $title_errors
        ]);
    }






    public function open_note()
    {
        $notes_coded = "";
        $labels_checked_coded = "";
        // Récupération de l'utilisateur connecté
        $this->get_user_or_redirect();
        // Vérification de la présence et de la validité de l'identifiant de la note
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupération de la note par son identifiant
            $note = Note::get_note_by_id($note_id);
            $user_id = $this->get_user_or_redirect()->id;
            // Vérification des états de la note
            $archived = $note->in_my_archives($user_id);
            $pinned = $note->is_pinned($user_id);
            $is_shared_as_editor = $note->is_shared_as_editor($user_id);
            $is_shared_as_reader = $note->is_shared_as_reader($user_id);
            $body = $note->get_content();
            if (isset($_GET["param2"]) && isset($_GET["param3"])) {
                $notes_coded = $_GET["param2"];
                $labels_checked_coded = $_GET["param3"];
            }
        }
        // Affichage de la vue appropriée en fonction du type de note (texte ou checklist)
        ($note->get_type() == "TextNote" ? new View("open_text_note") : new View("open_checklist_note"))->show([
            "note" => $note,
            "note_id" => $note_id,
            "created" => $this->get_created_time($note_id),
            "edited" => $this->get_edited_time($note_id),
            "archived" => $archived,
            "is_shared_as_editor" => $is_shared_as_editor,
            "is_shared_as_reader" => $is_shared_as_reader,
            "note_body" => $body,
            "pinned" => $pinned,
            "user_id" => $user_id,
            "notes_coded" => $notes_coded,
            "labels_checked_coded" => $labels_checked_coded
        ]);
    }

    // Méthode pour obtenir le temps d'édition d'une note
    public function get_edited_time(int $note_id): string|bool
    {
        $note = Note::get_note_by_id($note_id);
        $edited_date = $note->get_edited_at();
        return $edited_date != null ? $this->get_elapsed_time($edited_date) : false;
    }

    // Méthode pour obtenir le temps de création d'une note
    public function get_created_time(int $note_id): string
    {
        $note = Note::get_note_by_id($note_id);
        $created_date = $note->get_created_at();
        return $this->get_elapsed_time($created_date);
    }

    // Méthode pour obtenir le temps écoulé depuis une date donnée
    public function get_elapsed_time(string $date): string
    {
        $localDateNow = new DateTime();
        $dateTime = new DateTime($date);
        $diff = $localDateNow->diff($dateTime);
        $res = '';
        // Calcul du temps écoulé
        if ($diff->y == 0 && $diff->m == 0 && $diff->d == 0 && $diff->h == 0 && $diff->i == 0) {
            $res = $diff->s . " secondes ago.";
        } elseif ($diff->y == 0 && $diff->m == 0 && $diff->d == 0 && $diff->h == 0 && $diff->i != 0) {
            $res = $diff->i . " minutes ago.";
        } elseif ($diff->y == 0 && $diff->m == 0 && $diff->d == 0 && $diff->h != 0) {
            $res = $diff->h . " hours ago.";
        } elseif ($diff->y == 0 && $diff->m == 0 && $diff->d != 0) {
            $res = $diff->d . " days ago.";
        } elseif ($diff->y == 0 && $diff->m != 0) {
            $res = $diff->m . " month ago.";
        } else if ($diff->y != 0) {
            $res = $diff->y . " years ago.";
        }
        return $res;
    }

    // Méthode pour mettre à jour l'état de vérification d'un élément de liste de contrôle
    public function update_checked(): void
    {
        $this->get_user_or_redirect();
        // Vérification des données postées
        if (isset($_POST["check"])) {
            $checklist_item_id = $_POST["check"];
            $note_id = CheckListNoteItem::get_checklist_note($checklist_item_id);
            $checked = true;
            CheckListNoteItem::update_checked($checklist_item_id, $checked);
        } elseif (isset($_POST["uncheck"])) {
            $checklist_item_id = $_POST["uncheck"];
            $note_id = CheckListNoteItem::get_checklist_note($checklist_item_id);
            $checked = false;
            CheckListNoteItem::update_checked($checklist_item_id, $checked);
        }
        $this->redirect("note", "open_note/$note_id");
    }

    // Méthodes pour épingler, dépingler, archiver et désarchiver une note
    public function pin(): void
    {
        // Récupération de l'utilisateur connecté
        $this->get_user_or_redirect();
        // Vérification de la présence et de la validité de l'identifiant de la note
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupération de la note par son identifiant et épinglage
            $note = Note::get_note_by_id($note_id);
            $note->pin();
            $this->redirect("note", "open_note", $note_id);
        }
    }

    public function unpin(): void
    {
        // Récupération de l'utilisateur connecté
        $this->get_user_or_redirect();
        // Vérification de la présence et de la validité de l'identifiant de la note
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupération de la note par son identifiant et dépinglage
            $note = Note::get_note_by_id($note_id);
            $note->unpin();
            $this->redirect("note", "open_note", $note_id);
        }
    }
    public function archive(): void
    {
        // Vérifie si l'utilisateur est connecté, sinon le redirige
        $this->get_user_or_redirect();
        // Vérifie si l'identifiant de la note est présent et valide dans l'URL
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupère la note par son identifiant
            $note = Note::get_note_by_id($note_id);
            // Archive la note et la désépingle
            echo $note->archive();
            $note->archive();
            $note->unpin();
            // Redirige vers la page d'affichage de la note
            $this->redirect("note", "open_note", $note_id);
        }
    }

    public function unarchive(): void
    {
        // Vérifie si l'utilisateur est connecté, sinon le redirige
        $this->get_user_or_redirect();
        // Vérifie si l'identifiant de la note est présent et valide dans l'URL
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupère la note par son identifiant et la désarchive
            $note = Note::get_note_by_id($note_id);
            $note->unarchive();
            // Redirige vers la page d'affichage de la note
            $this->redirect("note", "open_note", $note_id);
        }
    }

    public function edit(): void
    {
        $notes_coded = "";
        $labels_checked_coded = "";

        // Vérifie si l'identifiant de la note est présent et valide dans l'URL
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupère la note par son identifiant
            $note = Note::get_note_by_id($note_id);
            // Récupère l'identifiant de l'utilisateur connecté
            $user_id = $this->get_user_or_redirect()->id;
            // Vérifie si la note est archivée, épinglée, partagée comme éditeur ou partagée comme lecteur
            $archived = $note->in_my_archives($user_id);
            $pinned = $note->is_pinned($user_id);
            $is_shared_as_editor = $note->is_shared_as_editor($user_id);
            $is_shared_as_reader = $note->is_shared_as_reader($user_id);
            $content = $note->get_content();
            if (isset($_GET["param2"]) && isset($_GET["param3"])) {
                $notes_coded = $_GET["param2"];
                $labels_checked_coded = $_GET["param3"];
            }
        }
        // Affiche la vue appropriée en fonction du type de note (texte ou checklist)
        ($note->get_type() == "TextNote" ? new View("edit_text_note") : new View("edit_checklist_note"))->show([
            "note" => $note,
            "note_id" => $note_id,
            "created" => $this->get_created_time($note_id),
            "edited" => $this->get_edited_time($note_id),
            "archived" => $archived,
            "is_shared_as_editor" => $is_shared_as_editor,
            "is_shared_as_reader" => $is_shared_as_reader,
            "content" => $content,
            "pinned" => $pinned,
            "user_id" => $user_id,
            "notes_coded" => $notes_coded,
            "labels_checked_coded" => $labels_checked_coded
        ]);
    }

    // Méthode pour ouvrir la vue d'ajout d'une note
    public function add_text_note(): void
    {
        // Récupère l'identifiant de l'utilisateur connecté
        $user_id = $this->get_user_or_redirect()->id;

        // Crée une instance de vue pour l'ajout de note texte
        $view = new View("add_text_note");

        // Prépare les données par défaut pour initialiser la vue
        $data = [
            "user_id" => $user_id,
            "note_id" => null,
            "created" => date("Y-m-d H:i:s"),
            "edited" => null,
            "archived" => 0,
            "is_shared_as_editor" => 0,
            "is_shared_as_reader" => 0,
            "content" => "",
            "pinned" => 0
        ];

        $view->show($data);
    }

    /* ***** Méthodes AJAX ***** */

    // Méthode pour vérifier le titre d'une note via un service AJAX
    public function check_title_service()
    {
        $title_error = "";
        if (isset($_POST['title'])) {
            $title = $_POST['title'];
            if ($_POST['note'] != null) {
                $note_id = (int) str_replace("&quot;", "", $_POST["note"]);
                $note = Note::get_note_by_id($note_id);
                if ($note->validate_title_service($title) != null)
                    $title_error = $note->validate_title_service($title)[0];
            } else {
                if (Note::validate_new_title_service($title) != null)
                    $title_error = Note::validate_new_title_service($title)[0];
            }
            if (!empty($title_error)) {
                echo $title_error;
            }
        }
    }


    public function add_title_service()
    {
        $title_error = "";
        if (isset($_POST['title'])) {
            $title = $_POST['title'];
            if ($_POST['note'] != null) {
                $note_id = (int) str_replace("&quot;", "", $_POST["note"]);
                $note = Note::get_note_by_id($note_id);
                if ($note->validate_title_service($title) != null)
                    $title_error = $note->validate_title_service($title)[0];
            } else {
                if (Note::validate_new_title_service($title) != null)
                    $title_error = Note::validate_new_title_service($title)[0];
            }
            if (empty($title_error)) {
                $note->title = Tools::sanitize($_POST['title']);
                $note->persist();
            }
        }
    }

    // Méthode pour vérifier le contenu d'une note via un service AJAX
    public function check_content_service()
    {
        $content_error = "";
        if (isset($_POST['content'])) {
            $content = $_POST['content'];
            if (Note::validate_content_service($content) != null)
                $content_error = Note::validate_content_service($content)[0];
            if (!empty($content_error)) {
                echo $content_error;
            }
        }
    }

    // Méthode pour vérifier le contenu d'une checklist note via un service AJAX
    public function check_content_checklist_service()
    {
        $error = []; // Tableau pour stocker les messages d'erreur

        // Vérifie si les données nécessaires sont présentes dans la requête POST
        if (isset($_POST['items'], $_POST['id'])) {
            // Récupération de l'identifiant de l'élément et de son contenu
            $id = $_POST['id'];
            $content = Tools::sanitize($_POST['items']);


            // Récupération de l'élément de la checklist associé à l'identifiant
            $checklistItem = CheckListNoteItem::get_item_by_id($id);

            if ($checklistItem) {
                // Vérification si le contenu est unique pour l'élément
                if (!$checklistItem->is_unique_service($content)) {
                    $error[$id] = "Item must be unique"; // Erreur si le contenu n'est pas unique
                } else {
                    // Validation du contenu de l'élément
                    $contentErrors = $checklistItem->validate_item_service($content);
                    if (!empty($contentErrors)) {
                        $error[$id] = $contentErrors[0]; // Erreur de validation du contenu
                    }
                }
            } else {
                $error[$id] = "Item not found in database"; // Erreur si l'élément n'est pas trouvé
            }
            // Retourne les messages d'erreur au format JSON
            echo json_encode($error);
        }
    }

    public function save_content_checklist_service()
    {
        $errors = []; // Tableau pour stocker les messages d'erreur

        // Vérifie si les données nécessaires sont présentes dans la requête POST
        if (isset($_POST['items'], $_POST['id'])) {
            // Récupération de l'identifiant de l'élément et de son contenu
            $id = $_POST['id'];
            $content = Tools::sanitize($_POST['items']);


            // Récupération de l'élément de la checklist associé à l'identifiant
            $checklistItem = CheckListNoteItem::get_item_by_id($id);

            if ($checklistItem) {
                // Vérification si le contenu est unique pour l'élément
                if (!$checklistItem->is_unique_service($content)) {
                    $errors[] = "Item must be unique"; // Erreur si le contenu n'est pas unique
                } else {
                    // Validation du contenu de l'élément
                    if ($checklistItem->validate_item_service($content) != null)
                        $errors = $checklistItem->validate_item_service($content)[0];
                }
                if (empty($errors)) {
                    var_dump($checklistItem);
                    $checklistItem->set_content($content);
                    $checklistItem->persist();
                }
            }
        }
    }

    // Méthode pour vérifier un nouvel item d'une checklist note via un service AJAX
    public function check_new_content_checklist_service()
    {
        $error = []; // Tableau pour stocker les messages d'erreur

        // Vérifie si les données nécessaires sont présentes dans la requête POST
        if (isset($_POST['new'], $_POST['note_id'])) {
            // Récupération de l'identifiant de l'élément et de son contenu
            $note_id = $_POST['note_id'];
            $content = Tools::sanitize($_POST['new']);
            $item = new CheckListNoteItem(0, $note_id, $content, false);


            if ($item) {
                // Vérification si le contenu est unique pour l'élément
                if (!$item->is_unique_service($content)) {
                    $error[] = "Item must be unique"; // Erreur si le contenu n'est pas unique
                } else {
                    // Validation du contenu de l'élément
                    $contentErrors = $item->validate_item_service($content);
                    if (!empty($contentErrors)) {
                        $error[] = $contentErrors[0]; // Erreur de validation du contenu
                    }
                }
            } else {
                $error[] = "Item not found in database"; // Erreur si l'élément n'est pas trouvé
            }
            // Retourne les messages d'erreur au format JSON
            echo json_encode($error);
        }
    }

    // ajoutes un item à la base de donnée sur base d'un service AJAX
    public function add_new_content_checklist_service()
    {
        $errors = []; // Tableau pour stocker les messages d'erreur

        // Vérifie si les données nécessaires sont présentes dans la requête POST
        if (isset($_POST['new'], $_POST['note_id'])) {
            // Récupération de l'identifiant de l'élément et de son contenu
            $note_id = $_POST['note_id'];
            $content = Tools::sanitize($_POST['new']);
            $item = new CheckListNoteItem(0, $note_id, $content, false);

            if ($item) {
                // Vérification si le contenu est unique pour l'élément
                if (!$item->is_unique_service($content)) {
                    $errors[] = "Item must be unique"; // Erreur si le contenu n'est pas unique
                } else {
                    // Validation du contenu de l'élément
                    if ($item->validate_item_service($content) != null)
                        $errors = $item->validate_item_service($content)[0];
                }
                if (empty($errors)) {
                    $item->persist();
                    // màj date edition
                    $note = Note::get_note_by_id($item->checklist_note);
                    $date = new DateTime();
                    $note->edited_at = $date->format('Y-m-d H:i:s');
                    $note->persist();
                    echo json_encode($item);
                }
            }
        }
    }

    // supprime un item de la base de donnée sur base d'un service AJAX
    public function delete_item_service()
    {
        //action delete item
        if (isset($_POST['id'])) {
            $id = $_POST["id"];
            $item = CheckListNoteItem::get_item_by_id($id);
            if ($item === null) {
                throw new Exception("Undefined checklist item");
            }
            $item->delete();
            // màj date édition
            $note = Note::get_note_by_id($item->checklist_note);
            $date = new DateTime();
            $note->edited_at = $date->format('Y-m-d H:i:s');
            $note->persist();
            echo ($id);
        }
    }


    public function labels()
    {
        $labels_note = [];
        $nvlab = [];
        $user = $this->get_user_or_redirect();
        $all = $user->get_labels();
        $errors = [];
        $notes_coded = "";
        $labels_checked_coded = "";
        //vérifier et récupérer l'id en paramètre
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupération de la note par son identifiant
            $note = Note::get_note_by_id($note_id);
            $labels_note = $note->get_labels();

            if (isset($_GET["param2"]) && isset($_GET["param3"])) {
                $notes_coded = $_GET["param2"];
                $labels_checked_coded = $_GET["param3"];
            }

            //verifier et mis en tableau les labels non utilisé
            foreach ($all as $label) {
                if (!in_array($label, $labels_note)) {
                    $nvlab[] = $label;
                }
            }

            //rajouter un nouveau label
            if (isset($_POST["new_label"]) && isset($_POST["new_label"]) !== "") {
                $content = $_POST["new_label"];
                $new_label = new NoteLabel($note->note_id, $content);
                $errors = $new_label->validate_label();
                if (empty($errors)) {
                    $new_label->persist();
                    $this->redirect("note", "labels", $note->note_id, $notes_coded, $labels_checked_coded);
                }
            }
        }
        (new View("labels"))->show([
            "labels" => $labels_note,
            "note" => $note,
            "all" => $nvlab,
            "errors" => $errors,
            "notes_coded" => $notes_coded,
            "labels_checked_coded" => $labels_checked_coded
        ]);
    }

    public function delete_label()
    {
        $user = $this->get_user_or_redirect();
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupération de la note par son identifiant
            $note = Note::get_note_by_id($note_id);
            $content = $_POST["label"];
            $label = NoteLabel::get_note_label($note->note_id, $content);
            $label->delete();
            $this->redirect("note", "labels", $note->note_id);
        }
    }

    public function add_label_service()
    {

        $this->get_user_or_redirect();
        $errors = "";
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupération de la note par son identifiant
            $note = Note::get_note_by_id($note_id);

            //rajouter un nouveau label
            if (isset($_POST["new_label"]) && isset($_POST["new_label"]) !== "") {
                $content = $_POST["new_label"];
                $new_label = new NoteLabel($note->note_id, $content);
                $errors = implode($new_label->validate_label());
                if (empty($errors)) {
                    $new_label->persist();
                }
            }
            if (!empty($errors)) {
                echo json_encode($errors);
            }
        }
    }

    public function delete_label_service()
    {
        $user = $this->get_user_or_redirect();
        if (isset($_GET["param1"]) && isset($_GET["param1"]) !== "") {
            $note_id = $_GET["param1"];
            // Récupération de la note par son identifiant
            $note = Note::get_note_by_id($note_id);
            $content = $_POST["label"];
            $label = NoteLabel::get_note_label($note->note_id, $content);
            $label->delete();
        }
    }

}
