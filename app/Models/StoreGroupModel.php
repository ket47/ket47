<?php
namespace App\Models;
use CodeIgniter\Model;

class StoreGroupModel extends Model{
        
    use PermissionTrait;

    protected $table      = 'store_group_list';
    protected $primaryKey = 'store_group_id';
    protected $allowedFields = [
        'store_group_parent_id',
        'store_group_name',
        'store_group_type'
        ];
    
    
    public function listGet( $filter=null ){
        $this->permitWhere('r');
        return $this->get()->getResult();
    }
    
    
    
}