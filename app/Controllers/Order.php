<?php

namespace App\Controllers;

use \CodeIgniter\API\ResponseTrait;

class Order extends \App\Controllers\BaseController {

    use ResponseTrait;

    public function itemGet($order_id=null) {
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

    public function itemCreate($order_store_id=null) {
        $order_store_id = $this->request->getVar('order_store_id');
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemCreate($order_store_id);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'noorder') {
            return $this->fail($result);
        }
        if ($OrderModel->errors()) {
            return $this->failValidationErrors($OrderModel->errors());
        }
        return $this->respond($result);
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
            $order_id_exists=$OrderModel->where($data->order_id)->get()->getRow('order_id');
        }
        $OrderModel->transBegin();
        if( !$order_id_exists ){
            if( !isset($data->order_store_id) ){
                $OrderModel->transRollback();
                return $this->fail('nostoreid');
            }
            $result=$OrderModel->itemCreate($data->order_store_id);
            if ($result === 'forbidden') {
                $OrderModel->transRollback();
                return $this->failForbidden($result);
            }
            if (!is_numeric($result)) {
                $OrderModel->transRollback();
                return $this->fail($result);
            }
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
        $OrderModel=model('OrderModel');

        $bulkResponse=(object)[];
        $bulkResponse->Store_deliveryOptions=$this->itemDeliveryOptionsGet(
            $order->order_store_id
        );
        if( $bulkResponse->Store_deliveryOptions=='not_ready' || $bulkResponse->Store_deliveryOptions=='no_tariff' ){
            return $this->fail($bulkResponse->Store_deliveryOptions);
        }
        $bulkResponse->Location_distanceHolderGet=$LocationModel->distanceHolderGet(
            'store',$order->order_store_id,
            'user',$order->owner_id
        );
        $bulkResponse->Promo_itemLinkGet=$PromoModel->itemLinkGet(
            $order_id
        );
        $bulkResponse->Promo_listGet=$PromoModel->listGet(
            $order->owner_id,
            'active',
            'count'
        );
        return $this->respond($bulkResponse);
    }

    private function itemDeliveryOptionsGet( $store_id ){
        $StoreModel=model('StoreModel');
        if(!$StoreModel->itemIsReady($store_id)){
            return 'not_ready';
        }
        
        $store=$StoreModel->itemGet($store_id,'basic');
        $storeTariffRuleList=$StoreModel->tariffRuleListGet($store_id);
        if(!$storeTariffRuleList){
            return 'no_tariff';
        }

        $deliveryOptions=[];
        foreach($storeTariffRuleList as $tariff){
            if($tariff->delivery_allow==1){
                $rule=[
                    'tariff_id'=>$tariff->tariff_id,
                    'order_sum_delivery'=>(int)$tariff->delivery_cost,
                    'deliveryByCourier'=>1,
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
                    $rule=[
                        'tariff_id'=>$tariff->tariff_id,
                        'order_sum_delivery'=>(int)$store->store_delivery_cost,
                        'deliveryByCourier'=>0,
                        'deliveryByStore'=>1,
                        'pickupByCustomer'=>0,
                        'paymentByCard'=>0,
                        'paymentByCash'=>0,
                        'paymentByCashStore'=>1
                    ];
                    if($tariff->card_allow==1){
                        $rule['paymentByCard']=1;
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
        $result='no_tariff';
        if( count($deliveryOptions)>0 ){
            $result=$deliveryOptions;
        }
        return $result;
    }

    public function itemCheckoutDataSet(){
        $checkoutData = $this->request->getJSON();
        $OrderModel = model('OrderModel');
        $order = $OrderModel->itemGet($checkoutData->order_id,'basic');
        if ($order === 'forbidden' || !$checkoutData->order_id??0 || !$checkoutData->tariff_id??0 ) {
            return $this->failForbidden();
        }
        if ($order === 'notfound') {
            return $this->failNotFound();
        }
        $TariffMemberModel=model('TariffMemberModel');
        $tariff=$TariffMemberModel->itemGet($checkoutData->tariff_id,$order->order_store_id);
        if(!$tariff){
            return 'no_tariff';
        }
        $order_data=(object)[];
        if( $tariff->order_cost ){
            $order_data->order_cost=$tariff->order_cost;
        }
        //DELIVERY OPTIONS SET
        if( $checkoutData->deliveryByCourier??0 && $tariff->delivery_allow ){
            $order_data->delivery_by_courier=1;
            $order_data->delivery_fee=$tariff->delivery_fee;
            $order_data->delivery_cost=$tariff->delivery_cost;
        }
        if( $checkoutData->deliveryByStore??0 ){
            $order_data->delivery_by_store=1;
            $PromoModel=model('PromoModel');
            $PromoModel->itemUnLink($checkoutData->order_id);
        }
        if( $checkoutData->pickupByCustomer??0 ){
            $order_data->pickup_by_customer=1;
            $PromoModel=model('PromoModel');
            $PromoModel->itemUnLink($checkoutData->order_id);
        }
        //PAYMENT OPTIONS SET
        if( $checkoutData->paymentByCard??0 && $tariff->card_allow ){
            $order_data->payment_by_card=1;
            $order_data->payment_fee=$tariff->card_fee;
        }
        if( $checkoutData->paymentByCash??0 && $tariff->cash_allow ){
            $order_data->payment_by_cash=1;
            $order_data->payment_fee=$tariff->cash_fee;
        }
        if( $checkoutData->paymentByCashStore??0 ){
            $order_data->payment_by_cash_store=1;
        }
        $OrderModel->itemDataDelete($checkoutData->order_id);
        $result=$OrderModel->itemDataUpdate($checkoutData->order_id,$order_data);
        $OrderModel->deliverySumUpdate($checkoutData->order_id);
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
            'trans_holder'=>'order',
            'trans_holder_id'=>$order_id
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
                $result=$this->fileSaveImage($image_holder_id,$file);                if( $result!==true ){
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
