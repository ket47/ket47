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
    
    
    public function itemGet( $order_id ){
        if( !$this->permit($order_id,'r') ){
            return 'forbidden';
        }
        $this->where('order_id',$order_id);
        $order = $this->get()->getRow();
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $ImageModel=model('ImageModel');
        $EntryModel=model('EntryModel');
        $StoreModel=model('StoreModel');
        $UserModel=model('UserModel');
        if($order){
            $OrderGroupMemberModel->orderBy('order_group_member_list.created_at DESC');
            
            
            $StoreModel->select('store_name,store_phone,store_email');
            $UserModel->select('user_name,user_phone');
            $order->is_writable=$this->permit($order_id,'w');
            $order->statuses=   $OrderGroupMemberModel->memberOfGroupsListGet($order->order_id);
            $order->images=     $ImageModel->listGet(['image_holder'=>'order','image_holder_id'=>$order->order_id]);
            $order->entries=    $EntryModel->listGet($order_id);
            $order->store=      $StoreModel->itemGet($order->order_store_id,'basic');
            $order->customer=   $UserModel->itemGet($order->order_customer_id);;
            $order->courier=    [];
            return $order;
        }
        return 'notfound';
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