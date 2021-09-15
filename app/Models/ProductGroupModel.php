<?php
namespace App\Models;
use CodeIgniter\Model;

class ProductGroupModel extends Model{
        
    use PermissionTrait;

    protected $table      = 'product_group_list';
    protected $primaryKey = 'product_group_id';
    protected $allowedFields = [
        'product_group_parent_id',
        'product_group_name',
        'product_group_type'
        ];
    
    
    public function listGet( $filter=null ){
        $this->permitWhere('r');
        return $this->get()->getResult();
    }
    
    
    
}