<?php
namespace App\Models;
use CodeIgniter\Model;

class StoreModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'store_list';
    protected $primaryKey = 'store_id';
    protected $allowedFields = [
        'store_name_new',
        'store_phone',
        'store_email',
        'store_description_new',
        'deleted_at',
        'owner_id',
        'owner_ally_id'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_name'     => 'required|min_length[3]',
        'store_description'     => 'min_length[10]',
    ];
    
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->permitWhere('r');
        $this->orderBy('modified_at','DESC');
        $store_list = $this->get()->getResult();
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet('store_group_member_list');
        
        $ImageModel=model('ImageModel');
        foreach($store_list as $store){
            if($store){
                $store->member_of_groups=$GroupMemberModel->memberOfGroupsGet($store->store_id);
                $filter=[
                    'image_holder'=>'store',
                    'image_holder_id'=>$store->store_id
                ];
                $store->images=$ImageModel->listGet($filter);
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
        $store_id=$this->insert(['store_name'=>$name,'is_disabled'=>1],true);
        if( $store_id ){
            $this->update($store_id,['owner_id'=>$user_id]);
            return $store_id;
        }
        return 'item_create_error';
    }
    
    public function itemUpdate( $data ){
        return $this->listUpdate([$data]);
    }
    
    public function itemUpdateGroup($store_id,$group_id,$is_joined){
        if( !$this->permit($store_id,'w') ){
            return 'item_update_forbidden';
        }
        $GroupModel=model('GroupModel');
        $GroupModel->tableSet('store_group_list');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'item_update_group_not_found';
        }
        
//        $allowed_group_types=['supplier','courier'];
//        if( !in_array($target_group->group_type, $allowed_group_types) && !sudo() ){
//            return 'item_update_forbidden';
//        }
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet('store_group_member_list');
        return $GroupMemberModel->itemUpdate( $store_id, $group_id, $is_joined );
    }
    
    public function itemDelete( $store_id ){
        $this->permitWhere('w');
        return $this->delete(['store_id'=>$store_id]);
    }
    
    public function itemDisable( $store_id, $is_disabled ){
        if( !$this->permit($store_id,'w','disabled') ){
            return 'item_update_forbidden';
        }
        $this->allowedFields[]='is_disabled';
        return $this->update(['store_id'=>$store_id],['is_disabled'=>$is_disabled?1:0]);
    }
    
    
    public function fieldApprove( $store_id, $field_name ){
        if( !sudo() ){
            return 'field_approve_forbidden';
        }
        $new_value=$this->where('store_id',$store_id)->select("{$field_name}_new")->get()->getRow("{$field_name}_new");
        $this->allowedFields[]=$field_name;
        $data=[
            $field_name=>$new_value,
            "{$field_name}_new"=>""
        ];
        return $this->update(['store_id'=>$store_id],$data);
    }
}