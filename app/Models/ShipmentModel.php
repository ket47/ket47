<?php
namespace App\Models;

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
    
    private $itemCache=[];
    private $order_data=null;

    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
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
        $shipment=$this->find($order_id);
        if( !$shipment ){
            return 'notfound';
        }
        if($shipment->order_data){
            $this->order_data=json_decode($shipment->order_data);
            unset($shipment->order_data);
        }
        if($mode=='basic'){
            $this->itemCache[$mode.$order_id]=$shipment;
            return $shipment;
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
            'is_shipping'=>1,
        ];
        $this->allowedFields[]='order_data';
        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='is_shipping';
        try{
            return $this->insert($new_shipment,true);
        } catch(\Exception $e){
            return $e->getMessage();
        }
    }
    
    public function itemUpdate( $shipment ){
        if( empty($shipment->order_id) ){
            return 'notfound';
        }
        $this->update($shipment->order_id,$shipment);
        return $this->db->affectedRows()>0?'ok':'idle';
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
            $data_create->{$path}=$this->db->escape($value);
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
        $this->where('is_shipping',0);
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

    public function listGet( $filter ){
        $this->filterMake($filter,false);
        $this->orderBy('shipment_list.created_at','DESC');
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