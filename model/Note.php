<?php

require_once "framework/Model.php";
require_once "User.php";
require_once "TextNote.php";
require_once "ChecklistNote.php";

enum TypeNote {
    const TN = "TextNote";
    const CLN = "ChecklistNote";
}


 class Note extends Model
{
    private String $title;
    private User $owner;
    private string $created_at;
    private bool $pinned;
    private bool $archived;
    private int $weight;
    private ?string $edited_at = NULL;
    private ?int $note_id = NULL;

    public function __construct(
        $initial_title,
        User $initial_owner,
        $initial_created_at,
        $initial_pinned,
        $initial_archived,
        $initial_weight,
        $initial_edited_at,
        $initial_note_id
    ) {
        $this->title = $initial_title;
        $this->owner = $initial_owner;
        $this->created_at = $initial_created_at;
        $this->pinned = $initial_pinned;
        $this->archived = $initial_archived;
        $this->weight = $initial_weight;
        $this->edited_at = $initial_edited_at;
        $this->note_id = $initial_note_id;
    }


    public function get_title() : string {
        return $this->title;
    }

    public function setTitle(string $title) : void {
        $this->title = $title;
    }

    public function  getOwner() {
        return $this->owner;
    }

    public function setOwner(User $owner) {
        $this->owner = $owner;
    }

    public function  getCreated_at() : string {
        return $this->created_at;
    }

    public function  setCreated_at(String $created_at): void {
        $this->created_at = $created_at;
    }

    public function isPinned() : bool {
        return $this->pinned;
    }

    public function setPinned(bool $pinned) : void {
        $this->pinned = $pinned;
    }

    public function  isArchived() : bool {
        return $this->archived;
    }

    public function  setArchived(bool $archived) : void {
        $this->archived = $archived;
    }

    public function  get_weight() : int {
        return $this->weight;
    }

    public function  set_weight(int $weight) : void {
        $this->weight = $weight;
    }

    public function  getEdited_at() : string {
        return $this->edited_at;
    }

    public function setEdited_at(string $edited_at) : void {
        $this->edited_at = $edited_at;
    }

    public function  getNote_id() : int {
        return $this->note_id;
    }

    public function  setNote_id(int $note_id) : void {
        $this->note_id = $note_id;
    }


    private static function get_notes(User $user, bool $pinned) : array {
        $pinnedCondition = $pinned ? '1' : '0';

        $query = self::execute("SELECT * FROM notes WHERE owner = :ownerid AND archived = 0 AND pinned = :pinned ORDER BY -weight" , ["ownerid" => $user->id, "pinned" => $pinnedCondition]);
        $data = $query->fetchAll();
        $all_notes = [];

        foreach ($data as $row) {
            $all_notes[] = new Note($row['title'],User::get_user_by_id($row['owner']),$row['created_at'], $row['pinned'], $row['archived'], $row['weight'], $row['edited_at'],$row['id'] );
        }

        $notes = [];

        
        foreach ($all_notes as $note) {
            $query_cln = self::execute("SELECT id from checklist_notes where id = :id ", ["id" => $note->note_id]);
            if ($query_cln->rowCount() == 0) {
                $query_text = self::execute("SELECT content FROM text_notes WHERE id = :note_id", ["note_id" => $note->note_id]);
                $data_text = $query_text->fetchColumn();         
                $notes[] = new TextNote($note,$note->note_id,$data_text); 
                    } else {
                $notes[]= self::get_checklist_note($note,$note->note_id);
            }
            
         }
        return $notes;
    }
    

    public static function get_checklist_note(Note $note,int $id) : ChecklistNote {
        $content = new ChecklistNote($note,$id); 

        return $content;
    }

    public static function get_notes_pinned(User $user) : array {
        return self::get_notes($user, true);
    }

    public static function get_notes_unpinned(User $user) : array {
        return self::get_notes($user, false);
    }

    public static function get_note_by_id(int $note_id) : Note |false {
        $query = self::execute("SELECT * FROM notes WHERE id = :id", ["id" => $note_id]);
        $data = $query->fetch(); 
        if($query->rowCount() == 0) {
            return false;
        }else {
            return new Note( $data['title'] , 
            User::get_user_by_id($data['owner']), 
            $data['created_at'], 
            $data['pinned'], 
            $data['archived'], 
            $data['weight'], 
            $data['edited_at'], 
            $data['id']);
        }

    }




    public function delete(User $initiator) : Note |false {
        if($this->owner == $initiator) {
            self::execute("DELETE FROM Notes WHERE id = :note_id", ['note_id' => $this->note_id]);
            return $this;
        }
        return false;
    }

    public function validate() : array {
        $errors = [];

        return $errors;
    }
    public function persist() : Note|array {
        if($this->note_id == NULL) {
            $errors = $this->validate();
            if(empty($errors)){
                self::execute('INSERT INTO Notes (title, owner, pinned, archived, weight) VALUES (:title, :owner,:pinned,:archived,:weight)', 
                               ['title' => $this->title,
                                'owner' => $this->owner->id,
                                'pinned' => $this->pinned? 1 : 0,
                                'archived' => $this->archived? 1 : 0,
                                'weight' => $this->weight,
                               ]);
                $note = self::get_note_by_id(self::lastInsertId());
                $this->note_id = $note->note_id;
                return $this;
            } else {
                return $errors; 
            }
        } else {
            //on ne modifie jamais les notes : pas de "UPDATE" SQL.
            throw new Exception("Not Implemented.");
        }
    }

    public function is_weight_unique(int $id): int {
        $query = self::execute("SELECT id, MAX(weight) from notes where id = :note_id group by id" , ["note_id" => $id]);
        $data = $query->fetch();
        return $data['id'];
    }

    public function get_note_up(User $user,int $note_id, int $weight, bool $pin): Note | false {
        $query = self::execute("
        SELECT * FROM notes n
        WHERE owner = :ownerid AND n.id <> :note_id AND archived = 0 AND pinned = :pin AND weight > :weight 
        ORDER BY weight LIMIT 1
        ", ["ownerid" => $user->id, "note_id" => $note_id, "pin" => $pin, "weight" => $weight]);
    
        $data = $query->fetch();
        if (!$data) {
            return false;
        }

        $note_up = new Note(
            $data['title'],
            $user,
            $data['created_at'],
            $data['pinned'],
            $data['archived'],
            $data['weight'],
            $data['edited_at'],
            $data['id']
        );
    
        return $note_up;
    } 

    public function move_db(Note $second) : Note {
        $weight_second = $second->get_weight();
        $second_id = $second->getNote_id();
        self::execute('UPDATE notes SET weight = :weight_note2 WHERE id = :id_note1', 
        ['id_note1' => $this->note_id, 'weight_note2' => $weight_second]);
        self::execute('UPDATE notes SET weight = :weight_note1 WHERE id = :id_note2', ['id_note2' => $second_id, 'weight_note1' => $this->weight]);


        return $this;
    }
}
