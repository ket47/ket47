<?php
namespace App\Libraries\Telegram;
trait SupplierTrait{
    private $supplierButtons=[
        ['isCourierReady',  'onCourierSetIdle',   "\xE2\x9D\x8C Завершить смену"],
        ['isCourierReady',  'onCourierJobsGet',   "\xF0\x9F\x94\x8D Список заданий"],
        ['isCourier',       'onCourierStatus',    "\xE2\x9D\x95 Статус курьера"],
    ];


    public function supplierButtonsGet(){
        $buttons=[];
        $ownedStores=$this->storeListGet();
        foreach($ownedStores as $store){
            if($store->is_working){
                $buttons[]=['',"onStoreWorkingSet-{$store->store_id},0","\xF0\x9F\x92\xA4 Приостановить {$store->store_name}"];
            } else {
                $buttons[]=['',"onStoreWorkingSet-{$store->store_id},1","\xF0\x9F\x92\xA1 Запустить {$store->store_name}"];
            }

        }
        return $buttons;
    }

    public function onStoreWorkingSet($store_id, $is_working){
        $StoreModel=model('StoreModel');
        $StoreModel->itemUpdate((object)['store_id'=>$store_id,'is_working'=>$is_working]);
        $this->sendMainMenu();
    }


    public function supplierStatusGet(){
        $user=$this->userGet();
        $ownedStoreList=$this->storeListGet();
        $OrderModel=model("OrderModel");
        $incomingOrderCount=$OrderModel->listCountGet();

        $context=[
            'user'=>$user,
            'ownedStoreList'=>$ownedStoreList,
            'incomingOrderCount'=>$incomingOrderCount
        ];
        return View('messages/telegram/supplierStatus',$context);
    }

    private function storeListGet(){
        if( !$this->isUserSignedIn() ){
            return [];
        }
        $user_id=session()->get('user_id');
        $StoreModel=model('StoreModel');
        return $StoreModel->listGet(['owner_id'=>$user_id,'owner_ally_ids'=>$user_id]);
    }
}
