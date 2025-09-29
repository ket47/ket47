<?php

namespace App\Controllers;

use \CodeIgniter\API\ResponseTrait;

class Order extends \App\Controllers\BaseController {

    use ResponseTrait;

    public function itemGet($order_id=null) {
        if( !(session()->get('user_id')>-1) ){
            return $this->failUnauthorized('unauthorized');
        }
        if( !$order_id ){
            $order_id = $this->request->getVar('order_id');
        }
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemGet($order_id);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }

        $result->time_plan=$this->itemTimePlanGet($order_id);
        return $this->respond($result);
    }

    /**
     * Timing plans to inform store and customer
     */
    private function itemTimePlanGet( $order_id ){
        $time_plan=[];
        $OrderModel = model('OrderModel');
        $order_data=$OrderModel->itemDataGet($order_id);
        if( $order_data->finish_plan_scheduled??0 ){
            $time_plan['finish_plan_scheduled']=$order_data->finish_plan_scheduled;
        }
        if( $order_data->delivery_by_courier??0 ){
            $DeliveryJobModel=model('DeliveryJobModel');
            $DeliveryJobModel->allowRead();
            $DeliveryJobModel->select('start_plan,finish_arrival_time');
            $djob=$DeliveryJobModel->itemGet(null,$order_id);
            if( $djob ){
                $time_plan['start_plan']=(int) $djob->start_plan;
                $time_plan['finish_arrival_time']=(int) $djob->finish_arrival_time;
            }
        }
        return $time_plan;
    }

    public function itemDetailsPrepaymentGet(){
        $order_id = $this->request->getVar('order_id');
        $OrderModel=model('OrderModel');
        $order_data=$OrderModel->itemDataGet($order_id);
        if(!$order_data){
            return $this->failNotFound('notfound');
        }
        return $this->respond([
            'order_sum_prepayed'=>$order_data->payment_card_fixate_sum??0
        ]);
    }

    public function itemSync() {
        $data = $this->request->getJSON();
        if(!$data){
            madd('order','create','error',null,'malformed_request');
            return $this->fail('malformed_request');
        }
        if( session()->get('user_id')<=0 && session()->get('user_id')!=-100 ){//system user
            return $this->failUnauthorized('unauthorized');
        }
        $OrderModel = model('OrderModel');
        $order_id_exists=false;
        if( ($data->order_id??-1)>0 ){
            $order_id_exists=$OrderModel->where($data->order_id)->get()->getRow('order_id');
        }
        $OrderModel->transBegin();
        if( !$order_id_exists ){
            if( !isset($data->order_store_id) ){
                $OrderModel->transRollback();
                madd('order','create','error',($data->order_id??null),'nostoreid');
                return $this->fail('nostoreid');
            }
            $result=$OrderModel->itemCreate($data->order_store_id,'order_delivery');
            if ($result === 'forbidden') {
                $OrderModel->transRollback();
                madd('order','create','error',($data->order_id??null),'forbidden');
                return $this->failForbidden($result);
            }
            if (!is_numeric($result)) {
                $OrderModel->transRollback();
                madd('order','create','error',($data->order_id??null),$result);
                return $this->fail($result);
            }
            $OrderModel->itemStageCreate( $result, 'customer_cart' );
            $data->order_id=$result;
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
        return $this->itemGet($data->order_id);
    }

    public function itemUpdate() {
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

    public function itemDelete() {
        $order_id = $this->request->getVar('order_id');
        return $this->itemStage($order_id, 'customer_deleted');
    }

    public function itemUnDelete() {
        $order_id = $this->request->getVar('order_id');
        return $this->itemStage($order_id, 'customer_cart');
    }

    public function itemDisable() {
        return $this->failNotFound();
    }

    public function itemPurge(){
        $order_id=$this->request->getVar('order_id');
        
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemPurge($order_id);        
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }

    private function itemDeliverySumGet( int $distance_m, object $tariff ){
        $distance_km=round($distance_m/1000,1);
        return $tariff->delivery_cost+round($tariff->delivery_fee*$distance_km);
    }

    public function itemScheduleRangeGet(){
        $timetable=$this->request->getPost('timetable');
        $timetable=json_decode($timetable,true);
        if( !$timetable ){
            return $this->fail('notimetable');
        }
        $DeliveryJobPlan=new \App\Libraries\DeliveryJobPlan();
        $DeliveryJobPlan->schedule->timetableSet($timetable);
        $scheduleRange=$DeliveryJobPlan->planScheduleGet();
        return $this->respond($scheduleRange);
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

    /**
     * Looking for available tariffs of store
     */
    private function itemDeliveryOptionsGet( $store_id, ?int $delivery_distance=null, $features=null ){
        $StoreModel=model('StoreModel');
        $store_readyness=$StoreModel->itemIsReady($store_id);
        if( !$store_readyness->is_ready ){
            return 'not_ready';
        }
        if( !$store_readyness->is_open && !str_contains($features??'-','schedule') ){
            return 'not_ready';
        }

        $tariff_order_mode='delivery_by_courier_first';
        $lookForCourierAroundLocation=(object)[
            'location_holder'=>'store',
            'location_holder_id'=>$store_id
        ];
        $CourierModel=model('CourierModel');
        $deliveryIsReady=$CourierModel->deliveryIsReady($lookForCourierAroundLocation);
        if(!$deliveryIsReady){
            $tariff_order_mode='delivery_by_courier_last';
        }
        $store=$StoreModel->itemGet($store_id,'basic');
        $storeTariffRuleList=$StoreModel->tariffRuleListGet($store_id,$tariff_order_mode);

        $default_error_code='no_tariff';
        if(!$storeTariffRuleList){
            return $default_error_code;
        }

        $courier_delivery_radius=$store_delivery_radius=getenv('delivery.radius');
        if( $store->store_delivery_radius>0 ){
            $store_delivery_radius=(int)$store->store_delivery_radius;
        }
        $deliveryOptions=[];
        foreach($storeTariffRuleList as $tariff){
            if($tariff->delivery_allow==1){
                if( $delivery_distance>$courier_delivery_radius ){
                    /**
                     * Delivery distance is bigger than maximum courier reach
                     */
                    $default_error_code='too_far';
                    continue;
                }
                if( !$deliveryIsReady ){
                    $CourierModel->deliveryNotReadyNotify($lookForCourierAroundLocation);//notify of absent courier only if needed
                }
                $deliveryHeavyModifier=$this->itemDeliveryHeavyGet();
                $order_sum_delivery=(int)$tariff->delivery_cost+$deliveryHeavyModifier->cost;
                $tariff->delivery_heavy_bonus=$deliveryHeavyModifier->bonus;
                $rule=[
                    'tariff_id'=>$tariff->tariff_id,
                    'reckonParameters'=>$tariff,
                    'order_sum_delivery'=>(int)$order_sum_delivery,
                    'order_sum_minimal'=>$this->itemSumMinimalGet('delivery_by_courier'),
                    'deliverySum'=>(int)$order_sum_delivery,
                    'deliveryHeavyCost'=>(int)$deliveryHeavyModifier->cost,
                    'deliveryByCourier'=>1,
                    'deliveryByStore'=>0,
                    'deliveryIsReady'=>$deliveryIsReady,
                    'storeIsReady'=>$store_readyness->is_open,
                    'pickupByCustomer'=>0,
                    'paymentByCard'=>0,
                    'paymentByCash'=>0,
                    'paymentByCashStore'=>0,
                ];
                if($tariff->card_allow==1){
                    $rule['paymentByCard']=1;
                    $rule['paymentByCardRecurrent']=1;
                }
                if($tariff->cash_allow==1){
                    $rule['paymentByCash']=1;
                }
                $deliveryOptions[]=$rule;
            } else {
                if( !$store_readyness->is_open ){
                    $default_error_code='not_ready';
                    continue;
                }
                if( $delivery_distance>$store_delivery_radius ){
                    $default_error_code='too_far';
                }
                if($store->store_delivery_allow==1 && $delivery_distance<=$store_delivery_radius){
                    $rule=[
                        'tariff_id'=>$tariff->tariff_id,
                        'reckonParameters'=>$tariff,
                        'order_sum_delivery'=>(int)$store->store_delivery_cost,
                        'order_sum_minimal'=>$store->store_minimal_order,
                        'deliverySum'=>(int)$store->store_delivery_cost,
                        'deliveryByCourier'=>0,
                        'deliveryByStore'=>1,
                        'pickupByCustomer'=>0,
                        'paymentByCard'=>0,
                        'paymentByCash'=>0,
                        'paymentByCashStore'=>0
                    ];
                    if($tariff->card_allow==1){
                        $rule['paymentByCard']=1;
                        $rule['paymentByCardRecurrent']=1;
                    }
                    if($tariff->cash_allow==1){
                        $rule['paymentByCashStore']=1;
                    }
                    $deliveryOptions[]=$rule;
                }
                if($store->store_pickup_allow==1){
                    $rule=[
                        'tariff_id'=>$tariff->tariff_id,
                        'reckonParameters'=>$tariff,
                        'order_sum_delivery'=>0,
                        'deliveryByCourier'=>0,
                        'deliveryByStore'=>0,
                        'pickupByCustomer'=>1,
                        'paymentByCard'=>0,
                        'paymentByCash'=>0,
                        'paymentByCashStore'=>1
                    ];
                    if($tariff->card_allow==1){
                        $rule['paymentByCard']=1;
                        $rule['paymentByCardRecurrent']=1;
                    }
                    $deliveryOptions[]=$rule;
                }
            }
        }
        if( !count($deliveryOptions) ){
            /**
             * If no rules are found then return error code no_tariff or too_far or not_ready
             */
            return $default_error_code;
        }
        return $deliveryOptions;
    }

    private function itemSumMinimalGet(){
        return 300;
    }

    private function routePlanGet( object $order, int $start_location_id, int $finish_location_id ){
        $StoreModel=model('StoreModel');
        $storeTimetable=$StoreModel->itemTimetableGet($order->order_store_id,'basic');

        $DeliveryJobPlan=new \App\Libraries\DeliveryJobPlan();
        $DeliveryJobPlan->scheduleFillShift();
        $DeliveryJobPlan->scheduleFillTimetable($storeTimetable);

        $DeliveryJobPlan->startPreparationSet($storeTimetable->store_time_preparation*60);
        $routePlan=$DeliveryJobPlan->routePlanGet($start_location_id,$finish_location_id);

        if( empty($routePlan->error) ){
            $peak_hour_offset=$DeliveryJobPlan->peakHourOffset(time());//if now is a peak hour then offset initiation time
            $init_finish_offset=max($routePlan->init_finish_offset,60*60);//init to finish time is not less than 60min
            $DeliveryJobPlan->schedule->begin(time(),'before');//offsetting today work window from now
            $DeliveryJobPlan->schedule->offset( $init_finish_offset+$peak_hour_offset );//offsetting all day windows
            $routePlan->finish_plan_timetable=$DeliveryJobPlan->schedule->timetableGet();
        }
        return $routePlan;
    }

    /**
     * Collects all data needed for checkout in one object
     */
    private function checkoutDataGet( $order, $features ){
        $data=(object)[];
        $data->validUntil=time()+10*60;//10 min
        $LocationModel=model('LocationModel');
        
        /**
         * Here we are fixating start and finish location ids
         */
        $owner_id=session()->get('user_id');
        $data->location_start=$LocationModel->itemMainGet('store',$order->order_store_id);
        $data->location_finish=$LocationModel->itemMainGet('user',$owner_id);
        if( empty($data->location_start) || empty($data->location_finish) ){
            return 'too_far';//user finish location is not set or store start location is not set
        }
        $start_finish_distance=(int) $LocationModel->distanceGet($data->location_start->location_id,$data->location_finish->location_id);

        $data->Store_deliveryOptions=$this->itemDeliveryOptionsGet( $order->order_store_id, $start_finish_distance, $features );
        if( !is_array($data->Store_deliveryOptions) ){
            return $data->Store_deliveryOptions;
        }

        $PromoModel=model('PromoModel');
        $UserCardModel=model('UserCardModel');

        $data->bankCard=$UserCardModel->itemMainGet($order->owner_id);
        $data->Promo_itemLinkGet=$PromoModel->itemLinkGet(
            $order->order_id
        );
        $data->Promo_listGet=$PromoModel->listGet(
            $order->owner_id,
            'active',
            'count'
        );

        $data->Promo_bonus=$PromoModel->bonusOrderCalculate( $order->order_id );
        if( $data->Promo_bonus->bonus_spend??null ){
            $data->Promo_bonus->bonus_total=$PromoModel->bonusTotalGet( $order->owner_id )??0;
            $data->Promo_bonus->bonus_usable=min($data->Promo_bonus->bonus_spend,$data->Promo_bonus->bonus_total);
        }
        /**
         * ROUTE PLANNING AND SCHEDULING ONLY FOR DELIVERY BY COURIER (FOR NOW)
         */
        foreach( $data->Store_deliveryOptions as $i=>$option){
            if( $option['deliveryByCourier'] ){
                $data->Store_deliveryOptions[$i]['routePlan']=$this->routePlanGet($order,$data->location_start->location_id,$data->location_finish->location_id);
                // if( $data->Store_deliveryOptions[$i]['tariff']->cash_back>0 ){
                //     $bonusTotal=$PromoModel->bonusGainGet($order->order_id,$data->Store_deliveryOptions[$i]['tariff']->cash_back);
                // }
            }
        }
        return $data;
    }

    public function itemCheckoutDataGet(){
        $order_id = $this->request->getVar('order_id');
        $features = $this->request->getVar('features');
        if(!$order_id){
            return $this->fail('noid');
        }
        $OrderModel = model('OrderModel');
        $order = $OrderModel->itemGet($order_id,'all');
        if ($order === 'forbidden') {
            return $this->failForbidden('forbidden');
        }
        if ($order === 'notfound') {
            return $this->failNotFound('notfound');
        }
        if ($order->stage_current != 'customer_confirmed') {
            return $this->failNotFound('not_confirmed');
        }

        $bulkResponse=$this->checkoutDataGet($order,$features);
        if( !is_object($bulkResponse) ){
            return $this->fail($bulkResponse);
        }
        $OrderModel->itemDataUpdate($order_id,(object)['checkoutDataCache'=>$bulkResponse]);
        $bulkResponse->order=$order;
        /**
         * DELETING RECKON PARAMETERS FROM RESPONSE
         */
        foreach( $bulkResponse->Store_deliveryOptions as $i=>$option){
            unset($bulkResponse->Store_deliveryOptions[$i]['reckonParameters']);
        }
        $entry_count=model('EntryModel')->where('order_id',$order_id)->select('COUNT(*) c')->get()->getRow('c');
        madd('order','create','ok',$order_id,null,(object)['act_data'=>['entry_count'=>$entry_count,'store_id'=>$order->order_store_id]]);
        return $this->respond($bulkResponse);
    }

    public function itemCheckoutDataSet(){
        $checkoutSettings = $this->request->getJSON();
        $OrderModel = model('OrderModel');
        $order = $OrderModel->itemGet($checkoutSettings->order_id,'basic');
        if ( $order === 'forbidden' || !$checkoutSettings->order_id??0 || !$checkoutSettings->tariff_id??0 ) {
            madd('order','start','error',$checkoutSettings->order_id,'forbidden');
            return $this->failForbidden();
        }
        if ( $order === 'notfound' ) {
            madd('order','start','error',$checkoutSettings->order_id,'forbidden');
            return $this->failNotFound();
        }
        $order_data=$OrderModel->itemDataGet($checkoutSettings->order_id);
        if($order_data->payment_card_fixate_id??0){
            /**
             * Checking if payment is done. Add stage customer_payed_card
             */
            $payment_card_acquirer=$order_data->payment_card_acquirer??'AcquirerUniteller';
            if($order_data->payment_card_acq_rncb??0){
                $payment_card_acquirer='AcquirerRncb';//backward compatibility
            }
            $Acquirer=\Config\Services::acquirer(false,$payment_card_acquirer);
            $result=$Acquirer->statusCheck( $checkoutSettings->order_id );
            if( $result!='order_not_payed' ){
                madd('order','start','error',$checkoutSettings->order_id,'payment_already_done');
                return $this->failResourceExists('payment_already_done');
            }
        }
        /**
         * Try to use checkout cache data. If it is outdated create new
         */
        if( isset($order_data->checkoutDataCache->validUntil) && $order_data->checkoutDataCache->validUntil>time() ){
            $checkoutData=$order_data->checkoutDataCache;
        } else {
            $checkoutData=$this->checkoutDataGet($order,'schedule');
        }
        if( !is_object($checkoutData) ){
            return $this->fail($checkoutData);
        }
        /**
         * Here we are controlling if user selected options are valid
         * need to move to separate function
         */
        $deliveryOption=null;
        foreach($checkoutData->Store_deliveryOptions as $opt){
            $option=(object) $opt;
            $option_is_matched=true;
            $flags=["tariff_id","deliveryByCourier","deliveryByStore","pickupByCustomer","paymentByCard","paymentByCash"];
            foreach($flags as $flag){
                if( $checkoutSettings?->{$flag}==1 && $option->{$flag}!=1 ){//user selected but option not allowed
                    $option_is_matched=false;
                    break;
                }
            }
            if( $option_is_matched ){
                $deliveryOption=$option;
                break;
            }
        }
        if( !$deliveryOption ){
            madd('order','start','error',$checkoutSettings->order_id,'no_tariff');
            return $this->fail('no_tariff');
        }

        //CONSTRUCTING ORDER DATA
        $order_update=(object)['order_id'=>$order->order_id];
        $order_data=(object)[];
        $order_data->order_cost=$deliveryOption->reckonParameters->order_cost;
        $order_data->order_fee= $deliveryOption->reckonParameters->order_fee;

        //PAYMENT OPTIONS CHECK
        if( ($checkoutSettings->paymentByCardRecurrent??0) && ($deliveryOption->paymentByCardRecurrent??0) ){
            $order_data->payment_by_card_recurrent=1;
            $order_data->payment_by_card=1;
            $order_data->payment_fee=$deliveryOption->reckonParameters->card_fee;
        } else
        if( ($checkoutSettings->paymentByCard??0) && ($deliveryOption->paymentByCard??0) ){
            $order_data->payment_by_card=1;
            $order_data->payment_fee=$deliveryOption->reckonParameters->card_fee;
        } else
        if( ($checkoutSettings->paymentByCash??0) && ($deliveryOption->paymentByCash??0) ){
            $order_data->payment_by_cash=1;
            $order_data->payment_fee=$deliveryOption->reckonParameters->cash_fee;
        } else 
        if( ($checkoutSettings->paymentByCashStore??0) && ($deliveryOption->paymentByCashStore??0) ){
            $order_data->payment_by_cash_store=1;
        } else {
            return $this->fail('no_payment');
        }

        /**
         * THERE IS TROUBLE WITH INCONSTINTENT LOCATION
         */
        $order_data->location_start=$checkoutData->location_start;
        $order_data->location_finish=$checkoutData->location_finish;
        //DELIVERY OPTIONS CHECK
        if( ($checkoutSettings->deliveryByCourier??0) && ($deliveryOption->deliveryByCourier??0) ){
            $order_update->order_script='order_delivery';
            $order_update->order_sum_delivery=$deliveryOption->deliverySum;
            $OrderModel->fieldUpdateAllow('order_sum_delivery');
            $OrderModel->fieldUpdateAllow('order_script');

            $order_data->delivery_by_courier=1;
            $order_data->delivery_fee=$deliveryOption->reckonParameters->delivery_fee;
            $order_data->delivery_cost=$deliveryOption->reckonParameters->delivery_cost;
            $order_data->delivery_heavy_bonus=$deliveryOption->reckonParameters->delivery_heavy_bonus;

            //DELIVERY JOB SETUP
            /**
             * todo routePlan error handling. if startplan is not set. the is an error
             */
            $order_data->start_plan=$deliveryOption->routePlan->start_plan;
            $order_data->start_plan_mode='awaited';//$deliveryOption->routePlan->start_plan_mode;//inited | awaited | scheduled 
            if( $checkoutSettings->deliveryFinishScheduled??null ){
                $DeliveryJobPlan=new \App\Libraries\DeliveryJobPlan();
                /**
                 * finish_plan_scheduled must be saved in order_data to show in order view
                 */
                $order_data->finish_plan_scheduled=strtotime($checkoutSettings->deliveryFinishScheduled);
                $peak_hour_offset=$DeliveryJobPlan->peakHourOffset($order_data->finish_plan_scheduled);//if scheduled time is a peak hour then offset initiation time
                $order_data->init_plan_scheduled=$order_data->finish_plan_scheduled-$deliveryOption->routePlan->init_finish_offset-$peak_hour_offset;
                //if scheduled time is lesser than start_plan use start_plan
                $order_data->start_plan=max($order_data->finish_plan_scheduled-$deliveryOption->routePlan->finish_arrival,$deliveryOption->routePlan->start_plan);
                $order_data->start_plan_mode='scheduled';
            }

            $ReactionTagModel=model('ReactionTagModel');
            $customer_heart_count=$ReactionTagModel->customerRatingGet($order->owner_id);

            $StoreModel=model('StoreModel');
            $store=$StoreModel->itemGet($order->order_store_id,'basic');
            $order_data->delivery_job=(object)[
                'job_name'=>"{$store->store_name}",
                'job_data'=>json_encode([
                    'payment_by_cash'=>$order_data->payment_by_cash??0,
                    'distance'=>$deliveryOption->routePlan->deliveryDistance,
                    'finish_plan_scheduled'=>$order_data->finish_plan_scheduled??0,
                    'customer_heart_count'=>$customer_heart_count
                ]),
                'start_plan'=>$order_data->start_plan,
                'start_prep_time'=>null,
                'finish_arrival_time'=>$deliveryOption->routePlan->finish_arrival,
                
                'start_longitude'=>$order_data->location_start->location_longitude,
                'start_latitude'=>$order_data->location_start->location_latitude,
                'start_address'=>$order_data->location_start->location_address,
                'finish_longitude'=>$order_data->location_finish->location_longitude,
                'finish_latitude'=>$order_data->location_finish->location_latitude,
                'finish_address'=>$order_data->location_finish->location_address,
            ];
        } else
        if( ($checkoutSettings->deliveryByStore??0) && ($deliveryOption->deliveryByStore??0) ){
            $order_update->order_script='order_supplier';
            $order_update->order_sum_delivery=$deliveryOption->deliverySum;
            $OrderModel->fieldUpdateAllow('order_sum_delivery');
            $OrderModel->fieldUpdateAllow('order_script');

            $order_data->start_plan_mode='inited';
            $order_data->delivery_by_store=1;
        } else
        if( ($checkoutSettings->pickupByCustomer??0) && ($deliveryOption->pickupByCustomer??0) ){
            $order_update->order_script='order_supplier';
            $order_update->order_sum_delivery=0;
            $OrderModel->fieldUpdateAllow('order_sum_delivery');
            $OrderModel->fieldUpdateAllow('order_script');

            $order_data->start_plan_mode='inited';
            $order_data->pickup_by_customer=1;
        } else {
            return $this->fail('no_delivery');
        }
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
        //FIXING LOCATION IDS
        $order_update->order_start_location_id=$checkoutData->location_start->location_id;
        $order_update->order_finish_location_id=$checkoutData->location_finish->location_id;





        /**
         * Loyalty engine
         */
        if( ($order_data->payment_by_card??0) && ($order_data->delivery_by_courier??0) ){
            //allow promotions if any
        } else {
            //clear all promotions
            $PromoModel=model('PromoModel');
            $PromoModel->itemUnlink( $order->order_id );
        }





        //SAVING CHECKOUT DATA
        $OrderModel->itemDataCreate($checkoutSettings->order_id,$order_data);
        $result = $OrderModel->itemUpdate($order_update);
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        if ($result != 'ok' && $result != 'idle') {
            return $this->respondNoContent($result);
        }
        madd('order','start','ok',$checkoutSettings->order_id);
        //AUTOSTART FOR CASH PAYMENTS
        if( isset($order_data->payment_by_cash_store) || isset($order_data->payment_by_cash) ){
            return $OrderModel->itemStageCreate($order->order_id,'customer_start');
        }
        return $this->respondUpdated('ok');
    }

    public function itemMetaGet(){
        $order_id=$this->request->getVar('order_id');
        $OrderModel=model('OrderModel');
        $TransactionModel=model('TransactionModel');

        $order=$OrderModel->itemGet($order_id);
        $order_data=$OrderModel->itemDataGet($order_id);


        if ($order === 'forbidden') {
            return $this->failForbidden($order);
        }
        if ($order === 'notfound') {
            return $this->failNotFound($order);
        }

        if($order->user_role==='admin'){
            $meta=$order_data;
        } else {
            $meta=(object)[
                'invoice_link'=>$order_data->invoice_link??null,
                'invoice_sum'=>$order_data->invoice_sum??null,
                'payment_by_card'=>$order_data->payment_by_card??0,
                'delivery_by_courier'=>$order_data->delivery_by_courier??0,
                'delivery_by_store'=>$order_data->delivery_by_store??0,
                'pickup_by_customer'=>$order_data->pickup_by_customer??0,
                'payment_card_fixate_sum'=>$order_data->payment_card_fixate_sum??0,
                'payment_card_confirm_sum'=>$order_data->payment_card_confirm_sum??0,
                'payment_card_refund_sum'=>$order_data->payment_card_refund_sum??0,
            ];
        }
        $filter=(object)[
            'tagQuery'=>"order:{$order_id}"
        ];
        $meta->transactions=$TransactionModel->listFind($filter);
        return $this->respond($meta);
    }

    public function listGet() {
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'offset'=>$this->request->getVar('offset'),
            'limit'=>$this->request->getVar('limit'),
            'user_role'=>$this->request->getVar('user_role'),
            'order_store_id'=>$this->request->getVar('order_store_id'),
            'order_group_type'=>$this->request->getVar('order_group_type'),
            'date_start'=>$this->request->getVar('date_start'),
            'date_finish'=>$this->request->getVar('date_finish'),
            'has_invoice'=>$this->request->getVar('has_invoice'),
        ];
        $OrderModel=model('OrderModel');
        $order_list=$OrderModel->listGet($filter);

        madd('orderlist','get','ok');
        return $this->respond($order_list);
    }

    public function listCountGet(){
        $OrderModel=model('OrderModel');
        $count=$OrderModel->listCountGet();
        return $this->respond($count);
    }

    public function listStageGet() {
        $OrderGroupModel = model('OrderGroupModel');
        $result = $OrderGroupModel->listGet();
        return $this->respond($result);
    }

    public function listCreate() {
        
    }

    public function listUpdate() {
        return false;
    }

    public function listDelete() {
        return false;
    }

    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload(){
        $image_holder_id=$this->request->getVar('image_holder_id');
        if ( !(int) $image_holder_id ) {
            return $this->fail('no_holder_id');
        }
        $items = $this->request->getFiles();
        if(!$items){
            return $this->failResourceGone('no_files_uploaded');
        }
        $result=false;
        foreach($items['files'] as $file){
            $type = $file->getClientMimeType();
            if(!str_contains($type, 'image')){
                continue;
            }
            if ($file->isValid() && ! $file->hasMoved()) {
                $result=$this->fileSaveImage($image_holder_id,$file);
                if( $result!==true ){
                    return $this->fail($result);
                }
            }
        }
        if($result===true){
            return $this->respondCreated('ok');
        }
        return $this->fail('no_valid_images');
    }


    private function fileSaveImage($image_holder_id, $file) {
        $image_data = [
            'image_holder' => 'order',
            'image_holder_id' => $image_holder_id
        ];
        $OrderModel = model('OrderModel');
        $image_hash = $OrderModel->imageCreate($image_data);
        if (!$image_hash) {
            return $this->failForbidden('forbidden');
        }
        if ($image_hash === 'limit_exeeded') {
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH . 'images/', $image_hash . '.webp');

        return \Config\Services::image()
                        ->withFile(WRITEPATH . 'images/' . $image_hash . '.webp')
                        ->resize(1024, 1024, true, 'height')
                        ->convert(IMAGETYPE_WEBP)
                        ->save();
    }

    public function imageDelete() {
        $image_id = $this->request->getVar('image_id');

        $OrderModel = model('OrderModel');
        $result = $OrderModel->imageDelete($image_id);
        if ($result === 'ok') {
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }

    public function imageOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $OrderModel=model('OrderModel');
        $result=$OrderModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }


}
