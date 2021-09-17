<?php
namespace App\Models;
use CodeIgniter\Model;

class ProductGroupMemberModel extends Model{
        
    use PermissionTrait;

    protected $table      = 'product_group_member_list';
    protected $primaryKey = 'product_id';
    protected $allowedFields = [
        'product_id',
        'product_group_id'
        ];
    
    public function itemUpdate( $product_id, $product_group_id, $value ){
        if( $value ){
            return $this->productGroupJoin($product_id, $product_group_id);
        }
        return $this->productGroupLeave($product_id, $product_group_id);
    }
    
    public function productMemberGroupsGet($product_id){
        return $this->select('GROUP_CONCAT(product_group_list.product_group_id) product_group_ids,GROUP_CONCAT(product_group_type) product_group_types')
                ->where('product_id',$product_id)
                ->join('product_group_list', 'product_group_list.product_group_id = product_group_member_list.product_group_id')
                ->get()->getRow();
    }
    
    public function productGroupJoinByType($product_id,$product_group_type){
        $product_group_id=$this
                ->query("SELECT product_group_id FROM product_group_list WHERE product_group_type='$product_group_type'")
                ->getRow('product_group_id');
        return $this->productGroupJoin($product_id,$product_group_id);
    }
    
    public function productGroupJoin($product_id,$product_group_id){
        $this->permit(null,'w');
        return $this->insert(['product_id'=>$product_id,'product_group_id'=>$product_group_id]);
    }
    
    public function productGroupLeave($product_id,$product_group_id){
        $this->permitWhere('w');
        return $this
                ->where('product_id',$product_id)
                ->where('product_group_id',$product_group_id)
                ->delete();
    }
    
    
}