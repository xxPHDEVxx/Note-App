<?php

require_once "framework/Model.php";
require_once "Note.php";
require_once "ChecklistNote.php";

class ChecklistNoteItem extends Model
{
    public function __construct(

        public int $id,
        public string $content,
        public bool $checked      

    ) {
    }

    public static function get_items(ChecklistNote $checklistNote) : array {
        $query =  self::execute("SELECT id,content, checked 
        FROM checklist_note_items
        WHERE checklist_note = :id", ["id" => $checklistNote->id]);
        $data = $query->fetchAll();
        $items = [];
        foreach ($data as $row) {
            $items[] = new ChecklistNoteItem( 
                $row['id'],
                $row['content'],
                $row['checked']);
        }


        return $items;    
    }


}
