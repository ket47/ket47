<?php
namespace App\Models;
use CodeIgniter\Model;

class SampleModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'item_list';
    protected $primaryKey = 'item_id';
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