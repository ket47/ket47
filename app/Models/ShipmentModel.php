<?php
namespace App\Models;
/**
 * 
 * This model should be merged to OrderModel! After experimenting with new features
 * 
 * 
 */
class ShipmentModel extends SecureModel{
    protected $table            = 'order_list';
    protected $primaryKey       = 'order_id';
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'order_store_id',
        'order_start_location_id',
        'order_finish_location_id',
        'order_description',
        ];
    protected $validationRules    = [
        'order_store_id'     => [
            'rules' =>'if_exist|permit_empty|numeric',
        ],
        'order_start_location_id'     => [
            'rules' =>'if_exist|permit_empty|numeric',
        ],
        'order_finish_location_id'     => [
            'rules' =>'if_exist|permit_empty|numeric',
        ],
        'order_description'     => [
            'rules' =>'if_exist|permit_empty',
        ],
    ];
    
    use OrderStageTrait;

    private $itemCache=[];
    private $order_data=null;

    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }
    
    private function itemUserRoleCalc(){
        $user_id=session()->get('user_id')??-1;
        if( sudo() ){
            $this->select("'admin' user_role");
        }
        else {
            $this->select("
                IF(order_list.owner_id=$user_id,'customer',
                IF(COALESCE(FIND_IN_SET('$user_id',order_list.order_store_admins),0),'supplier',
                IF(COALESCE(FIND_IN_SET('$user_id',order_list.order_courier_admins),0),'delivery',
                'other'))) user_role
                ");
        }
    }
    
    public function itemCacheClear(){
        $this->itemCache=[];
        $this->order_data=null;
        $this->resetQuery();
    }

    public function itemGet( int $order_id, $mode='all' ){
        if( $this->itemCache[$mode.$order_id]??0 ){
            return $this->itemCache[$mode.$order_id];
        }
        $this->select("{$this->table}.*,group_name stage_current_name,group_type stage_current");
        $this->join('order_group_list','order_group_id=group_id','left');
        $this->itemUserRoleCalc();
        $shipment=$this->find($order_id);
        if( !$shipment ){
            return 'notfound';
        }
        if( !($shipment->is_shipment??null) ){
            return 'nosupport';
        }
        if($shipment->order_data){
            $this->order_data=json_decode($shipment->order_data);
            unset($shipment->order_data);
        }
        if($mode=='basic'){
            $this->itemCache[$mode.$order_id]=$shipment;
            return $shipment;
        }

        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $ImageModel=model('ImageModel');
        //$EntryModel=model('EntryModel');
        $StoreModel=model('StoreModel');
        $UserModel=model('UserModel');

        $OrderGroupMemberModel->orderBy('order_group_member_list.created_at DESC,link_id DESC');
        $StoreModel->select('store_id,store_name,store_phone,store_minimal_order,store_tax_num,image_hash');
        $StoreModel->join('image_list','image_holder="store_avatar" AND image_holder_id=store_id','left');
        $UserModel->select('user_id,user_name,user_phone,user_email');
        $shipment->stage_next= $this->itemStageNextGet($order_id,$shipment->stage_current,$shipment->user_role);
        $shipment->stages=     $OrderGroupMemberModel->memberOfGroupsListGet($shipment->order_id);
        $shipment->images=     $ImageModel->listGet(['image_holder'=>'order','image_holder_id'=>$shipment->order_id,'is_active'=>1,'is_disabled'=>1,'is_deleted'=>1]);
        //$shipment->entries=    $EntryModel->listGet($order_id);
        $shipment->store=      $StoreModel->where('store_id',$shipment->order_store_id)->get()->getRow();

        $shipment->customer=   $UserModel->where('user_id',$shipment->owner_id)->get()->getRow();//permission issue for other parties
        $shipment->is_writable=$this->permit($order_id,'w');
        if( sudo() ){
            foreach($shipment->stages as $stage){
                $UserModel->select('user_id,user_name,user_phone');
                $stage->created_user=$UserModel->itemGet($stage->created_by,'basic');
            }
        }
        $this->itemCache[$mode.$order_id]=$shipment;
        return $shipment;
    }
  
    public function itemCreate( int $is_shopping=0 ){
        $user_id=session()->get('user_id');
        $new_shipment=[
            'owner_id'=>$user_id,
            'order_sum_delivery'=>0,
            'order_data'=>'{"is_shopping":0}',
            'is_shipment'=>1,
        ];
        $this->allowedFields[]='order_data';
        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='is_shipment';
        try{
            $order_id=$this->insert($new_shipment,true);
            $this->itemStageCreate( $order_id, 'customer_draft' );//move to controller
            return $order_id;
        } catch(\Exception $e){
            return $e->getMessage();
        }
    }

    public function itemUpdate( $shipment ){
        if( empty($shipment->order_id) ){
            return 'notfound';
        }
        $shipment->updated_by=session()->get('user_id');
        $this->update($shipment->order_id,$shipment);
        $update_result=$this->db->affectedRows()>0?'ok':'idle';
        if( $this->in_object($shipment,['owner_id','owner_ally_ids','order_courier_id']) ){//,'order_store_id'   not updating owners so store will not see order at this stage 
            $this->itemUpdateOwners($shipment->order_id);
        }
        return $update_result;
    }

    public function itemUpdateOwners( $order_id ){
        $this->select("(SELECT CONCAT(owner_id,',',owner_ally_ids) FROM store_list WHERE order_store_id=store_id) store_owners");
        $this->select("(SELECT CONCAT(owner_id,',',owner_ally_ids) FROM courier_list WHERE order_courier_id=courier_id) courier_owners");
        //$this->select("owner_id");,$all_owners->owner_id
        $all_owners=$this->getWhere(['order_id'=>$order_id])->getRow();
        $owners=array_map('trim',array_unique(explode(',',"0,$all_owners->store_owners,$all_owners->courier_owners"),SORT_NUMERIC));
        array_shift($owners);
        $owner_list=implode(',',$owners);

        $sql="
            UPDATE
                order_list ol
                    LEFT JOIN
                order_entry_list el USING(order_id)
            SET
                ol.owner_ally_ids='$owner_list',
                el.owner_ally_ids='$owner_list'
            WHERE
                ol.order_id='$order_id'";
        $this->query($sql);
    }

    public function itemDelete( $order_id ){
        $this->delete(['order_id'=>$order_id]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDataGet( int $order_id ){
        if( !$this->order_data ){
            $this->select('order_data');
            $shipment=$this->find($order_id);
            $this->order_data=json_decode($shipment->order_data);
        }
        return $this->order_data;
    }

    public function itemDataCreate( int $order_id, object $data_create ){
        foreach($data_create as $path=>$value){
            $data_create->{$path}=addslashes($value);
        }
        $this->order_data=null;//cache is unvalidated
        $this->set("order_data",json_encode($data_create));
        $this->allowedFields[]='order_data';
        $this->update($order_id);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemDataUpdate( int $order_id, object $data_update ){
        $path_value='';
        foreach($data_update as $path=>$value){
            $path_value.=','.$this->db->escape("$.$path").','.$this->db->escape($value);
        }
        $this->order_data=null;//cache is unvalidated
        $this->set("order_data","JSON_SET(`order_data`{$path_value})",false);
        $this->allowedFields[]='order_data';
        $this->update($order_id);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemDataDelete( int $order_id ){
        return $this->itemDataCreate($order_id,(object)[]);
    }

    public function tariffRuleListGet( $store_id, $tariff_order_mode ){//this function is messy OMG
        $this->permitWhere('r');
        $this->join('tariff_member_list','store_id');
        $this->join('tariff_list','tariff_id');
        $this->where('store_id',$store_id);
        $this->where('start_at<=NOW()');
        $this->where('finish_at>=NOW()');
        $this->where('tariff_list.is_disabled',0);
        $this->where('order_allow',1);
        $this->where('is_shipment',0);
        $this->select("tariff_id,card_allow,cash_allow,delivery_allow,delivery_cost");
        if( $tariff_order_mode=='delivery_by_courier_first' ){
            $this->orderBy("delivery_allow DESC");
        } else {
            $this->orderBy("delivery_allow ASC");
        }
        $this->orderBy("card_allow DESC");
        return $this->get()->getResult();
    }

    public function tariffRuleDeliveryCostGet( $store_id ){//this function is messy OMG
        $this->limit(1);
        $this->select('IF(delivery_cost>0,delivery_cost,store_delivery_cost) order_sum_delivery');
        $this->where('delivery_allow',1);
        $delivery_option=$this->tariffRuleListGet($store_id,'delivery_by_courier_first');
        if( isset($delivery_option[0]) ){
            return $delivery_option[0]->order_sum_delivery;
        }
        return 0;
    }

    private function in_object(object $obj,array $props){
        foreach($props as $prop){
            if( property_exists($obj,$prop) ){
                return true;
            }
        }
        return false;
    }

    public function listGet( $filter ){
        $this->filterMake($filter,false);
        $this->orderBy('shipment_list.created_at','DESC');
        return $this->get()->getResult();
    }
    
    public function listCountGet(){
        return 0;
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