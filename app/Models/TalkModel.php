<?php
namespace App\Models;
class TalkModel extends SecureModel{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'wall_room_enties_list';
    protected $primaryKey = 'item_id';
    protected $returnType = 'object';
    protected $allowedFields = [

        ];

    protected $useSoftDeletes = true;
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}