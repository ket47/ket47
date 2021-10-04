<?php
namespace App\Models;
use CodeIgniter\Model;

class ImporterModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'imported_list';
    protected $primaryKey = 'id';
    protected $allowedFields = [

        ];

    protected $useSoftDeletes = false;
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( $item ){
        //$item['owner_id']=
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
    
    public function listCreate( $list ){
        
        
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}