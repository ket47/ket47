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
        return $this->respond($result);
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

    // public function itemCreate($order_store_id=null) {
    //     $order_store_id = $this->request->getVar('order_store_id');
    //     $OrderModel = model('OrderModel');
    //     $result = $OrderModel->itemCreate($order_store_id);
    //     if ($result === 'forbidden') {
    //         return $this->failForbidden($result);
    //     }
    //     if ($result === 'noorder') {
    //         return $this->fail($result);
    //     }
    //     if ($OrderModel->errors()) {
    //         return $this->failValidationErrors($OrderModel->errors());
    //     }
    //     $OrderModel->itemStageCreate( $result, 'customer_cart' );
    //     return $this->respond($result);
    // }

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
        $order_id = $this->request->getVar('order_id');
        $new_stage = $this->request->getVar('new_stage');
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

    // public function itemInfoGet(){
    //     $order_id = $this->request->getVar('order_id');
    //     $OrderModel=model('OrderModel');
    //     $OrderModel->permitWhere('r');
    //     $OrderModel->where('order_id',$order_id);
    //     $OrderModel->select("JSON_EXTRACT(`order_data`,'$.info_for_customer') info_for_customer");
    //     $OrderModel->select("JSON_EXTRACT(`order_data`,'$.info_for_supplier') info_for_supplier");
    //     $OrderModel->select("JSON_EXTRACT(`order_data`,'$.info_for_courier') info_for_courier");
    //     $info=$OrderModel->get()->getRow();

    //     if(!$info || !$info->info_for_customer || !$info->info_for_supplier || !$info->info_for_courier){
    //         return $this->failNotFound();
    //     }
    //     $response=(object)[
    //         'info_for_customer'=>$info->info_for_customer?json_decode($info->info_for_customer):null,
    //         'info_for_supplier'=>$info->info_for_supplier?json_decode($info->info_for_supplier):null,
    //         'info_for_courier'=>$info->info_for_customer?json_decode($info->info_for_courier):null,
    //     ];
    //     return $this->respond($response);
    // }

    public function itemCheckoutDataGet(){
        $order_id = $this->request->getVar('order_id');
        $OrderModel = model('OrderModel');
        $order = $OrderModel->itemGet($order_id,'basic');
        if ($order === 'forbidden') {
            return $this->failForbidden();
        }
        if ($order === 'notfound') {
            return $this->failNotFound();
        }

        $LocationModel=model('LocationModel');
        $PromoModel=model('PromoModel');
        $StoreModel=model('StoreModel');

        $bulkResponse=(object)[];
        $bulkResponse->Location_distanceHolderGet=$LocationModel->distanceHolderGet(
            'store',$order->order_store_id,
            'user',$order->owner_id
        );
        $bulkResponse->Store_deliveryOptions=$this->itemDeliveryOptionsGet(
            $order->order_store_id,
            $bulkResponse->Location_distanceHolderGet
        );
        if( in_array($bulkResponse->Store_deliveryOptions,['not_ready','no_tariff','too_far']) ){
            madd('order','create','error',$order_id,$bulkResponse->Store_deliveryOptions);
            return $this->fail($bulkResponse->Store_deliveryOptions);
        }
        $bulkResponse->Store_preparationTime=$StoreModel->itemGet($order->order_store_id,'basic')->store_time_preparation??0;

        // $bulkResponse->Location_count=$LocationModel->listCountGet([
        //     'location_holder'=>'user',
        //     'location_holder_id'=>$order->owner_id
        // ]);
        
        $bulkResponse->Promo_itemLinkGet=$PromoModel->itemLinkGet(
            $order_id
        );
        $bulkResponse->Promo_listGet=$PromoModel->listGet(
            $order->owner_id,
            'active',
            'count'
        );
        //if( sudo() ){
            $UserCardModel=model('UserCardModel');
            $bulkResponse->bankCard=$UserCardModel->itemMainGet($order->owner_id);
        //}

        $entry_count=model('EntryModel')->where('order_id',$order_id)->select('COUNT(*) c')->get()->getRow('c');
        madd('order','create','ok',$order_id,null,(object)['act_data'=>['entry_count'=>$entry_count,'store_id'=>$order->order_store_id]]);
        return $this->respond($bulkResponse);
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

    private function itemDeliveryOptionsGet( $store_id, int $delivery_distance=null ){
        $StoreModel=model('StoreModel');
        if(!$StoreModel->itemIsReady($store_id)){
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

        $courier_delivery_radius=$store_delivery_radius=getenv('delivery.radius');
        if( $store->store_delivery_radius>0 ){
            $store_delivery_radius=(int)$store->store_delivery_radius;
        }

        $default_error_code='no_tariff';
        if(!$storeTariffRuleList){
            return $default_error_code;
        }
        $deliveryHeavyModifier=$this->itemDeliveryHeavyGet();
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
                $order_sum_delivery=(int)$tariff->delivery_cost+$deliveryHeavyModifier->cost;
                $rule=[
                    'tariff_id'=>$tariff->tariff_id,
                    'order_sum_delivery'=>$order_sum_delivery,
                    'order_sum_minimal'=>$this->itemSumMinimalGet('delivery_by_courier'),
                    'deliveryHeavyCost'=>$deliveryHeavyModifier->cost,
                    'deliveryByCourier'=>1,
                    'deliveryIsReady'=>$deliveryIsReady,
                    'deliveryByStore'=>0,
                    'pickupByCustomer'=>0,
                    'paymentByCard'=>0,
                    'paymentByCash'=>0,
                    'paymentByCashStore'=>0
                ];
                if($tariff->card_allow==1){
                    $rule['paymentByCard']=1;
                }
                if($tariff->cash_allow==1){
                    $rule['paymentByCash']=1;
                }
                $deliveryOptions[]=$rule;
            } else {
                if($store->store_delivery_allow==1){
                    if( $delivery_distance>$store_delivery_radius ){
                        /**
                         * Delivery distance is bigger than maximum store courier reach
                         */
                        $default_error_code='too_far';
                        continue;
                    }
                    $rule=[
                        'tariff_id'=>$tariff->tariff_id,
                        'order_sum_delivery'=>(int)$store->store_delivery_cost,
                        'order_sum_minimal'=>$store->store_minimal_order,
                        'deliveryByCourier'=>0,
                        'deliveryByStore'=>1,
                        'pickupByCustomer'=>0,
                        'paymentByCard'=>0,
                        'paymentByCash'=>0,
                        'paymentByCashStore'=>0
                    ];
                    if($tariff->card_allow==1){
                        $rule['paymentByCard']=1;
                    }
                    if($tariff->cash_allow==1){
                        $rule['paymentByCashStore']=1;
                    }
                    $deliveryOptions[]=$rule;
                }
                if($store->store_pickup_allow==1){
                    $rule=[
                        'tariff_id'=>$tariff->tariff_id,
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
                    }
                    $deliveryOptions[]=$rule;
                }
            }
        }
        if( !count($deliveryOptions) ){
            /**
             * If no rules are found then return error code no_tariff or too_far
             */
            return $default_error_code;
        }
        return $deliveryOptions;
    }

    private function itemSumMinimalGet( $mode='delivery_by_courier' ){
        return 200;
    }

    public function itemCheckoutDataSet(){
        $checkoutData = $this->request->getJSON();
        $OrderModel = model('OrderModel');
        $order = $OrderModel->itemGet($checkoutData->order_id,'basic');
        $order_data=$OrderModel->itemDataGet($checkoutData->order_id);
        if($order_data->payment_card_fixate_id??0){
            madd('order','start','error',$checkoutData->order_id,'payment_already_done');
            return $this->failResourceExists('payment_already_done');
        }
        if ($order === 'forbidden' || !$checkoutData->order_id??0 || !$checkoutData->tariff_id??0 ) {
            madd('order','start','error',null,'forbidden');
            return $this->failForbidden();
        }
        if ($order === 'notfound') {
            madd('order','start','error',null,'notfound');
            return $this->failNotFound();
        }

        $TariffMemberModel=model('TariffMemberModel');
        $tariff=$TariffMemberModel->itemGet($checkoutData->tariff_id,$order->order_store_id);
        if(!$tariff){
            madd('order','start','error',$checkoutData->order_id,'no_tariff');
            return $this->fail('no_tariff');
        }
        $order_data=(object)[];
        if( $tariff->order_cost ){
            $order_data->order_cost=$tariff->order_cost;
        }
        if( $tariff->order_fee ){
            $order_data->order_fee=$tariff->order_fee;
        }
        $LocationModel=model('LocationModel');

        $order_start_location=$LocationModel->itemMainGet('store',$order->order_store_id);
        $order_finish_location=$LocationModel->itemMainGet('user',$order->owner_id);
        $delivery_distance=$LocationModel->distanceGet($order_start_location->location_id,$order_finish_location->location_id);
        if( $delivery_distance>getenv('delivery.radius') ){
            madd('order','start','error',$checkoutData->order_id,'too_far');
            return $this->fail('too_far');
        }
        /**
         * RACING CONDITION CAN OCCUR!!! WHEN MODIFYING ORDER DATA FROM LOADED PREVIOUSLY OBJECT
         */
        /**
         * Should use shipment system!
         */


        $StoreModel=model('StoreModel');
        $PromoModel=model('PromoModel');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');

        //DELIVERY OPTIONS SET
        if( $checkoutData->deliveryByCourier??0 && $tariff->delivery_allow ){
            $deliveryHeavyModifier=$this->itemDeliveryHeavyGet();
            $order_data->delivery_by_courier=1;
            $order_data->delivery_fee=$tariff->delivery_fee;
            $order_data->delivery_cost=$tariff->delivery_cost+$deliveryHeavyModifier->cost;
            $order_data->delivery_heavy_bonus=$deliveryHeavyModifier->bonus;
            $CourierModel=model('CourierModel');
            $lookForCourierAroundLocation=(object)[
                'location_holder'=>'store',
                'location_holder_id'=>$order->order_store_id
            ];
            $deliveryIsReady=$CourierModel->deliveryIsReady($lookForCourierAroundLocation);
            if( !$deliveryIsReady ){
                madd('order','start','error',$checkoutData->order_id,'no_delivery');
                return $this->fail('no_delivery');
            }

            //DELIVERY JOB SETUP


            //TMP FIX
            $DeliveryJobModel=model('DeliveryJobModel');
            $routePlan=$DeliveryJobModel->routePlanGet($order_start_location->location_id,$order_finish_location->location_id);
            $payment_by_cash=($tariff->cash_allow && ($checkoutData->paymentByCash??0))?1:0;
            $order_data->delivery_job=(object)[
                'job_name'=>"Заказ из {$store->store_name}",
                'job_data'=>json_encode([
                    'distance'=>$routePlan->deliveryDistance,
                    'finish_plan_scheduled'=>$order_data->finish_plan_scheduled??0,
                    'payment_by_cash'=>$payment_by_cash
                ]),
                'start_plan'=>$routePlan->start_plan??0,
                'start_prep_time'=>$store->store_time_preparation,
                
                'start_longitude'=>$order_start_location->location_longitude,
                'start_latitude'=>$order_start_location->location_latitude,
                'start_address'=>$order_start_location->location_address,
                'finish_longitude'=>$order_finish_location->location_longitude,
                'finish_latitude'=>$order_finish_location->location_latitude,
                'finish_address'=>$order_finish_location->location_address,
            ];
        } else
        if( $checkoutData->deliveryByStore??0 ){

            $order_data->delivery_by_store=1;
            $order_data->delivery_by_store_cost=$store->store_delivery_cost??0;
            $PromoModel->itemUnLink($checkoutData->order_id);
        } else
        if( $checkoutData->pickupByCustomer??0 ){
            $order_data->pickup_by_customer=1;
            $PromoModel->itemUnLink($checkoutData->order_id);
        } else {
            madd('order','start','error',$checkoutData->order_id,'no_delivery');
            return $this->fail('no_delivery');
        }
        //PROMO SHARE CHECK
        $promo=$PromoModel->itemLinkGet($checkoutData->order_id);
        if( $promo && ($promo->min_order_sum_product>$order->order_sum_product) ){
            madd('order','start','error',$checkoutData->order_id,'promo_share_too_high');
            return $this->fail('promo_share_too_high');
        }
        //PAYMENT OPTIONS SET
        if( $checkoutData->paymentByCardRecurrent??0 && $tariff->card_allow ){
            $order_data->payment_by_card_recurrent=1;
            $order_data->payment_by_card=1;
            $order_data->payment_fee=$tariff->card_fee;
        } else
        if( $checkoutData->paymentByCard??0 && $tariff->card_allow ){
            $order_data->payment_by_card=1;
            $order_data->payment_fee=$tariff->card_fee;
        } else
        if( $checkoutData->paymentByCash??0 && $tariff->cash_allow ){
            $order_data->payment_by_cash=1;
            $order_data->payment_fee=$tariff->cash_fee;
        } else 
        if( $checkoutData->paymentByCashStore??0 ){
            $order_data->payment_by_cash_store=1;
        } else {
            madd('order','start','error',$checkoutData->order_id,'no_payment');
            return $this->fail('no_payment');
        }

        if( $checkoutData->storeCorrectionAllow??0 ){
            $order_data->store_correction_allow=1;
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
        $result=$OrderModel->itemDataCreate($checkoutData->order_id,$order_data);
        $OrderModel->deliverySumUpdate($checkoutData->order_id);
        madd('order','start','ok',$checkoutData->order_id);
        return $this->respond($result);
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
        return $this->respond($order_list);
    }

    public function listCountGet(){
        $OrderModel=model('OrderModel');
        $count=$OrderModel->listCountGet();
        return $this->respond($count);
    }
    

    // public function listCartGet(){
    //     $OrderModel=model('OrderModel');
    //     $order_list=$OrderModel->listCartGet();
    //     return $this->respond($order_list);
    // }

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
