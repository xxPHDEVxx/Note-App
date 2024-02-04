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
    private int $owner;
    private string $created_at;
    private bool $pinned;
    private bool $archived;
    private int $weight;
    private ?string $edited_at = NULL;
    private ?int $note_id = NULL;

    public function __construct( $initial_title, $initial_owner, $initial_created_at, $initial_pinned, $initial_archived, $initial_weight, $initial_edited_at, $initial_note_id

    ) {
        $this->title = $initial_title ;
        $this->owner = $initial_owner;
        $this->created_at= $initial_created_at; 
        $this->pinned = $initial_pinned;
        $this->archived = $initial_archived;
        $this->weight = $initial_weight ;
        $this->edited_at = $initial_edited_at; 
        $this->note_id = $initial_note_id;
    
    }


    public static function get_notes(User $user, bool $pinned) : array {
        $pinnedCondition = $pinned ? '1' : '0';

        $query = self::execute("SELECT * FROM notes WHERE owner = :ownerid AND archived = 0 AND pinned = :pinned ORDER BY -weight" , ["ownerid" => $user->id, "pinned" => $pinnedCondition]);
        $data = $query->fetchAll();
        $all_notes = [];

        foreach ($data as $row) {
            $all_notes[] = new Note($row['title'],$row['owner'],$row['created_at'], $row['pinned'], $row['archived'], $row['weight'], $row['edited_at'],$row['id'] );
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
        $query = self::execute("select * from Notes where note_id = :id", ["id" => $note_id]);
        $data = $query->fetch(); 
        if($query->rowCount() == 0) {
            return false;
        }else {
            return new Note( $data['title'] , $data['owner'], $data['created_at'],$data['pinned'], $data['archived'], $data['weight'],$data['edited_at'],$data['id']);
        }

    }

    public function getTitle() : string {
        return $this->title;
    }

    public function setTitle(string $title) : void {
        $this->title = $title;
    }

    public function  getOwner() {
        return $this->owner;
    }

    public function setOwner(int $owner) {
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

    public function  getWeight() : int {
        return $this->weight;
    }

    public function  setWeight(int $weight) : void {
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
                                'owner' => $this->owner,
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

    
    public static function get_archives(User $user): array {
        $archives = [];
        $query = self::execute("SELECT id, title FROM notes WHERE owner = :ownerid AND archived = 1 ORDER BY -weight" , ["ownerid" => $user->id]);
        $archives = $query->fetchAll();
        $content_checklist = [];
       foreach ($archives as &$row) {
            $dataQuery = self::execute("SELECT content FROM text_notes WHERE id = :note_id", ["note_id" => $row["id"]]);
            $content = $dataQuery->fetchColumn(); 
          
            if(!$content) {
                $dataQuery = self::execute("SELECT content, checked FROM checklist_note_items WHERE checklist_note = :note_id ", ["note_id" => $row["id"]]);
                $content_checklist = $dataQuery->fetchAll();
            }
            $row["content"] = $content;
            $row["content_checklist"] = $content_checklist;
        }
        return $archives;
    }

    public static function get_shared_by(int $userid, int $ownerid) : array {
        $shared_by = [];
        $query = self::execute("SELECT id, title, editor FROM notes JOIN note_shares ON notes.id = note_shares.note and note_shares.user = :userid and 
        notes.owner = :ownerid", ["ownerid"=>$ownerid, "userid"=>$userid]);
        $shared_by = $query->fetchAll();
        $content_checklist = [];
        foreach ($shared_by as &$row) {
             $dataQuery = self::execute("SELECT content FROM text_notes WHERE id = :note_id", ["note_id" => $row["id"]]);
             $content = $dataQuery->fetchColumn(); 
           
             if(!$content) {
                 $dataQuery = self::execute("SELECT content, checked FROM checklist_note_items WHERE checklist_note = :note_id ", ["note_id" => $row["id"]]);
                 $content_checklist = $dataQuery->fetchAll();
             }
             $row["content"] = $content;
             $row["content_checklist"] = $content_checklist;
            }
        return $shared_by;

        
    }

    public static function get_shared_note(User $user): array {
        $shared = [];
        $query = self::execute("SELECT note from note_shares WHERE user = :userid" , ['userid'=>$user->id]);
        $shared_note_id = $query->fetchAll(PDO::FETCH_COLUMN);
        foreach ($shared_note_id as $note_id) {
           $note = Note::get_note_by_id($note_id);
            $shared[] = $note;

        }
        return $shared;
    }

    


    public function get_title(): string {
        return $this->title;
    }

}
