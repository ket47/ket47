<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Shipment extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet( $order_id=null ){
        if( !(session()->get('user_id')>-1) ){
            return $this->failUnauthorized('unauthorized');
        }
        if( !$order_id ){
            $order_id=$this->request->getPost('order_id');
        }
        $ShipmentModel=model('ShipmentModel');
        $result=$ShipmentModel->itemGet($order_id);
        if( is_object($result) ){
            return $this->respond($result);
        }
        if( $result=='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->fail($result);
    }
    public function itemSync() {
        $data = $this->request->getJSON();
        if(!$data){
            return $this->fail('malformed_request');
        }
        if( session()->get('user_id')<=0 && session()->get('user_id')!=-100 ){//system user
            return $this->failUnauthorized('unauthorized');
        }
        $ShipmentModel = model('ShipmentModel');
        $order_id_exists=false;
        if( ($data->order_id??-1)>0 ){
            $order_id_exists=$ShipmentModel->select('order_id')->find($data->order_id);
        }
        $ShipmentModel->transBegin();
        if( !$order_id_exists ){
            $result=$ShipmentModel->itemCreate($data->is_shopping);
            if ($result === 'forbidden') {
                $ShipmentModel->transRollback();
                return $this->failForbidden($result);
            }
            if (!is_numeric($result)) {
                $ShipmentModel->transRollback();
                return $this->fail($result);
            }
            $data->order_id=$result;
        }
        $result = $ShipmentModel->itemUpdate($data);
        if ($result === 'forbidden') {
            $ShipmentModel->transRollback();
            return $this->failForbidden($result);
        }
        if ($result === 'validation_error') {
            $ShipmentModel->transRollback();
            return $this->fail($result);
        }
        if ($ShipmentModel->errors()) {
            $ShipmentModel->transRollback();
            return $this->failValidationErrors($ShipmentModel->errors());
        }
        $ShipmentModel->transCommit();
        return $this->respond($data->order_id);
    }

    public function itemCreate(){
        $data = $this->request->getJSON();
        if(!$data){
            return $this->fail('malformed_request');
        }
        if( session()->get('user_id')<=0 && session()->get('user_id')!=-100 ){//system user
            return $this->failUnauthorized('unauthorized');
        }
        $ShipmentModel = model('ShipmentModel');
        $result=$ShipmentModel->itemCreate($data);
        if (is_numeric($result)) {
            return $this->respondCreated($result);
        }
        if ($result === 'forbidden') {
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

    public function itemDeliverySumEstimate(){
        $start_location_id=$this->request->getPost('start_location_id');
        $finish_location_id=$this->request->getPost('finish_location_id');
        if(!$start_location_id || !$finish_location_id){
            return $this->fail('noid');
        }
        $store_owner_id=session()->get('user_id');
        $routeStats=$this->itemRouteStatsGet($start_location_id, $finish_location_id);
        $deliveryOptions=$this->itemDeliveryOptionsGet($routeStats, $store_owner_id);
        $defaultDeliveryOption=array_shift($deliveryOptions);
        if($defaultDeliveryOption->deliverySum>0){
            return $this->respond($defaultDeliveryOption->deliverySum);
        }
        return $this->fail('cant_estimate');
    }

    private function itemRouteStatsGet(int $start_location_id, int $finish_location_id){
        $LocationModel=model('LocationModel');
        $default_location_id=$LocationModel->where('location_holder','default_location')->get()->getRow('location_id');

        $maximum_distance=getenv('delivery.radius');
        $result=(object)[
            'max_distance'=>$maximum_distance
        ];
        $start_center_distance=$LocationModel->distanceGet($default_location_id,$start_location_id);
        if($start_center_distance>$maximum_distance){
            $result->error='start_center_toofar';
            $result->distance=round($start_center_distance/1000,1);
            return $result;
        }
        $finish_center_distance=$LocationModel->distanceGet($default_location_id,$finish_location_id);
        if($finish_center_distance>$maximum_distance){
            $result->error='finish_center_toofar';
            $result->distance=round($finish_center_distance/1000,1);
            return $result;
        }
        $start_finish_distance=$LocationModel->distanceGet($start_location_id,$finish_location_id);
        if($start_finish_distance>$maximum_distance){
            $result->error='start_finish_toofar';
            $result->distance=round($start_finish_distance/1000,1);
            return $result;
        }
        $result->distance=round($start_finish_distance/1000,1);
        return $result;
    }

    private function itemDeliverySumGet( float $distance, object $tariff ){
        return $tariff->delivery_cost+round($tariff->delivery_fee*$distance);
    }

    private $customerOwnedStore=null;
    private function itemCreditBalanceGet( int $customer_user_id ){
        if( $this->customerOwnedStore==null ){
            $StoreModel=model('StoreModel');
            $this->customerOwnedStore=$StoreModel->itemOwnedGet($customer_user_id);
            if($this->customerOwnedStore){
                $this->customerOwnedStore['creditBalance']=$StoreModel->itemBalanceGet($this->customerOwnedStore['store_id']);
            }
        }
        return $this->customerOwnedStore;
    }

    private function itemDeliveryOptionsGet( object $routeStats, int $store_owner_id=null ){
        $TariffModel=model('TariffModel');
        $tariffList=$TariffModel->where('is_shipping',1)->get()->getResult();
        $deliveryOptions=[];
        foreach( $tariffList as $tariff ){
            $orderSumDelivery=$this->itemDeliverySumGet($routeStats->distance,$tariff);
            $rule=[
                'tariff_id'=>$tariff->tariff_id,
                'deliverySum'=>$orderSumDelivery,
                'deliveryCost'=>$tariff->delivery_cost,
                'deliveryFee'=>$tariff->delivery_fee,
                'paymentByCard'=>$tariff->card_allow,
                'paymentByCash'=>$tariff->cash_allow,
                'paymentByCreditStore'=>$tariff->credit_allow
            ];
            if( $tariff->credit_allow ){
                $customerOwnedStore=$this->itemCreditBalanceGet( $store_owner_id );
                if($customerOwnedStore['creditBalance']<$orderSumDelivery){
                    $rule['storeCreditBalanceLow']=1;
                }
                $rule['storeId']=$customerOwnedStore['store_id'];
                $rule['storeCreditBalance']=$customerOwnedStore['creditBalance'];
                $rule['storeCreditName']=$customerOwnedStore['store_name'];
            }
            $deliveryOptions[]=$rule;
        }
        return $deliveryOptions;
    }

    private function checkoutDataGet( $order ){
        $data=(object)[];
        $data->Shipment_routeStats=$this->itemRouteStatsGet($order->order_start_location_id,$order->order_finish_location_id);
        if( $data->Shipment_routeStats->error??null ){
            return $data->Shipment_routeStats->error;
        }
        $data->Shipment_deliveryOptions=$this->itemDeliveryOptionsGet( $data->Shipment_routeStats, $order->owner_id );
        if( $data->Shipment_deliveryOptions=='no_tariff' ){
            return 'no_tariff';
        }
        $DeliveryScheduleModel=model('DeliveryScheduleModel');
        $data->deliveryScheduleStats=$DeliveryScheduleModel->itemDeliveryArriveRangeGet();
        if( getenv('uniteller.recurrentAllow') ){
            $UserCardModel=model('UserCardModel');
            $data->bankCard=$UserCardModel->itemMainGet();
        }
        $data->validUntil=time()+5*60;//5 min
        return $data;
    }

    public function itemCheckoutDataGet(){
        $order_id = $this->request->getPost('order_id');
        if(!$order_id){
            return $this->fail('noid');
        }
        $ShipmentModel = model('ShipmentModel');
        $order = $ShipmentModel->itemGet($order_id,'basic');
        if ($order === 'forbidden') {
            return $this->failForbidden('forbidden');
        }
        if ($order === 'notfound') {
            return $this->failNotFound('notfound');
        }
        $bulkResponse=$this->checkoutDataGet($order);
        if( !is_object($bulkResponse) ){
            return $this->fail($bulkResponse);
        }
        $ShipmentModel->itemDataUpdate($order_id,(object)['checkoutDataCache'=>json_encode($bulkResponse)]);
        $bulkResponse->order=$order;
        return $this->respond($bulkResponse);
    }

    public function itemCheckoutDataSet(){
        $checkoutData = $this->request->getJSON();
        $ShipmentModel = model('ShipmentModel');
        $order = $ShipmentModel->itemGet($checkoutData->order_id,'basic');
        if ($order === 'forbidden' || !$checkoutData->order_id??0 || !$checkoutData->tariff_id??0 ) {
            return $this->failForbidden();
        }
        if ($order === 'notfound') {
            return $this->failNotFound();
        }
        $order_data=$ShipmentModel->itemDataGet($checkoutData->order_id);
        if($order_data->payment_card_fixate_id??0){
            return $this->failResourceExists('payment_already_done');
        }
        /**
         * Try to use checkout cache data.If it is out dated create new
         */
        $checkoutControlData=json_decode($order_data->checkoutDataCache??null);
        if( !isset($checkoutControlData->validUntil) || $checkoutControlData->validUntil<time() ){
            $checkoutControlData=$this->checkoutDataGet($order);
            if( !is_object($checkoutControlData) ){
                return $this->fail($checkoutControlData);
            }    
        }
        /**
         * Here we controlling if user selected options are valid
         */
        $deliveryOption=null;
        foreach($checkoutControlData->Shipment_deliveryOptions as $opt){
            $option=(object) $opt;
            if( $checkoutData?->tariff_id==$option->tariff_id ){
                $deliveryOption=$option;
            }
        }
        if( !$deliveryOption ){
            return $this->fail('no_tariff');
        }

        //CONSTRUCTING ORDER DATA
        $order=(object)[
            'order_id'=>$checkoutData->order_id
        ];
        $order_data=(object)[];

        //PAYMENT OPTIONS CHECK
        if( $checkoutData->paymentByCardRecurrent??0 && $deliveryOption->paymentByCardRecurrent??0 && getenv('uniteller.recurrentAllow') ){
            $order_data->payment_by_card_recurrent=1;
            $order_data->payment_by_card=1;
        } else
        if( $checkoutData->paymentByCard??0 && $deliveryOption->paymentByCard??0 ){
            $order_data->payment_by_card=1;
        } else
        if( $checkoutData->paymentByCash??0 && $deliveryOption->paymentByCash??0 ){
            $order_data->payment_by_cash=1;
        } else 
        if( $checkoutData->paymentByCreditStore??0 && $deliveryOption->paymentByCreditStore??0 ){
            if( $deliveryOption->deliverySum>$deliveryOption->storeCreditBalance ){
                return $this->fail('credit_balance_low');
            }
            $order_data->payment_by_credit_store=1;
            $order->order_store_id=$deliveryOption->storeId;
        } else {
            return $this->fail('no_payment');
        }
        $order->order_sum_delivery=$deliveryOption->deliverySum;
        $ShipmentModel->fieldUpdateAllow('order_sum_delivery');

        //ARRIVAL TIME CHECK && ESTIMATE TIMINGS
        $deliveryArrivalNearest=$checkoutControlData->deliveryScheduleStats->deliveryArrivalNearest;
        $deliveryArrivalSelected=$checkoutData->deliveryArrivalDatetime;
        if($deliveryArrivalSelected<$deliveryArrivalNearest){
            $deliveryArrivalSelected=$deliveryArrivalNearest;
            //return $this->fail('not_in_schedule');
        }
        $order_data->timeCustomerStart='';//when this order should start and supplier if eny get notification 
        $order_data->timeDeliverySearch='';//when order should get delivery_search status
        $order_data->timeDeliveryStart=$deliveryArrivalSelected;//target time to pickup shipment
        $order_data->timeDeliveryFinish=$deliveryArrivalSelected;//target time to dropdown shipment

        //SAVING CHECKOUT DATA
        $result=$ShipmentModel->itemDataCreate($checkoutData->order_id,$order_data);
        if( $result != 'ok' ){
            return $this->respondNoContent($result);
        }
        $result = $ShipmentModel->itemUpdate($order);
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        if ($result != 'ok') {
            return $this->respondNoContent($result);
        }
        return $this->respondUpdated($result);
    }



    public function itemStageCreate() {
        $order_id = $this->request->getPost('order_id');
        $new_stage = $this->request->getPost('new_stage');
        return $this->itemStage($order_id, $new_stage);
    }

    private function itemStage($order_id, $stage) {
        $ShipmentModel = model('ShipmentModel');
        $result = $ShipmentModel->itemStageCreate($order_id, $stage);
        if ($result === 'ok') {
            return $this->respondUpdated($result);
        }
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        return $this->fail($result);
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
