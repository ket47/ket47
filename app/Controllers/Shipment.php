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
        $LocationModel=model('LocationModel');
        $ShipmentModel=model('ShipmentModel');
        $result=$ShipmentModel->itemGet($order_id);
        if( is_object($result) ){
            if( $result->order_start_location_id && $result->order_start_location_id ){
                $result->locationStart=$LocationModel->itemGet($result->order_start_location_id,'all');
                $result->locationFinish=$LocationModel->itemGet($result->order_finish_location_id,'all');
            }
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
        $data = $this->request->getJSON();
        if(!$data){
            return $this->fail('malformed_request');
        }
        $ShipmentModel = model('ShipmentModel');
        $result = $ShipmentModel->itemUpdate($data);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($ShipmentModel->errors()) {
            return $this->failValidationErrors($ShipmentModel->errors());
        }
        return $this->respondUpdated($result);
    }
    
    public function itemDelete(){
        return false;
    }

    /**
     * This function is for delivery cost estimation in shipping draft
     */
    public function itemDeliverySumEstimate(){
        $start_location_id=$this->request->getPost('start_location_id');
        $finish_location_id=$this->request->getPost('finish_location_id');
        if(!$start_location_id || !$finish_location_id){
            return $this->fail('noid');
        }
        $store_owner_id=session()->get('user_id');        
        $DeliveryScheduleModel=model('DeliveryScheduleModel');
        $routeStats=$DeliveryScheduleModel->routePlanGet($start_location_id, $finish_location_id);
        $deliveryOptions=$this->deliveryOptionsGet($routeStats->deliveryDistance, $store_owner_id);
        $defaultDeliveryOption=array_shift($deliveryOptions);
        if($defaultDeliveryOption['deliverySum']>0){
            $routeStats->cost=$defaultDeliveryOption['deliveryCost'];
            $routeStats->fee=$defaultDeliveryOption['deliveryFee'];
            $routeStats->sum=$defaultDeliveryOption['deliverySum'];
            return $this->respond($routeStats);
        }
        return $this->fail('cant_estimate');
    }

    private function itemDeliverySumGet( int $distance_m, object $tariff ){
        $distance_km=round($distance_m/1000,1);
        return $tariff->delivery_cost+round($tariff->delivery_fee*$distance_km);
    }

    private $customerOwnedStore=null;
    private function сreditBalanceGet( int $customer_user_id ){
        if( $this->customerOwnedStore==null ){
            $StoreModel=model('StoreModel');
            $this->customerOwnedStore=$StoreModel->itemOwnedGet($customer_user_id);
            if($this->customerOwnedStore){
                $this->customerOwnedStore['creditBalance']=$StoreModel->itemBalanceGet($this->customerOwnedStore['store_id']);
            }
        }
        return $this->customerOwnedStore;
    }

    private function deliveryOptionsGet( int $distance_m, int $store_owner_id=null ){
        $TariffModel=model('TariffModel');
        $tariffList=$TariffModel->where('is_shipment',1)->get()->getResult();
        $deliveryOptions=[];
        foreach( $tariffList as $tariff ){
            $orderSumDelivery=$this->itemDeliverySumGet($distance_m,$tariff);
            $rule=[
                'tariff_id'=>$tariff->tariff_id,
                'deliverySum'=>$orderSumDelivery,
                'deliveryCost'=>$tariff->delivery_cost,
                'deliveryFee'=>$tariff->delivery_fee,
                'paymentByCard'=>$tariff->card_allow,
                'paymentByCash'=>$tariff->cash_allow,
                'paymentByCreditStore'=>0
            ];
            $customerOwnedStore=null;
            if( $tariff->credit_allow ){
                $customerOwnedStore=$this->сreditBalanceGet( $store_owner_id );
            }
            if( $customerOwnedStore ){
                $rule['paymentByCreditStore']=1;
                $rule['storeId']=$customerOwnedStore['store_id']??0;
                $rule['storeCreditBalance']=$customerOwnedStore['creditBalance']??0;
                $rule['storeCreditName']=$customerOwnedStore['store_name']??'';
                $rule['storeCreditBalanceLow']= ($rule['storeCreditBalance']<$orderSumDelivery)?1:0;
            }
            $deliveryOptions[]=$rule;
        }
        return $deliveryOptions;
    }

    private function checkoutDataGet( $order ){
        $data=(object)[];
        $DeliveryScheduleModel=model('DeliveryScheduleModel');
        $data->routePlan=$DeliveryScheduleModel->routePlanGet($order->order_start_location_id,$order->order_finish_location_id);
        if( $data->routePlan->error??null ){
            return $data->routePlan->error;
        }
        $data->deliveryOptions=$this->deliveryOptionsGet( $data->routePlan->deliveryDistance, $order->owner_id );
        if( $data->deliveryOptions=='no_tariff' ){
            return 'no_tariff';
        }
        if( getenv('uniteller.recurrentAllow') ){
            $UserCardModel=model('UserCardModel');
            $data->bankCard=$UserCardModel->itemMainGet();
        }
        $data->validUntil=time()+5*60;//5 min
        return $data;
    }

    public function itemCheckoutDataGet(){
        $order_id = $this->request->getVar('order_id');////!!!!!!!!!!!!!!!!
        $with_arrival_range = $this->request->getVar('with_arrival_range');
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
        if( $with_arrival_range || $bulkResponse->routePlan->plan_mode ){//if delay is inqueue or schedule
            $plan_delivery_start=$bulkResponse->routePlan->plan_delivery_start;
            $DeliveryScheduleModel=model('DeliveryScheduleModel');
            $bulkResponse->arrivalRange=$DeliveryScheduleModel->itemDeliveryArrivalRangeGet($plan_delivery_start);
        }
        return $this->respond($bulkResponse);
    }

    public function itemCheckoutDataSet(){
        $checkoutSettings = $this->request->getJSON();
        $ShipmentModel = model('ShipmentModel');
        $order = $ShipmentModel->itemGet($checkoutSettings->order_id,'basic');
        if ( $order === 'forbidden' || !$checkoutSettings->order_id??0 || !$checkoutSettings->tariff_id??0 ) {
            return $this->failForbidden();
        }
        if ( $order === 'notfound' ) {
            return $this->failNotFound();
        }
        $order_data=$ShipmentModel->itemDataGet($checkoutSettings->order_id);
        if($order_data->payment_card_fixate_id??0){
            return $this->failResourceExists('payment_already_done');
        }
        /**
         * Try to use checkout cache data.If it is out dated create new
         */
        if($order_data->checkoutDataCache??null){
            $checkoutData=json_decode($order_data->checkoutDataCache??null);
        }
        if( !isset($checkoutData->validUntil) || $checkoutData->validUntil<time() ){
            $checkoutData=$this->checkoutDataGet($order);
            if( !is_object($checkoutData) ){
                return $this->fail($checkoutData);
            }    
        }
        /**
         * Here we controlling if user selected options are valid
         */
        $deliveryOption=null;
        foreach($checkoutData->deliveryOptions as $opt){
            $option=(object) $opt;
            if( $checkoutSettings?->tariff_id==$option->tariff_id ){
                $deliveryOption=$option;
            }
        }
        if( !$deliveryOption ){
            return $this->fail('no_tariff');
        }

        //CONSTRUCTING ORDER DATA
        // $order=(object)[
        //     'order_id'=>$checkoutSettings->order_id
        // ];
        $order_data=(object)[];

        //PAYMENT OPTIONS CHECK
        if( $checkoutSettings->paymentByCardRecurrent??0 && $deliveryOption->paymentByCardRecurrent??0 && getenv('uniteller.recurrentAllow') ){
            $order_data->payment_by_card_recurrent=1;
            $order_data->payment_by_card=1;
        } else
        if( $checkoutSettings->paymentByCard??0 && $deliveryOption->paymentByCard??0 ){
            $order_data->payment_by_card=1;
        } else
        if( $checkoutSettings->paymentByCash??0 && $deliveryOption->paymentByCash??0 ){
            $order_data->payment_by_cash=1;
        } else 
        if( $checkoutSettings->paymentByCreditStore??0 && $deliveryOption->paymentByCreditStore??0 ){
            if( $deliveryOption->deliverySum > $deliveryOption->storeCreditBalance ){
                return $this->fail('credit_balance_low');
            }
            $order_data->payment_by_credit_store=1;
            $order->order_store_id=$deliveryOption->storeId;
        } else {
            return $this->fail('no_payment');
        }
        $order_data->delivery_by_courier=1;
        $order->order_sum_delivery=$deliveryOption->deliverySum;
        $ShipmentModel->fieldUpdateAllow('order_sum_delivery');

        //ARRIVAL TIME CHECK && ESTIMATE TIMINGS
        $order_data->time_offset=$checkoutData->routePlan->time_offset;//offset sec
        $order_data->time_delivery=$checkoutData->routePlan->time_delivery;//duration sec
        $order_data->time_preparation=$checkoutData->routePlan->time_start_arrival;//duration sec
        $order_data->time_start_arrival=$checkoutData->routePlan->time_start_arrival;//duration sec

        $order_data->plan_delivery_start=$checkoutData->routePlan->plan_delivery_ready+$order_data->time_start_arrival;//time
        $order_data->plan_mode=$checkoutData->routePlan->plan_mode;//nodelay | await | schedule

        if( $checkoutSettings->deliveryStartScheduled ){
            $plan_delivery_start_scheduled=strtotime($checkoutSettings->deliveryStartScheduled);
            if( $plan_delivery_start_scheduled > $order_data->plan_delivery_start ){
                $order_data->plan_delivery_start=$plan_delivery_start_scheduled;
            }
            $order_data->plan_mode='schedule';
        }
        //SAVING CHECKOUT DATA
        $result=$ShipmentModel->itemDataCreate($checkoutSettings->order_id,$order_data);
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
        return $this->respondUpdated('ok');
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
