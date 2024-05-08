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
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemGet($order_id);
        if( is_object($result) ){
            $order_data=$OrderModel->itemDataGet($order_id);
            $result->locationStart=$order_data->location_start??[];
            $result->locationFinish=$order_data->location_finish??[];

            if( $order_data->delivery_by_courier??0 ){
                $DeliveryJobModel=model('DeliveryJobModel');
                $result->deliveryJob=$DeliveryJobModel->select('start_plan,stage')->itemGet(null,$order_id);
                if($result->deliveryJob){
                    $result->deliveryJob->start_plan_date=date("H:i, d.m",$result->deliveryJob->start_plan??time());
                }
            }
            if( $order_data->finish_plan_scheduled??0 ){
                $result->finish_plan_scheduled=date("H:i, d.m.Y",$order_data->finish_plan_scheduled);
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
        $OrderModel = model('OrderModel');
        $order_id_exists=false;
        if( ($data->order_id??-1)>0 ){
            $order_id_exists=$OrderModel->select('order_id')->find($data->order_id);
        }
        $OrderModel->transBegin();
        if( !$order_id_exists ){
            $result=$OrderModel->itemCreate(null,1);
            if ($result === 'forbidden') {
                $OrderModel->transRollback();
                return $this->failForbidden($result);
            }
            if (!is_numeric($result)) {
                $OrderModel->transRollback();
                return $this->fail($result);
            }
            $data->order_id=$result;
            $OrderModel->itemStageCreate( $data->order_id, 'customer_cart' );
            $OrderModel->itemStageCreate( $data->order_id, 'customer_confirmed', $data );
        }
        $result = $OrderModel->itemUpdate($data);
        if ($result === 'forbidden') {
            $OrderModel->transRollback();
            return $this->failForbidden($result);
        }
        if ($result === 'validation_error') {
            $OrderModel->transRollback();
            return $this->fail($result);
        }
        if ($OrderModel->errors()) {
            $OrderModel->transRollback();
            return $this->failValidationErrors($OrderModel->errors());
        }
        $OrderModel->transCommit();
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
        $OrderModel = model('OrderModel');
        $result=$OrderModel->itemCreate($data);
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
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemUpdate($data);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($OrderModel->errors()) {
            return $this->failValidationErrors($OrderModel->errors());
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
        $DeliveryJobModel=model('DeliveryJobModel');
        $routeStats=$DeliveryJobModel->routePlanGet($start_location_id, $finish_location_id);
        $deliveryOptions=$this->deliveryOptionsGet($routeStats->deliveryDistance, $store_owner_id);
        $defaultDeliveryOption=array_shift($deliveryOptions);
        if( ($defaultDeliveryOption['deliverySum']??0) >0 ){
            $routeStats->cost=(int) $defaultDeliveryOption['deliveryCost'];
            $routeStats->fee=(int) $defaultDeliveryOption['deliveryFee'];
            $routeStats->sum=(int) $defaultDeliveryOption['deliverySum'];
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

    private function itemDeliveryHeavyGet(){
        $PrefModel=model('PrefModel');
        $delivery_heavy_level=$PrefModel->itemGet('delivery_heavy_level','pref_value');
        if($delivery_heavy_level){
            $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level}",'pref_value');
            $delivery_heavy_bonus=$PrefModel->itemGet("delivery_heavy_bonus_{$delivery_heavy_level}",'pref_value');

            if( $delivery_heavy_cost && $delivery_heavy_bonus ){
                return (object)[
                    'cost'=>$delivery_heavy_cost,
                    'bonus'=>$delivery_heavy_bonus
                ];
            }
        }
        return (object)[
            'cost'=>0,
            'bonus'=>0
        ];
    }

    private function deliveryOptionsGet( int $distance_m, int $store_owner_id=null ){
        $deliveryHeavyModifier=$this->itemDeliveryHeavyGet();
        $TariffModel=model('TariffModel');
        $tariffList=$TariffModel->where('is_shipment',1)->get()->getResult();
        $deliveryOptions=[];
        foreach( $tariffList as $tariff ){
            $tariff->delivery_cost+=$deliveryHeavyModifier->cost;
            $orderSumDelivery=$this->itemDeliverySumGet($distance_m,$tariff);
            $rule=[
                'tariff_id'=>$tariff->tariff_id,
                'deliverySum'=>$orderSumDelivery,
                'deliveryCost'=>$tariff->delivery_cost,
                'deliveryFee'=>$tariff->delivery_fee,
                'deliveryHeavyCost'=>$deliveryHeavyModifier->cost,
                'deliveryHeavyBonus'=>$deliveryHeavyModifier->bonus,
                'paymentByCard'=>$tariff->card_allow,
                'paymentByCash'=>$tariff->cash_allow,
                'paymentByCreditStore'=>0
            ];
            $customerOwnedStore=null;
            if( $tariff->credit_allow ){
                $customerOwnedStore=$this->сreditBalanceGet( $store_owner_id );
            }
            if( $customerOwnedStore ){
                $store_owners_all=ownersAll((object)$customerOwnedStore);
                $rule['paymentByCreditStore']=1;
                $rule['storeId']=$customerOwnedStore['store_id']??0;
                $rule['storeAdmins']=$store_owners_all;
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
        $DeliveryJobModel=model('DeliveryJobModel');
        $data->routePlan=$DeliveryJobModel->routePlanGet($order->order_start_location_id,$order->order_finish_location_id);
        if( $data->routePlan->error??null ){
            return $data->routePlan->error;
        }
        $data->deliveryOptions=$this->deliveryOptionsGet( $data->routePlan->deliveryDistance, $order->owner_id );
        if( $data->deliveryOptions=='no_tariff' ){
            return 'no_tariff';
        }
        if( getenv('rncb.recurrentAllow') ){
            $UserCardModel=model('UserCardModel');
            $data->bankCard=$UserCardModel->itemMainGet($order->owner_id);
        }
        $data->validUntil=time()+10*60;//10 min
        return $data;
    }

    public function itemCheckoutDataGet(){
        $order_id = $this->request->getVar('order_id');
        $with_arrival_range = $this->request->getVar('with_arrival_range');
        if(!$order_id){
            return $this->fail('noid');
        }
        $OrderModel = model('OrderModel');
        $order = $OrderModel->itemGet($order_id,'basic');
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
        if($bulkResponse->routePlan->start_plan!='nocourier'){
            /**
             * If right now there is no couriers, don't cache checkoutDataCache
             */
            $OrderModel->itemDataUpdate($order_id,(object)['checkoutDataCache'=>$bulkResponse]);
        }
        $bulkResponse->order=$order;
        if( $with_arrival_range || $bulkResponse->routePlan->start_plan_mode=='scheduled' ){
            /**
             * For shipment it is more logical to use start_plan instead of finish_plan
             */
            $start_plan=$bulkResponse->routePlan->start_plan;//+$bulkResponse->routePlan->finish_arrival;
            $DeliveryJobModel=model('DeliveryJobModel');
            $bulkResponse->finishPlanSchedule=$DeliveryJobModel->planScheduleGet($start_plan);
        }
        return $this->respond($bulkResponse);
    }

    public function itemCheckoutDataSet(){
        $checkoutSettings = $this->request->getJSON();
        $OrderModel = model('OrderModel');
        $order = $OrderModel->itemGet($checkoutSettings->order_id,'basic');
        if ( $order === 'forbidden' || !$checkoutSettings->order_id??0 || !$checkoutSettings->tariff_id??0 ) {
            return $this->failForbidden();
        }
        if ( $order === 'notfound' ) {
            return $this->failNotFound();
        }
        $order_data=$OrderModel->itemDataGet($checkoutSettings->order_id);
        if($order_data->payment_card_fixate_id??0){
            /**
             * Checking if payment is done. Add stage customer_payed_card
             */
            if($order_data->payment_card_acq_rncb??0){
                $Acquirer=new \App\Libraries\AcquirerRncb();
            } else {
                $Acquirer=\Config\Services::acquirer();
            }
            $result=$Acquirer->statusCheck( $checkoutSettings->order_id );
            if( $result!='order_not_payed' ){
                return 'already_payed';
            }
            return $this->failResourceExists('payment_already_done');
        }
        /**
         * Try to use checkout cache data. If it is outdated create new
         */
        if($order_data->checkoutDataCache??null){
            $checkoutData=$order_data->checkoutDataCache??null;
        }
        if( !isset($checkoutData->validUntil) || $checkoutData->validUntil<time() ){
            $checkoutData=$this->checkoutDataGet($order);
            if( !is_object($checkoutData) ){
                return $this->fail($checkoutData);
            }    
        }
        /**
         * Here we are controlling if user selected options are valid
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
            $order->order_store_admins=$deliveryOption->storeAdmins;
            $OrderModel->fieldUpdateAllow('order_store_admins');
        } else {
            return $this->fail('no_payment');
        }
        $order_data->delivery_by_courier=1;
        $order_data->delivery_fee=$deliveryOption->deliveryFee;
        $order_data->delivery_cost=$deliveryOption->deliveryCost;
        $order_data->delivery_heavy_bonus=$deliveryOption->deliveryHeavyBonus;
        $order->order_sum_delivery=$deliveryOption->deliverySum;
        $OrderModel->fieldUpdateAllow('order_sum_delivery');

        //LOCATIONS DATA (SAVING IN DATA TO NOT AFFECT BY DELETION BY USER)
        $LocationModel=model('LocationModel');
        $order_data->location_start=$LocationModel->itemGet($order->order_start_location_id,'all');
        $order_data->location_finish=$LocationModel->itemGet($order->order_finish_location_id,'all');   

        //DELIVERY JOB SETUP
        $order_data->start_plan=$checkoutData->routePlan->start_plan;
        $order_data->start_plan_mode=$checkoutData->routePlan->start_plan_mode;//inited | awaited | scheduled 
        if( $checkoutSettings->deliveryFinishScheduled ){
            //if scheduled time is lesser than start_plan use start_plan
            $order_data->finish_plan_scheduled=strtotime($checkoutSettings->deliveryFinishScheduled);
            /**
             * finish_plan_scheduled must be saved in order_data to show in order view
             */
            $order_data->start_plan=max($order_data->finish_plan_scheduled-$checkoutData->routePlan->finish_arrival,$checkoutData->routePlan->start_plan);
            $order_data->start_plan_mode='scheduled';
        }
        $order_data->delivery_job=(object)[
            'job_name'=>'Посылка',
            'job_data'=>json_encode(['is_shipment'=>1,'distance'=>$checkoutData->routePlan->deliveryDistance,'finish_plan_scheduled'=>$order_data->finish_plan_scheduled??0]),
            'start_plan'=>$order_data->start_plan,
            'start_prep_time'=>null,
            'finish_arrival_time'=>$checkoutData->routePlan->finish_arrival,
            
            'start_longitude'=>$order_data->location_start->location_longitude,
            'start_latitude'=>$order_data->location_start->location_latitude,
            'start_address'=>$order_data->location_start->location_address,
            'finish_longitude'=>$order_data->location_finish->location_longitude,
            'finish_latitude'=>$order_data->location_finish->location_latitude,
            'finish_address'=>$order_data->location_finish->location_address,
        ];

        /**
         * Check if order is already not in confirmed state (for example returned to cart stage automatically)
         * If not - try to make confirmed
         */
        if( $order->stage_current!='customer_confirmed' ){
            $result=$OrderModel->itemStageCreate($order->order_id,'customer_confirmed');
            if( $result!='ok' ){
                return $this->fail('wrong_stage');
            }
        }
        //SAVING CHECKOUT DATA
        $OrderModel->itemDataCreate($checkoutSettings->order_id,$order_data);
        $result = $OrderModel->itemUpdate($order);
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        if( $order_data->payment_by_credit_store??0 ){
            $OrderModel->itemStageAdd($checkoutSettings->order_id,'customer_payed_credit');
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
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemStageCreate($order_id, $stage);
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
