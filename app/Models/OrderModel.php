<?php
namespace App\Models;
use CodeIgniter\Model;

class OrderModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'order_list';
    protected $primaryKey = 'order_id';
    protected $allowedFields = [
        'order_store_id',
        'order_shipping_fee',
        'order_tax',
        'owner_id'
    ];

    protected $useSoftDeletes = true;
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( int $store_id, array $entry_list=null ){
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $user_id=session()->get('user_id');
        
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($store_id);
        if( !$store ){
            return 'nostore';
        }
        $new_order=[
            'order_store_id'=>$store_id,
            'order_shipping_fee'=>$this->shippingFeeGet(),
            'order_tax'=>0,
            'owner_id'=>$user_id
        ];
        $this->insert($new_order);
        $order_id=$this->db->insertID();
        if($entry_list){
            $this->itemUpdate([
                'order_id'=>$order_id,
                'entry_list'=>$entry_list
            ]);
        }
        return $order_id;
    }
    
    private function shippingFeeGet(){
        $PrefModel=model('PrefModel');
        return $PrefModel->get('shipping_fee');
    }
    
    public function itemUpdate( $order ){
        if( !$this->permit($order->order_id,'w') ){
            return 'forbidden';
        }
        if($entry_list){
            $EntryModel->listUpdate($order->order_id,$entry_list);
        }
        $order_sum=$EntryModel->listCount($order->order_id);
        //$TransactionModel=model('TransactionModel');
        $order_updated=$order+$order_sum;
        $this->update($order->order_id,$order_updated);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet( $filter ){
        $this->filterMake($filter,false);
        $this->permitWhere('r');
        if($filter['order_store_id']??0){
            $this->where('order_store_id',$filter['order_store_id']);
        }
        $this->join('image_list',"image_holder='order' AND image_holder_id=order_id AND is_main=1",'left');
        $this->join('order_group_list ogl',"order_group_id=group_id",'left');
        $this->join('user_list ul',"user_id=order_list.owner_id");
        $this->select("{$this->table}.*,group_id,group_name,group_type,user_phone,user_name,image_hash");
        return $this->get()->getResult();
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}