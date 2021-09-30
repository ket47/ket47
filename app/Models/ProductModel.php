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
        'is_produced',
        'deleted_at'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_id'         => 'required|numeric',
        'product_name'     => 'required|min_length[3]',
        'product_price'    => 'required|numeric'
    ];
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $product_id ){
        $this->permitWhere('r');
        return $this->where('product_id',$product_id)->get()->getRow();
    }
    
    public function itemCreate( $product ){
        if( !$product ){
            return 'error_empty';
        }
        $store_id=$product['store_id'];
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet(['store_id'=>$store_id]);
        if( !$store ){
            return 'nostore';
        }
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        $this->allowedFields[]='owner_id';
        return $this->insert($product);
    }
    
    public function itemUpdate( $product ){
        if( !$product || !isset($product->product_id) ){
            return 'error_empty';
        }
        $target_store_id=$product->store_id??$this->itemGet($product->product_id)->store_id;
        $StoreModel=model('StoreModel');
        $permission_granted=$StoreModel->permit($target_store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        
        $this->permitWhere('w');
        $this->update($product->product_id,$product);
        return $this->db->affectedRows()?'ok':'forbidden';
    }
    
    public function itemUpdateGroup($product_id,$group_id,$is_joined){
        if( !$this->permit($product_id,'w') ){
            return 'forbidden';
        }
        $GroupModel=model('GroupModel');
        $GroupModel->tableSet('product_group_list');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'not_found';
        }
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet('product_group_member_list');
        $ok=$GroupMemberModel->itemUpdate( $product_id, $group_id, $is_joined );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    
    public function itemDelete( $product_id ){
        $target_store_id=$this->itemGet($product_id)->store_id;
        $StoreModel=model('StoreModel');
        if( !$StoreModel->permit($target_store_id,'w') || 
            !$this->permit($product_id, 'w') ){
            return 'forbidden';
        }
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('product',$product_id);
        $this->delete($product_id);
        return $this->db->affectedRows()?'ok':'error';
    }
    
    public function itemDisable( $product_id, $is_disabled ){
        if( !$this->permit($product_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['product_id'=>$product_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'error';
    }
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter=null ){
        $user_id=session()->get('user_id');
        
        $this->filterMake( $filter );
        $this->orderBy('updated_at','DESC');
        $this->permitWhere('r');
        //$this->select("*,IF($user_id=owner_id,1,0) is_owner");
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
                    'is_deleted'=>0,
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
    }
    
    public function listUpdate( $list ){
        return false;
    }
    
    public function listDelete( $product_ids ){
        return false;
    }
    
    public function listDeleteChildren( $store_id ){
        $StoreModel=model('StoreModel');
        if( !$StoreModel->permit($store_id,'w') ){
            return 'forbidden';
        }
        $this->where('deleted_at IS NOT NULL OR is_disabled=1');
        $this->where('store_id',$store_id);
        $this->select('GROUP_CONCAT(product_id) product_ids');
        $trashed_product_ids=$this->get()->getRow('product_ids');
        
        $this->update($trashed_product_ids,['deleted_at','2000-01-01 00:00:00']);
        $this->where('store_id',$store_id);
        $this->delete();
    }
    
    public function listPurge( $olderThan=7 ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan days");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
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
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');
        $ok=$ImageModel->itemDisable( $image_id, $is_disabled );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    
    public function imageDelete( $image_id ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $product_id=$image->image_holder_id;
        if( !$this->permit($product_id,'w') ){
            return 'forbidden';
        }
        $ok=$ImageModel->itemPurge( $image_id );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    
    public function imageOrder( $image_id, $dir ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $product_id=$image->image_holder_id;
        if( !$this->permit($product_id,'w') ){
            return 'forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
}