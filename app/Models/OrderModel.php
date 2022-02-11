<?php
namespace App\Models;
use CodeIgniter\Model;

class OrderModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    use OrderStageTrait;
    
    protected $table      = 'order_list';
    protected $primaryKey = 'order_id';
    protected $allowedFields = [
        'order_store_id',
        'order_customer_id',
        'order_courier_user_id',
        'order_sum_shipping',
        'order_sum_total',
        'order_sum_tax',
        'order_description',
        'updated_by',
        'deleted_at'
    ];

    protected $useSoftDeletes = true;
    
    private function itemUserRoleCalc(){
        $user_id=session()->get('user_id');
        if( sudo() ){
            $this->select("'admin' user_role");
        }
        else {
            $this->select("
                IF(order_list.owner_id=$user_id,'customer',
                IF(order_list.order_courier_user_id=$user_id,'courier',
                IF('$user_id' IN (order_list.owner_ally_ids),'supplier',
                'other'))) user_role
                ");
        }
    }
    
    private function itemGetNextStages($current_stage,$user_role){
        $unfilterd_stage_next= $this->stageMap[$current_stage??'']??[];
        $stage_next=[];
        foreach($unfilterd_stage_next as $stage=>$config){
            if( $user_role=='admin' || strpos($stage, $user_role)===0 || strpos($stage, 'action')===0 ){
                $stage_next[$stage]=$config;
            }
        }
        return $stage_next;
    }
    
    public function itemCacheClear(){
        $this->itemCache=[];
        $this->resetQuery();
    }
    
    public $checkPermissionForItemGet=true;
    private $itemCache=[];
    public function itemGet( $order_id, $mode='all' ){
        if( $this->itemCache[$mode.$order_id]??0 ){
            return $this->itemCache[$mode.$order_id];
        }
        $this->permitWhere('r');
        $this->select("{$this->table}.*,group_name stage_current_name,group_type stage_current");
        $this->where('order_id',$order_id);
        $this->join('order_group_list','order_group_id=group_id','left');
        $this->itemUserRoleCalc();
        $order = $this->get()->getRow();
        if( !$order ){
            return 'forbidden';
        }
        if($mode=='basic'){
            $this->itemCache[$mode.$order_id]=$order;
            return $order;
        }
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $ImageModel=model('ImageModel');
        $EntryModel=model('EntryModel');
        $StoreModel=model('StoreModel');
        $UserModel=model('UserModel');
        $OrderGroupMemberModel->orderBy('order_group_member_list.created_at DESC,link_id DESC');
        $StoreModel->select('store_id,store_name,store_phone');
        $UserModel->select('user_id,user_name,user_phone');
        $order->stage_next=  $this->itemGetNextStages($order->stage_current,$order->user_role);
        $order->stages=     $OrderGroupMemberModel->memberOfGroupsListGet($order->order_id);
        $order->images=     $ImageModel->listGet(['image_holder'=>'order','image_holder_id'=>$order->order_id]);
        $order->entries=    $EntryModel->listGet($order_id);
        
        $order->store=      $StoreModel->itemGet($order->order_store_id,'basic');
        $order->customer=   $UserModel->itemGet($order->order_customer_id,'basic');
        $order->courier=    $UserModel->itemGet($order->order_courier_user_id,'basic');
        $order->is_writable=$this->permit($order_id,'w');
        
        if( sudo() ){
            foreach($order->stages as $stage){
                $UserModel->select('user_id,user_name,user_phone');
                $stage->created_user=$UserModel->itemGet($stage->created_by,'basic');
            }
        }
        $this->itemCache[$mode.$order_id]=$order;
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
        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='owner_ally_ids';
        $order_owner_allys=$store->owner_ally_ids?"$store->owner_id,$store->owner_ally_ids":"$store->owner_id";
        $new_order=[
            'order_store_id'=>$store_id,
            'order_customer_id'=>$user_id,
            'order_shipping_fee'=>$this->shippingFeeGet(),
            'order_tax'=>0,
            'owner_id'=>$user_id,
            'owner_ally_id'=>$order_owner_allys
        ];
        $this->insert($new_order);
        $order_id=$this->db->insertID();
        $this->itemStageCreate( $order_id, 'customer_created' );
        if($entry_list){
            $this->itemUpdate((object)[
                'order_id'=>$order_id,
                'entry_list'=>$entry_list
            ]);
        }
        return $order_id;
    }
    
    private function shippingFeeGet(){
        $PrefModel=model('PrefModel');
        return $PrefModel->itemGet('shipping_fee');
    }
    
    public function itemUpdate( $order ){
        if( !$this->permit($order->order_id,'w') ){
            return 'forbidden';
        }
        if( isset($order->entries) ){
            $EntryModel=model('EntryModel');
            $EntryModel->listUpdate($order->order_id,$order->entries);
        }
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
    
    public function itemPurge( $order_id ){
        $this->itemDelete($order_id);
        
        $EntryModel=model('EntryModel');
        $EntryModel->where(['order_id',$order_id])->delete(null,true);
        $this->delete($order_id,true);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( $order_id ){
        if( !$this->permit($order_id,'w') ){
            return 'forbidden';
        }
        $EntryModel=model('EntryModel');
        $EntryModel->listDeleteChildren( $order_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('order', $order_id);
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderGroupMemberModel->where('member_id',$order_id)->delete();
        
        $this->delete($order_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUnDelete( $order_id ){
        if( !$this->permit($order_id,'w') ){
            return 'forbidden';
        }//group member list
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
    
    public function listGet( $filter ){
        $this->filterMake($filter,false);
        $this->permitWhere('r');
        if($filter['order_store_id']??0){
            $this->where('order_store_id',$filter['order_store_id']);
        }
        if($filter['order_group_id']??0){
            $this->whereIn('order_group_id',$filter['order_group_id']);
        }
        if($filter['order_group_type']??0){
            $this->whereIn('order_group_type',$filter['order_group_type']);
        }
        if($filter['date_start']??0){
            $this->where('created_at>',$filter['date_start']);
        }
        if($filter['date_finish']??0){
            $this->where('created_at<',$filter['date_finish']);
        }
        $this->join('image_list',"image_holder='order' AND image_holder_id=order_id AND is_main=1",'left');
        $this->join('order_group_list ogl',"order_group_id=group_id",'left');
        $this->join('user_list ul',"user_id=order_list.owner_id");
        $this->select("{$this->table}.*,group_id,group_name stage_current_name,group_type stage_current,user_phone,user_name,image_hash");
        $this->itemUserRoleCalc();
        if( $filter['user_role']??0 ){
            $this->havingIn('user_role',$filter['user_role']);
        }
        return $this->get()->getResult();
    }

    public function listPreviewGet(){
        $customer=$this
                ->listGet(['limit'=>3,'user_role'=>'customer']);
        $courier=$this
                ->listGet(['limit'=>3,'user_role'=>'courier']);
        $supplier=$this
                ->listGet(['limit'=>3,'user_role'=>'supplier']);
        return [
            'customer'=>$customer,
            'courier'=>$courier,
            'supplier'=>$supplier
        ];
    }

    public function listCartGet(){
        $this->permitWhere('r');
        $this->join('order_group_list ogl',"order_group_id=group_id");
        $this->where('group_type','customer_created');
        $this->select('order_id');
        $cart_ids=$this->get()->getResult();
        $cart_list=[];
        foreach($cart_ids as $cart){
            $cart_list[]=$this->itemGet($cart->order_id);
        }
        return $cart_list;
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
    
    public function listPurge( $olderThan=APP_TRASHED_DAYS ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan days");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
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