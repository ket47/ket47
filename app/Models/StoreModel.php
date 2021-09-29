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
        'store_tax_num',
        'store_company_name_new',
        'store_minimal_order',
        'store_time_preparation',
        'store_time_opens_0',
        'store_time_opens_1',
        'store_time_opens_2',
        'store_time_opens_3',
        'store_time_opens_4',
        'store_time_opens_5',
        'store_time_opens_6',
        'store_time_closes_0',
        'store_time_closes_1',
        'store_time_closes_2',
        'store_time_closes_3',
        'store_time_closes_4',
        'store_time_closes_5',
        'store_time_closes_6',
        'deleted_at',
        'owner_id',
        'owner_ally_id'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_name_new'        => 'required|min_length[3]',
        'store_description_new' => 'min_length[10]',
        'store_company_name_new' => 'min_length[3]',
        'store_tax_num'         => 'exact_length[10,12]|integer'
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
                    'image_holder_id'=>$store->store_id,
                    'is_disabled'=>1,
                    'is_deleted'=>1,
                    'is_active'=>1,
                    'limit'=>30
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
        $store_id=$this->insert(['store_name_new'=>$name],true);
        if( $store_id ){
            $this->allowedFields[]='owner_id';
            $this->allowedFields[]='is_disabled';
            $this->update($store_id,['owner_id'=>$user_id,'is_disabled'=>1]);
            return $store_id;
        }
        return 'item_create_error';
    }
    
    
    public function itemUpdate( $store ){
        $this->permitWhere('w');
        $this->update($store->store_id,$store);
        return $this->db->affectedRows()?'item_update_ok':'item_update_forbidden';
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
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet('store_group_member_list');
        return $GroupMemberModel->itemUpdate( $store_id, $group_id, $is_joined );
    }
    
    public function itemDelete( $store_id ){
        if( !$this->permit($store_id,'w') ){
            return 'item_delete_forbidden';
        }
        $this->itemDeleteChildProducts( $store_id );
        $this->delete($store_id);
        return $this->db->affectedRows()?'item_delete_ok':'item_delete_error';
    }
    
    private function itemDeleteChildProducts( $store_id ){
        $ProductModel=model('ProductModel');
        $ProductModel->where('deleted_at IS NOT NULL OR is_disabled=1');
        $ProductModel->where('store_id',$store_id);
        $trashed_products=$ProductModel->get()->getResult();
        foreach($trashed_products as $product){
            $ProductModel->itemPurge($product->product_id);
        }
        $ProductModel->where('store_id',$store_id);
        $ProductModel->delete();
    }
    
    public function itemDisable( $store_id, $is_disabled ){
        if( !$this->permit($store_id,'w','disabled') ){
            return 'item_update_forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['store_id'=>$store_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'item_update_disabled_ok':'item_update_disabled_error';
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
    
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $data ){
        $data['is_disabled']=1;
        $data['owner_id']=session()->get('user_id');
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemCreate($data);
        }
        return 0;
    }

    public function imageUpdate( $data ){
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemUpdate($data);
        }
        return 0;
    }
    
    public function imageDisable( $image_id, $is_disabled ){
        if( !sudo() ){
            return 'image_update_disable_forbidden';
        }
        $ImageModel=model('ImageModel');
        $ok=$ImageModel->itemDisable( $image_id, $is_disabled );
        if( $ok ){
            return 'image_update_disable_ok';
        }
        return 'image_update_disable_error';
    }    
    
    public function imageDelete( $image_id ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $store_id=$image->image_holder_id;
        if( !$this->permit($store_id,'w') ){
            return 'image_delete_forbidden';
        }
        $ok=$ImageModel->itemPurge( $image_id );
        if( $ok ){
            return 'image_delete_ok';
        }
        return 'image_delete_error';
    }
    
    public function imageOrder( $image_id, $dir ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $store_id=$image->image_holder_id;
        if( !$this->permit($store_id,'w') ){
            return 'image_order_forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'image_order_ok';
        }
        return 'image_order_error';
    }
    
    
    public function listPurge( $olderThan=7, $image_holder=null, $image_holder_id=null ){
//        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan days");
//        $this->where('deleted_at<',$olderStamp);
//        if( $image_holder ){
//            $this->where('image_holder',$image_holder);
//        }
//        if( $image_holder_id ){
//            $this->where('image_holder_id',$image_holder_id);
//        }
//        $list_to_purge=$this->select('image_id')->get()->getResult();
//        foreach( $list_to_purge as $item_to_purge ){
//            $this->itemPurge($item_to_purge->image_id);
//        }
//        return 'list_purge_ok';
    }
}
