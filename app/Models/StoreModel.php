<?php
namespace App\Models;
use CodeIgniter\Model;

class StoreModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'store_list';
    protected $primaryKey = 'store_id';
    protected $allowedFields = [
        'store_name',
        'store_phone',
        'store_email',
        'store_description',
        'is_disabled',
        'deleted_at',
        'owner_id',
        'owner_ally_id'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->permitWhere('r');
        $this->orderBy('modified_at','DESC');
        $store_list = $this->get()->getResult();
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet('store_group_member_list');
        foreach($store_list as $store){
            if($store){
                $store->member_of_groups=$GroupMemberModel->memberGroupsGet($store->store_id);
            }
        }
        return $store_list;
    }
    
    public function listCreate(){
        
    }
    
    public function listUpdate( $list ){
        $this->permitWhere('w');
        return $this->updateBatch($list,'store_id');
    }
    
    public function listDelete(){
        
    }
    
    public function itemGet( $store_id ){
        $store_list=$this->listGet( ['store_id'=>$store_id] );
        if( !$store_list ){
            return [];
        }
        return $store_list[0];
    }
    
    public function itemCreate( $name ){
        if( !$this->permit(null,'w') ){
            return 'item_create_error_forbidden';
        }
        $user_id=session()->get('user_id');
        $has_store_id=$this->where('owner_id',$user_id)->get()->getRow('store_id');
        if( $has_store_id ){
            return 'item_create_error_dublicate';
        }
        $store_id=$this->insert(['store_name'=>$name],true);
        if( $store_id ){
            $this->update($store_id,['owner_id'=>$user_id]);
            return $store_id;
        }
        return 'item_create_error';
    }
    
    public function itemUpdate( $data ){
        return $this->listUpdate([$data]);
    }
    
    public function itemDelete( $store_id ){
        $this->permitWhere('w');
        return $this->delete(['store_id'=>$store_id]);
    }
}