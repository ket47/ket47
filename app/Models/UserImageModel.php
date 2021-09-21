<?php
namespace App\Models;
use CodeIgniter\Model;

class UserImageModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'user_image_list';
    protected $primaryKey = 'image_id';
    protected $allowedFields = [
        'user_name',
        'user_surname',
        'user_middlename',
        'user_phone',
        'user_email',
        'user_pass',
        ];
    protected $returnType     = 'array';
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
}