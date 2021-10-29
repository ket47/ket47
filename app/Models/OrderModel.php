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
        'order_group_id',
        'order_sum_shipping',
        'order_sum_total',
        'order_sum_tax',
        'updated_by',
        'deleted_at',
        'order_description',
        'owner_id',
        'owner_ally_ids'
    ];

    protected $useSoftDeletes = true;
    
    
    public function itemGet( $order_id, $mode='all' ){
        $this->permitWhere('r');
        $this->select("{$this->table}.*,group_name stage_current_name,group_type stage_current");
        $this->where('order_id',$order_id);
        $this->join('order_group_list','order_group_id=group_id','left');
        $order = $this->get()->getRow();
        if( !$order ){
            echo 'notfound or ';
            return 'forbidden';
        }
        if($mode=='basic'){
            return $order;
        }
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $ImageModel=model('ImageModel');
        $EntryModel=model('EntryModel');
        $StoreModel=model('StoreModel');
        $UserModel=model('UserModel');
        $OrderGroupMemberModel->orderBy('order_group_member_list.created_at DESC');
        $StoreModel->select('store_id,store_name,store_phone');
        $UserModel->select('user_id,user_name,user_phone');
        $order->stage_next= $this->stageMap[$order->current_stage??''][0]??'';
        $order->stages=     $OrderGroupMemberModel->memberOfGroupsListGet($order->order_id);
        $order->images=     $ImageModel->listGet(['image_holder'=>'order','image_holder_id'=>$order->order_id]);
        $order->entries=    $EntryModel->listGet($order_id);
        
        $order->store=      $StoreModel->itemGet($order->order_store_id,'basic');
        $order->customer=   $UserModel->itemGet($order->order_customer_id,'basic');
        $order->courier=    $UserModel->itemGet($order->order_courier_id,'basic');
        $order->is_writable=$this->permit($order_id,'w');
        
        
        //$this->itemHistoryCreate( $order_id, 'customer_created' );
        if( sudo() ){
            foreach($order->stages as $stage){
                $stage->created_user=$UserModel->itemGet($stage->created_by,'basic');
            }
        }
        return $order;
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
        $this->itemHistoryCreate( $order_id, 'customer_created' );
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
        if( isset($order->entry_list) ){
            $EntryModel=model('EntryModel');
            $EntryModel->listUpdate($order->order_id,$order->entry_list);
        }
        //$this->itemHistoryCreate( $order->order_id, 'customer_created' );
        /*
         * IF owners are changed then update owner of entries
         */
        
        
        //$TransactionModel=model('TransactionModel');
        $order->updated_by=session()->get('user_id');
        $this->update($order->order_id,$order);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemCalculate( $order_id ){
        $EntryModel=model('EntryModel');
        $order=$EntryModel->listSumGet( $order_id );
        $order->order_id=$order_id;
        return $this->itemUpdate($order);
    }
    
//    public function itemUpdateGroup($order_id,$group_id,$is_joined){
//        if( !$this->permit($order_id,'w') ){
//            return 'forbidden';
//        }
//        $GroupModel=model('OrderGroupModel');
//        $target_group=$GroupModel->itemGet($group_id);
//        if( !$target_group ){
//            return 'not_found';
//        }
//        $OrderGroupMemberModel=model('OrderGroupMemberModel');
//        $ok=$OrderGroupMemberModel->itemUpdate( $order_id, $group_id, $is_joined );
//        if( $ok ){
//            return 'ok';
//        }
//        return 'error';
//    }
    
    public function itemDelete( $order_id ){
        if( !$this->permit($order_id,'w') ){
            return 'forbidden';
        }
        if( !$this->itemHistoryCreate( $order_id, 'customer_deleted' ) ){
            return 'wrong_stage';
        }
        $EntryModel=model('EntryModel');
        $EntryModel->listDeleteChildren( $order_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('order', $order_id);
        
        $this->delete($order_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUnDelete( $order_id ){
        if( !$this->permit($order_id,'w') ){
            return 'forbidden';
        }
        if( !$this->itemHistoryCreate( $order_id, 'customer_created' ) ){
            return 'wrong_stage';
        }
        $EntryModel=model('EntryModel');
        $EntryModel->listUnDeleteChildren( $order_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listUnDelete('order', $order_id);
        
        $this->update($order_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDisable( $order_id, $is_disabled ){
        if( !$this->permit($order_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['order_id'=>$order_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
//    public function itemHistoryGet($order_id){
//        $OrderGroupMemberModel=model('OrderGroupMemberModel');
//        $OrderGroupMemberModel->orderBy('created_at','DESC');
//        return $OrderGroupMemberModel->memberOfGroupsListGet($order_id);
//    }
//    
//    public function itemHistoryHas( $order_id, $group_type ){
//        $orderHistory=$this->itemHistoryGet($order_id);
//        foreach($orderHistory as $stage){
//            if( $stage->group_type==$group_type ){
//                return true;
//            }
//        }
//        return false;
//    }
    
    
    /*
     *  'current_stage'=>['posibble,next,stages']
     */
    
    protected $stageMap=[
        ''=>                    ['customer_created'],
        'customer_deleted'=>    ['customer_created'],
        'customer_created'=>    ['customer_deleted,customer_payed'],
        'customer_payed'=>      ['customer_confirmed'],
        'customer_confirmed'=>  ['supplier_start,supplier_reject,delivery_search'],
        
        'supplier_start'=>      ['supplier_correction,supplier_finish'],
        'supplier_finish'=>     ['delivery_start'],
        'delivery_search'=>     ['delivery_start,delivery_no_courier'],
        'delivery_start'=>      ['delivery_finish,delivery_no_address,delivery_rejected'],
        'delivery_finish'=>     ['customer_accepted,customer_partly_accepted,customer_rejected'],
        
        'customer_partly_accepted'=>['customer_refunded'],
        'customer_rejected'=>   ['supplier_reclaimed'],
        'delivery_no_address'=> ['supplier_reclaimed'],
        'delivery_rejected'=>   ['supplier_reclaimed'],
    ];
    
    public function itemHistoryCreate( $order_id, $stage ){
        $this->permitWhere('w');
        $order=$this->itemGet( $order_id, 'basic' );
        $next_stages=$this->stageMap[$order->group_type??''][0]??'';
        if( !str_contains($next_stages,$stage) ){
            return false;
        }
        $OrderGroupModel=model('OrderGroupModel');
        $group=$OrderGroupModel->select('group_id')->itemGet(null,$stage);
        if( !$group || !$group->group_id ){
            return false;
        }
        $this->update($order_id,['order_group_id'=>$group->group_id]);
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->joinGroup($order_id,$group->group_id);
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
    
    
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $data ){
        $data['is_disabled']=0;
        $data['owner_id']=session()->get('user_id');
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemCreate($data);
        }
        return 0;
    }
    
    public function imageDelete( $image_id ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $order_id=$image->image_holder_id;
        if( !$this->permit($order_id,'w') ){
            return 'forbidden';
        }
        $ImageModel->itemDelete( $image_id );
        $ok=$ImageModel->itemPurge( $image_id );
        if( $ok ){
            return 'ok';
        }
        return 'idle';
    }
    
}