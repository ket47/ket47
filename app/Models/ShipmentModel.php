<?php
namespace App\Models;
use CodeIgniter\Model;

class ShipmentModel extends SecureModel{
    protected $table            = 'shipment_list';
    protected $primaryKey       = 'ship_id';
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'ship_store_id',
        'ship_start_location_id',
        'ship_finish_location_id',
        'ship_sum_delivery',
        'ship_description',
        ];

    
    private function itemDeliverySumCalculate( int $store_id=null, int $start_location_id=null, int $finish_location_id=null ){
        $deliveryCourierCost=0;
        $deliveryCourierFee=0;#% from ship_sum_cargo
        $deliveryCourierDistanceTreshold=5;#km
        $deliveryCourierDistanceFee=50;#rubles/km
        //if store_id is set use store account and tariff system
        if( $store_id ){
            return 333;
        }
        return 444;
    }

    private $ship_data=null;
    public function itemGet( int $ship_id ){
        $shipment=$this->find($ship_id);
        if( !$shipment ){
            return 'notfound';
        }
        if($shipment->ship_data){
            $this->ship_data=json_decode($shipment->ship_data);
            unset($shipment->ship_data);
        }
        return $shipment;
    }
    
    public function itemCreate( int $is_shopping=0 ){
        $user_id=session()->get('user_id');
        $ship_sum_delivery=$this->itemDeliverySumCalculate();
        if(!$ship_sum_delivery){
            return 'no_sum_delivery';
        }
        $new_shipment=[
            'owner_id'=>$user_id,
            'is_shopping'=>$is_shopping,
            'ship_sum_delivery'=>$ship_sum_delivery??0,//IF null then there is no delivery
            'ship_data'=>'{}',
        ];
        $this->allowedFields[]='ship_data';
        $this->allowedFields[]='owner_id';
        try{
            return $this->insert($new_shipment,true);
        } catch(\Exception $e){
            return $e->getMessage();
        }
    }
    
    public function itemUpdate( $shipment ){
        if( empty($shipment->ship_id) ){
            return 'noid';
        }
        $this->update($shipment->ship_id,$shipment);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete( $ship_id ){
        $this->delete(['ship_id'=>$ship_id]);
        return $this->db->affectedRows()?'ok':'idle';
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