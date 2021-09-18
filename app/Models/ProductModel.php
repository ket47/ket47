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
    
    protected $beforeInsert = ['onBeforeInsert'];
    protected $beforeUpdate = ['onBeforeUpdate'];
    protected function onBeforeInsert(array $data){
        $data['data']['store_id']=$this->current_store_id;
        $data['data']['owner_id']=$this->current_store_owner;
        return $data;
    }
    protected function onBeforeUpdate(array $data){
        if( $this->current_store_id ){
            $data['data']['store_id']=$this->current_store_id;
        }
        return $data;
    }
    
    public function __construct(\CodeIgniter\Database\ConnectionInterface &$db = null, \CodeIgniter\Validation\ValidationInterface $validation = null) {
        parent::__construct($db, $validation);
        if( sudo() ){
            $adminAllowedFields=[
                'is_disabled',
                'deleted_at',
                'owner_id',
                'owner_ally_id'
                ];
            $this->allowedFields=array_merge($this->allowedFields,$adminAllowedFields);
        }
    }
    
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->orderBy('modified_at','DESC');
        $this->permitWhere('r');
        $product_list= $this->get()->getResult();
        $ProductGroupMemberModel=model('ProductGroupMemberModel');
        foreach($product_list as $product){
            if($product){
                $product->member_of_groups=$ProductGroupMemberModel->productmemberOfGroupsGet($product->product_id);
            }
        }
        return $product_list;
    }
    
    
    public function listCreate( $list ){
        if( !$list || !$list[0] ){
            return 'list_create_error_empty';
        }
        $store_id=$list[0]['store_id'];
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet(['store_id'=>$store_id]);
        if( !$store ){
            return 'list_create_error_nostore';
        }
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'list_create_error_forbidden';
        }
        $this->current_store_id=$store->store_id;
        $this->current_store_owner=$store->owner_id;
        return $this->insertBatch($list,true);
    }
    
    public function listUpdate( $list ){
        if( !$list || !$list[0] ){
            return 'list_update_error_empty';
        }
        if( isset($list[0]->store_id) ){
            /*
             * If user tries to update store_id then check store permission and set same store_id for all records
             */
            $this->current_store_id=$list[0]->store_id;
            $StoreModel=model('StoreModel');
            $permission_granted=$StoreModel->permit($this->current_store_id,'w');
            if( !$permission_granted ){
                return 'list_update_error_forbidden';
            }
        }
        $this->permitWhere('w');
        return $this->updateBatch($list,'product_id');
    }
    
    public function listDelete( $product_ids ){
        $this->permitWhere('w');
        return $this->delete($product_ids);
    }
    
    
    
    public function itemGet( $product_id ){
        $this->permitWhere('r');
        return $this->where('product_id',$product_id)->get()->getRow();
    }
    
    public function itemCreate( $product ){
        return $this->listCreate( [ $product ] );
    }
    
    public function itemUpdate( $product ){
        return $this->listUpdate([$product]);
    }
    
    public function itemDelete( $product_id ){
        return $this->listDelete([$product_id]);
    }
}