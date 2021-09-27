<?php
namespace App\Models;
use CodeIgniter\Model;

class ProductModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'product_list';
    protected $primaryKey = 'product_id';
    protected $allowedFields = [
        'store_id',
        'product_code',
        'product_name',
        'product_quantity',
        'product_description',
        'product_weight',
        'product_price',
        'is_produced'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_id'         => 'required|numeric',
        'product_name'     => 'required|min_length[3]',
        'product_price'    => 'required|numeric'
    ];
    
//    protected $beforeInsert = ['onBeforeInsert'];
//    protected $beforeUpdate = ['onBeforeUpdate'];
//    protected function onBeforeInsert(array $data){
//        $data['data']['store_id']=$this->current_store_id;
//        $data['data']['owner_id']=$this->current_store_owner;
//        return $data;
//    }
//    protected function onBeforeUpdate(array $data){
//        if( $this->current_store_id ){
//            $data['data']['store_id']=$this->current_store_id;
//        }
//        return $data;
//    }
    
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->orderBy('updated_at','DESC');
        $this->permitWhere('r');
        $product_list= $this->get()->getResult();
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet('product_group_member_list');
        $ImageModel=model('ImageModel');
        
        foreach($product_list as $product){
            if($product){
                $product->member_of_groups=$GroupMemberModel->memberOfGroupsGet($product->product_id);
                $filter=[
                    'image_holder'=>'product',
                    'image_holder_id'=>$product->product_id,
                    'is_disabled'=>1,
                    'is_deleted'=>1,
                    'is_active'=>1,
                    'limit'=>30
                ];
                $product->images=$ImageModel->listGet($filter);
            }
        }
        return $product_list;
    }
    
    public function listCreate( $list ){
        /*
         * Should create importer based performant product importer
         */
//        
//        
//        
//        
//        if( !$list || !$list[0] ){
//            return 'list_create_error_empty';
//        }
//        $store_id=$list[0]['store_id'];
//        $StoreModel=model('StoreModel');
//        $store=$StoreModel->itemGet(['store_id'=>$store_id]);
//        if( !$store ){
//            return 'list_create_error_nostore';
//        }
//        $permission_granted=$StoreModel->permit($store_id,'w');
//        if( !$permission_granted ){
//            return 'list_create_error_forbidden';
//        }
//        $this->current_store_id=$store->store_id;
//        $this->current_store_owner=$store->owner_id;
//        
//        $this->allowedFields[]='owner_id';
//        return $this->insertBatch($list,true);
    }
    
    public function listUpdate( $list ){
        return false;
    }
    
    public function listDelete( $product_ids ){
        return false;
    }
    
    
    
    public function itemGet( $product_id ){
        $this->permitWhere('r');
        return $this->where('product_id',$product_id)->get()->getRow();
    }
    
    public function itemCreate( $product ){
        if( !$product ){
            return 'item_create_error_empty';
        }
        $store_id=$product['store_id'];
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet(['store_id'=>$store_id]);
        if( !$store ){
            return 'item_create_error_nostore';
        }
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'item_create_error_forbidden';
        }
        $this->allowedFields[]='owner_id';
        return $this->insert($product);
    }
    
    public function itemUpdate( $product ){
        if( !$product || !isset($product->product_id) ){
            return 'item_update_error_empty';
        }
        $target_store_id=$product->store_id??$this->itemGet($product->product_id)->store_id;
        $StoreModel=model('StoreModel');
        $permission_granted=$StoreModel->permit($target_store_id,'w');
        if( !$permission_granted ){
            return 'item_update_forbidden';
        }
        
        $this->permitWhere('w');
        $this->update($product->product_id,$product);
        return $this->db->affectedRows()?'item_update_ok':'item_update_forbidden';
    }
    
    public function itemUpdateGroup($product_id,$group_id,$is_joined){
        if( !$this->permit($product_id,'w') ){
            return 'item_update_forbidden';
        }
        $GroupModel=model('GroupModel');
        $GroupModel->tableSet('product_group_list');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'item_update_group_not_found';
        }
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet('product_group_member_list');
        return $GroupMemberModel->itemUpdate( $product_id, $group_id, $is_joined );
    }
    
    public function itemDelete( $product_id ){
        $target_store_id=$this->itemGet($product_id)->store_id;
        $StoreModel=model('StoreModel');
        if( !$StoreModel->permit($target_store_id,'w') || 
            !$this->permit($product_id, 'w') ){
            return 'item_delete_forbidden';
        }
        $this->delete($product_id);
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('product',$product_id);
        return $this->db->affectedRows()?'item_delete_ok':'item_delete_error';
    }
    
    
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemCreateImage( $data ){
        $data['is_disabled']=1;
        $data['owner_id']=session()->get('user_id');
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemCreate($data);
        }
        return 0;
    }

    public function itemUpdateImage( $data ){
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemUpdate($data);
        }
        return 0;
    }
    
    public function imageApprove( $image_id ){
        if( !sudo() ){
            return 'image_approve_forbidden';
        }
        
        $is_disabled=0;
        $ImageModel=model('ImageModel');
        $ok=$ImageModel->itemDisable( $image_id, $is_disabled );
        if( $ok ){
            return 'image_approve_ok';
        }
        return 'image_approve_error';
    }
    
    
    public function imageDelete( $image_id ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $product_id=$image->image_holder_id;
        if( !$this->permit($product_id,'w') ){
            return 'image_delete_forbidden';
        }
        $ok=$ImageModel->itemDelete( $image_id );
        if( $ok ){
            return 'image_delete_ok';
        }
        return 'image_delete_error';
    }
    
    public function imageOrder( $image_id, $dir ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $product_id=$image->image_holder_id;
        if( !$this->permit($product_id,'w') ){
            return 'image_order_forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'image_order_ok';
        }
        return 'image_order_error';
    }
}