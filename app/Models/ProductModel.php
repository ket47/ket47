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
        'product_description',
        'product_weight',
        'product_price',
        'is_disabled',
        'owner_id',
        'owner_ally_id'
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
        $data['data']['store_id']=$this->current_store_id;
        return $data;
    }
    
    
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->orderBy('modified_at','DESC');
        $this->permitWhere('r');
        return $this->get()->getResult();
    }
    
    
    public function listCreate( $store_id, $list ){
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet(['store_id'=>$store_id]);
        if( !$store ){
            return false;
        }
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return false;
        }
        $this->current_store_id=$store->store_id;
        $this->current_store_owner=$store->owner_id;
        return $this->insertBatch($list,true);
    }
    
    public function listUpdate( $store_id, $list ){
        $this->current_store_id=$store_id;
        $StoreModel=model('StoreModel');
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return false;
        }
        return $this->updateBatch($list,true);
    }
    
    public function listDelete( $product_ids ){
        $this->delete($product_ids);
    }
}