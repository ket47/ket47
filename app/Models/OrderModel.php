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
        'order_courier_id',
        'order_start_location_id',
        'order_finish_location_id',
        'order_sum_product',
        'order_sum_delivery',
        'order_sum_tax',
        'order_sum_promo',
        'order_sum_total',
        'order_description',
        'order_objection',
        'order_stock_status',
        'updated_by',
        'deleted_at'
    ];

    protected $useSoftDeletes = true;
    
    private function itemUserRoleCalc(){
        $user_id=session()->get('user_id');
        $courier_id=session()->get('courier_id');//must be inited at login
        if( sudo() ){
            $this->select("'admin' user_role");
        }
        else {
            $this->select("
                IF(order_list.owner_id=$user_id,'customer',
                IF(order_list.order_courier_id='$courier_id','delivery',
                IF(COALESCE(FIND_IN_SET('$user_id',order_list.owner_ally_ids),0),'supplier',
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
            return 'notfound';
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
        $StoreModel->select('store_id,store_name,store_phone,store_minimal_order');
        $UserModel->select('user_id,user_name,user_phone');
        $order->stage_next= $this->itemGetNextStages($order->stage_current,$order->user_role);
        $order->stages=     $OrderGroupMemberModel->memberOfGroupsListGet($order->order_id);
        $order->images=     $ImageModel->listGet(['image_holder'=>'order','image_holder_id'=>$order->order_id,'is_active'=>1,'is_disabled'=>1,'is_deleted'=>1]);
        $order->entries=    $EntryModel->listGet($order_id);
        
        $order->store=      $StoreModel->where('store_id',$order->order_store_id)->get()->getRow();
        $order->customer=   $UserModel->itemGet($order->owner_id,'basic');
        $order->courier=    [];//$CourierModel->itemGet($order->order_courier_id,'basic');
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
    
    public function itemCreate( int $store_id ){
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $user_id=session()->get('user_id');
        
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($store_id,'basic');
        if( !$store ){
            return 'nostore';
        }
        $this->allowedFields[]='owner_id';
        $new_order=[
            'order_store_id'=>$store_id,
            'order_sum_delivery'=>$this->deliveryFeeGet(),
            'order_tax'=>0,
            'owner_id'=>$user_id,
        ];
        $this->insert($new_order);
        $order_id=$this->db->insertID();
        $this->itemUpdateOwners($order_id);
        $this->itemStageCreate( $order_id, 'customer_cart' );
        return $order_id;
    }
    
    private function deliveryFeeGet(){
        $PrefModel=model('PrefModel');
        return $PrefModel->itemGet('delivery_fee','pref_value');
    }
    
    public function itemUpdate( $order ){
        if( !$this->permit($order->order_id,'w') ){
            return 'forbidden';
        }
        if( $this->in_object($order,['entries']) ){
            $EntryModel=model('EntryModel');
            $EntryModel->listUpdate($order->order_id,$order->entries);
        }
        $order->updated_by=session()->get('user_id');
        $this->update($order->order_id,$order);

        $update_result=$this->db->affectedRows()>0?'ok':'idle';
        if( $this->in_object($order,['owner_id','owner_ally_ids','order_store_id','order_courier_id']) ){
            $this->itemUpdateOwners($order->order_id);
        }
        return $update_result;
    }

    private function in_object(object $obj,array $props){
        foreach($props as $prop){
            if( property_exists($obj,$prop) ){
                return true;
            }
        }
        return false;
    }

    public function itemUpdateOwners( $order_id ){
        $this->select("(SELECT CONCAT(owner_id,',',owner_ally_ids) FROM store_list WHERE order_store_id=store_id) store_owners");
        $this->select("(SELECT CONCAT(owner_id,',',owner_ally_ids) FROM courier_list WHERE order_courier_id=courier_id) courier_owners");
        //$this->select("owner_id");,$all_owners->owner_id
        $all_owners=$this->getWhere(['order_id'=>$order_id])->getRow();
        $owners=array_unique(explode(',',"0,$all_owners->store_owners,$all_owners->courier_owners"),SORT_NUMERIC);
        array_shift($owners);
        $owner_list=implode(',',$owners);

        $sql="
            UPDATE
                order_list ol
                    LEFT JOIN
                order_entry_list el USING(order_id)
                    LEFT JOIN
                transaction_list tl ON holder_id=order_id AND holder='order'
            SET
                ol.owner_ally_ids='$owner_list',
                el.owner_ally_ids='$owner_list',
                tl.owner_ally_ids='$owner_list'
            WHERE
                ol.order_id='$order_id'";
        $this->query($sql);
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
        if($filter['order_group_type']??0){
            if($filter['order_group_type']=='active_only'){
                $this->where('ogl.group_type<>','customer_finish');
                $this->having("`ogl`.`group_type`='customer_cart' AND user_role='customer'OR `ogl`.`group_type`<>'customer_cart'");
            } else {
                $firstChar=substr($filter['order_group_type'],0,1);
                if( $firstChar=='!' ){
                    $this->where('ogl.group_type <>',substr($filter['order_group_type'],1));
                } else {
                    $this->where('ogl.group_type',$filter['order_group_type']);
                }
            }
        }
        $this->join('image_list',"image_holder='order' AND image_holder_id=order_id AND is_main=1",'left');
        $this->join('order_group_list ogl',"order_group_id=group_id",'left');
        $this->join('user_list ul',"user_id=order_list.owner_id");
        $this->join('store_list sl',"store_id=order_store_id",'left');

        $this->select("{$this->table}.*,group_id,group_name stage_current_name,group_type stage_current,user_phone,user_name,image_hash,store_name");
        $this->itemUserRoleCalc();
        if( $filter['user_role']??0 ){
            $this->havingIn('user_role',$filter['user_role']);
        }
        $this->orderBy('updated_at','DESC');
        return $this->get()->getResult();
    }

    public function listCountGet(){
        $this->permitWhere('r');
        $this->whereNotIn('ogl.group_type',['customer_cart','customer_finish']);
        $this->join('order_group_list ogl',"order_group_id=group_id",'left');
        $this->select('COUNT(*) count');
        return $this->get()->getRow('count');
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
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan hours");
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
            $ok=$ImageModel->itemCreate($data);
            return $ok;
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