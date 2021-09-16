<?php
namespace App\Models;
use CodeIgniter\Model;

class StoreGroupMemberModel extends Model{
        
    use PermissionTrait;

    protected $table      = 'store_group_member_list';
    protected $primaryKey = 'store_id';
    protected $allowedFields = [
        'store_id',
        'store_group_id'
        ];
    
    
    public function itemUpdate( $store_id, $store_group_id, $value ){
        if( $value ){
            return $this->storeGroupJoin($store_id, $store_group_id);
        }
        return $this->storeGroupLeave($store_id, $store_group_id);
    }
    
    
    public function storeMemberGroupsGet($store_id){
        return $this->select('GROUP_CONCAT(store_group_list.store_group_id) store_group_ids,GROUP_CONCAT(store_group_type) store_group_types')
                ->where('store_id',$store_id)
                ->join('store_group_list', 'store_group_list.store_group_id = store_group_member_list.store_group_id')
                ->get()->getRow();
    }
    
    public function storeGroupJoinByType($store_id,$store_group_type){
        $store_group_id=$this
                ->query("SELECT store_group_id FROM store_group_list WHERE store_group_type='$store_group_type'")
                ->getRow('store_group_id');
        return $this->storeGroupJoin($store_id,$store_group_id);
    }
    
    public function storeGroupJoin($store_id,$store_group_id){
        $this->permit(null,'w');
        return $this->insert(['store_id'=>$store_id,'store_group_id'=>$store_group_id]);
    }
    
    public function storeGroupLeave($store_id,$store_group_id){
        $this->permitWhere('w');
        return $this
                ->where('store_id',$store_id)
                ->where('store_group_id',$store_group_id)
                ->delete();
    }
    
    
}