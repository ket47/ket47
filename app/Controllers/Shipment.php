<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Shipment extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $ship_id=$this->request->getPost('ship_id');
        $ShipmentModel=model('ShipmentModel');
        $result=$ShipmentModel->itemGet($ship_id);
        if( is_object($result) ){
            return $this->respond($result);
        }
        if( $result=='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->fail($result);
    }
    
    public function itemCreate(){
        $is_shopping=$this->request->getPost('is_shopping');
        $ShipmentModel=model('ShipmentModel');
        $result=$ShipmentModel->itemCreate($is_shopping?1:0);
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        if( $result=='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }

    private function tariffRuleListGet( $customer_user_id ){
        /**
         * use customer_user_id to find if it is admin in store so can use credit
         */
        return [
            (object)[
                'tariff_id'=>'cdel_1',
                'tariff_name'=>'courier delivery without cargo',
                'card_allow'=>1,
                'card_fee'=>0,
                'cash_allow'=>0,
                'cash_fee'=>0,
                'credit_allow'=>0,
                'delivery_allow'=>1,
                'delivery_fee'=>30,
                'delivery_cost'=>200
            ]
        ];
        
    }

    private function itemDeliverySumGet( int $delivery_cost, int $delivery_fee, int $delivery_distance ){
        return round( $delivery_cost+$delivery_distance/1000*$delivery_fee );
    }
    /**
     * we should do calculations after all inputs are set
     */
    private function itemDeliveryOptionsGet( int $customer_user_id, int $delivery_distance ){
        $shipTariffRuleList=$this->tariffRuleListGet( $customer_user_id );
        $deliveryOptions=[];
        foreach($shipTariffRuleList as $tariff){
            $shipSumDelivery=$this->itemDeliverySumGet( $tariff->delivery_cost, $tariff->delivery_fee, $delivery_distance );
            $rule=[
                'tariff_id'=>$tariff->tariff_id,
                'ship_sum_delivery'=>$shipSumDelivery,
                'paymentByCard'=>$tariff->card_allow,
                'paymentByCash'=>$tariff->cash_allow,
                'paymentByCreditStore'=>$tariff->credit_allow
            ];
            $deliveryOptions[]=$rule;
        }
        $result='no_tariff';
        if( count($deliveryOptions)>0 ){
            $result=$deliveryOptions;
        }
        return $result;
    }
    
    /**
     * Here we checking for errors and ability to deliver
     * then calculating delivery sum for customer
     */
    public function itemCheckoutDataGet(){
        $ship_id = $this->request->getVar('ship_id');
        $ShipmentModel = model('ShipmentModel');
        $ship = $ShipmentModel->itemGet($ship_id,'basic');
        if ($ship === 'forbidden') {
            return $this->failForbidden();
        }
        if ($ship === 'notfound') {
            return $this->failNotFound();
        }
        if( !$ship->ship_start_location_id || !$ship->ship_finish_location_id){
            return $this->fail('no_input');
        }

        $LocationModel=model('LocationModel');
        $CourierModel=model('CourierModel');

        $bulkResponse=(object)[];
        $bulkResponse->ship=$ship;
        $lookForCourierAroundLocation=(object)[
            'location_id'=>$ship->ship_start_location_id
        ];
        $bulkResponse->deliveryIsReady=$CourierModel->deliveryIsReady($lookForCourierAroundLocation);
        if( !$bulkResponse->deliveryIsReady ){
            return $this->fail('no_courier');
        }

        $bulkResponse->Location_distanceGet=$LocationModel->distanceGet($ship->ship_start_location_id, $ship->ship_finish_location_id);//distance between start and finish
        if($bulkResponse->Location_distanceGet>getenv('delivery.radius')){
            return $this->fail('too_far');
        }

        $bulkResponse->Ship_deliveryOptions=$this->itemDeliveryOptionsGet(
            $ship->owner_id,
            $bulkResponse->Location_distanceGet
        );
        if( $bulkResponse->Ship_deliveryOptions=='no_tariff' ){
            return $this->fail($bulkResponse->Ship_deliveryOptions);
        }

        if( getenv('uniteller.recurrentAllow') ){
            $UserCardModel=model('UserCardModel');
            $bulkResponse->bankCard=$UserCardModel->itemMainGet();
        }
        return $this->respond($bulkResponse);
    }


















    public function listGet(){
        return false;
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
