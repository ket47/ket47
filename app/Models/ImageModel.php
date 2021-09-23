<?php
namespace App\Models;
use CodeIgniter\Model;

class ImageModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'image_list';
    protected $primaryKey = 'image_id';
    protected $allowedFields = [
        'image_holder',
        'image_holder_id',
        'image_extension',
        'image_description'
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
        return [
            
        ];
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