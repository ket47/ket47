<?php
namespace App\Models;
use CodeIgniter\Model;

class UserGroupModel extends Model{
        
    use PermissionTrait;

    protected $table      = 'user_group_list';
    protected $primaryKey = 'user_group_id';
    protected $allowedFields = [
        'user_group_parent_id',
        'user_group_name',
        'user_group_type'
        ];
    
    
    public function listGet( $filter=null ){
        $this->permitWhere('r');
        return $this->get()->getResult();
    }
    
    
    
}