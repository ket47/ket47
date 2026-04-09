<?php
namespace App\Libraries\VK;
trait CourierTrait{
    private $courierButtons=[
        ['onCourierGet', "👤 Статус"],
        ['onCourierJobsGet', "🌟 Свободные заказы"],
        ['onOrderListGet-1', "🏃 Активные заказы"],
        ['onOrderListGet-2', "✅ Завершённые заказы"]
    ];

    public function courierButtonsGet()
    {
        $buttons = [];
        foreach($this->courierButtons as $button){
            $buttons[] = $this->createButton($button[1], $button[0]);
        }
        if(!$this->isShiftOpened()){
            $buttons[] = [
                "action" => [
                    "type" => "location",
                    "payload" => json_encode(["command" => "location_new"])
                ]
            ];
        } else {
            $buttons[] = $this->createButton("🏁 Завершить смену", "onCourierSetIdle");
        }
        if($this->courierGet()->courier_parttime_notify == 'off'){
            $buttons[] = $this->createButton("🔊 Включить такси", "onCourierTaxiNotif-push");
        } else {
            $buttons[] = $this->createButton("🚫 Отключить такси", "onCourierTaxiNotif-off");
        }
        return $buttons;
    }

    public function courierStatusGet(){
        if( !$this->isCourier() ){
            return false;
        }
        $courier=$this->courierGet();
        $user=$this->userGet();
        $CourierModel=model("CourierModel");
        $jobs=$CourierModel->listJobGet($courier->courier_id);
        pl($courier);
        $context=[
            'courier'=>$courier,
            'user'=>$user,
            'job_count'=>is_array($jobs)?count($jobs):0
        ];
        return View('messages/vk/courierStatus',$context);
    }
    
    public function onCourierSetIdle(){
        $user=$this->userGet();
        if( $this->isCourierBusy() ){
            $this->api->setText("{$user->user_name}, нельзя закрыть смену во время задания");
            return $this->api->messagesSend($this->client_id);
        }
        if( $this->isCourierIdle() ){
            $this->api->setText("{$user->user_name}, ваша смена уже была закрыта");
            return $this->api->messagesSend($this->client_id);
        }
        if($this->courierSetIdle()){
            return $this->api->setText("Смена успешно закрыта");
        }
    }

    public function onCourierUpdateLocation($location){
        $lastUpdateMsg=session()->get('lastLocationUpdateMessage');
        if($lastUpdateMsg && ($lastUpdateMsg['updated_at']??0)>time()-60){
            //too many requests
            return false;
        }
        if( $this->isCourierIdle() ){
            $this->courierSetReady();
        }
        //limit coordinates to boundary box
        $bound_longitude_min=34.000344;
        $bound_longitude_max=34.217667;
        $bound_latitude_min=44.894650;
        $bound_latitude_max=44.996708;

        $location_is_distorted=0;
        if( $location['longitude']<$bound_longitude_min || $location['longitude']>$bound_longitude_max || $location['latitude']<$bound_latitude_min || $location['latitude']>$bound_latitude_max ){
            //looking if ouside of square
            $location_is_distorted=1;
        } else {
            //looking if ouside of octagon
            $octo_lat_third=($bound_latitude_max-$bound_latitude_min)/3;
            $octo_lat_top=$bound_latitude_min+$octo_lat_third*2;
            $octo_lat_bottom=$bound_latitude_min+$octo_lat_third;
            
            $octo_lon_third=($bound_longitude_max-$bound_longitude_min)/3;
            $octo_lon_left=$bound_longitude_min+$octo_lon_third;
            $octo_lon_right=$bound_longitude_min+$octo_lon_third*2;

            if( $location['longitude']<$octo_lon_left && ($location['latitude']<$octo_lat_bottom || $location['latitude']>$octo_lat_top) || $location['longitude']>$octo_lon_right && ($location['latitude']<$octo_lat_bottom || $location['latitude']>$octo_lat_top) ){
                $location_is_distorted=1;
            }
        }

        $courier=$this->courierGet();

        if($location_is_distorted){
            $location_lon_midpoint=($bound_longitude_min+$bound_longitude_max)/2;//set at midpoint
            $location_lat_midpoint=($bound_latitude_min+$bound_latitude_max)/2;//set at midpoint
            
            $location['latitude']= $location_lat_midpoint;
            $location['longitude']= $location_lon_midpoint;
            
        } else {
            $CourierShiftModel=model('CourierShiftModel');
            $CourierShiftModel->fieldUpdateAllow('actual_longitude');
            $CourierShiftModel->fieldUpdateAllow('actual_latitude');
            $CourierShiftModel->allowWrite();

            $update=(object)[
                'courier_id'=>$courier->courier_id,
                'actual_longitude'=>$location['longitude'],
                'actual_latitude'=>$location['latitude']
            ];
            $CourierShiftModel->itemUpdate($update);
        }
    }

    public function onCourierGet()
    {
        $this->api->setText($this->courierStatusGet());
        return true;
    }



    public function onCourierJobsGet(){
        if( !$this->isCourier() ){
            $this->api->setText("Вы не курьер, задания недоступны.");
            $this->api->messagesSend($this->client_id);
            return false;
        }
        if( $this->isCourierIdle() && !$this->isCourierTaxi() ){
            $this->api->setText("Откройте смену или режим такси.");
            $this->api->messagesSend($this->client_id);
            return false;
        }
        $OrderModel=model("OrderModel");
        $DeliveryJobModel=model("DeliveryJobModel");

        $courier=$this->courierGet();
        $isShiftOpened = $this->isShiftOpened();

        $jobs = $DeliveryJobModel->listGet($isShiftOpened);
        
        if( !count($jobs) ){
            $this->api->setText("Нет доступных заданий");
            $this->api->messagesSend($this->client_id);
            return true;
        }
        $jobs_filtered = [];
        foreach($jobs as &$job){
            if($job->stage !== 'awaited'){
                continue;
            }
            $OrderModel->select("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.info_for_courier')) info");
            $OrderModel->where('order_id', $job->order_id);
            
            $job->info = json_decode($OrderModel->get()->getRow('info')??'');
            $jobs_filtered[] = $job;
        }
        
        foreach($jobs_filtered as &$job){
            $html=View('messages/vk/jobItem',['job' => $job]);
            $keyboard = [
                "one_time" => false,
                "inline" => true,
                "buttons" => [
                    [ $this->createButton("🚀 Взять задание", "onCourierJobTake-{$job->order_id}") ]
                ]
            ];
            $this->api->setKeyboard($keyboard);
            $this->api->setText($html);
            $this->api->messagesSend($this->client_id);
        }
        if(!empty($jobs_filtered)){
            $this->api->setText("👆 Вот список доступных заданий (".count($jobs_filtered).")");
        } else {
            $this->api->setText("Доступных заданий нет!");
        }
        return true;
    }

    private function isShiftOpened(){
        $CourierShiftModel=model("CourierShiftModel");
        $CourierShiftModel->allowRead();
        $CourierShiftModel->join('courier_list','courier_id','left');
        $CourierShiftModel->join('image_list','courier_list.courier_id=image_holder_id AND image_holder="courier"','left');
        $CourierShiftModel->select("courier_name,image_hash,IF({$this->user_id}=courier_shift_list.owner_id,1,0) users_shift");
        $open_shifts=$CourierShiftModel->listGet((object)['shift_status'=>'open']);
        
        $isShiftOpened=false;
        foreach($open_shifts as $shift){
            if( $shift->users_shift==1 ){
                $isShiftOpened=true;
                break;
            }
        }
        return $isShiftOpened;
    }

    public function onCourierTaxiNotif($notification_level){
        $CourierModel=model("CourierModel");
        $courier=$this->courierGet();

        $CourierModel->itemUpdate((object)[
            'courier_id'=>$courier->courier_id,
            'courier_parttime_notify'=>$notification_level
        ]);
        $buttons = [
            $this->createButton("🚫 Не получать", "onCourierTaxiNotif-off"),
            $this->createButton("🔇 Без звука", "onCourierTaxiNotif-silent"),
            $this->createButton("🔊 Со звуком", "onCourierTaxiNotif-push"),
            $this->createButton("🔔 Рингтон", "onCourierTaxiNotif-ringtone")
        ];
        $rows = array_chunk($buttons, 2);

        $keyboard = [
            "one_time" => false,
            "inline" => true,
            "buttons" => $rows
        ];
        $this->api->setKeyboard($keyboard);

        if($notification_level=='off'){
            $this->api->setText("🚫 Свободные заказы не будут приходить вам.");
            $this->api->messagesSend($this->client_id);

        } else
        if($notification_level=='silent'){
            $this->api->setText("🔇 Свободные заказы будут приходить без звука в VK.");
            $this->api->messagesSend($this->client_id);
        } else 
        if($notification_level=='push'){
            $this->api->setText("🔊 Свободные заказы будут приходить в приложение и VK.");
            $this->api->messagesSend($this->client_id);
        } else 
        if($notification_level=='ringtone'){
            $this->api->setText("🔔 Свободные заказы будут приходить в VK и приложение с рингтоном.");
            $this->api->messagesSend($this->client_id);
        }
        return $this->api->setText("Статус свободных заказов обновлён!");
    }
    
    public function onCourierJobTake($order_id){
        $OrderModel=model("OrderModel");
        $DeliveryJobModel=model("DeliveryJobModel");

        $job = $DeliveryJobModel->itemGet(null, $order_id);
        
        $OrderModel->select("JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.info_for_courier')) info");
        $OrderModel->where('order_id', $job->order_id);
        
        $job->job_data = json_decode($job->job_data);
        $job->info = json_decode($OrderModel->get()->getRow('info')??'');
        $job->info->tariff_info = strip_tags($job->info->tariff_info);
        $text=View('messages/vk/jobItemConfirmation',['job' => $job]);

        $keyboard = [
            "one_time" => false,
            "inline" => true,
            "buttons" => [
                [ $this->createButton("✅ Да, взять задание", "onCourierJobConfirm-{$job->order_id}") ]
            ]
        ];
        $this->api->setKeyboard($keyboard);
        $this->api->setText($text);
        $this->api->messagesSend($this->client_id);
        return false;
    }

    public function onCourierJobConfirm($order_id){
        if( !courdo() ){
            $this->api->setText("Не удалось начать задание!");
            $this->api->messagesSend($this->client_id);
            return false;
        }
        $OrderModel=model("OrderModel");
        $OrderGroupMemberModel=model('OrderGroupMemberModel');

        $isSearching4Courier=$OrderGroupMemberModel->isMemberOf($order_id,'delivery_search');
        
        if( !$isSearching4Courier ){
            $this->api->setText("Курьер уже не требуется.");
            $this->api->messagesSend($this->client_id);
            return false;
        }
        $courier=$this->courierGet();
        
        $courierData=(object)[
            'order_courier_id'=>$courier->courier_id,
            'order_courier_admins'=>$courier->owner_id
        ];
        $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');
        $OrderModel->allowWrite();//allow modifying order once
        $OrderModel->update($order_id,(object)['order_courier_id'=>$courier->courier_id,'order_courier_admins'=>$courier->owner_id]);
        $OrderModel->itemUpdateOwners($order_id);
        $OrderModel->itemCacheClear();
        $result= $OrderModel->itemStageAdd( $order_id, 'delivery_found', $courierData );
        if($result=='ok'){
            $this->onOrderOpen($order_id);
            return true;
        }
        $this->api->setText("Не удалось начать задание!");
        $this->api->messagesSend($this->client_id);
        return false;
    }

    

    private $courier;
    private function courierGet(){
        $CourierModel=model('CourierModel');
        return $CourierModel->itemGet(null,'basic');
    }
    private function isCourier(){
        $user=$this->userGet();
        $isCourier=str_contains($user->member_of_groups->group_types??'','courier');
        if( !$isCourier ){
            return false;
        }
        return $this->courierGet()?1:0;
    }
    private function isCourierReady(){
        if( !$this->isCourier() ){
            return false;
        }
        return ($this->courierGet()->status_type??'')=='ready';
    }
    private function isCourierBusy(){
        if( !$this->isCourier() ){
            return false;
        }
        return ($this->courierGet()->status_type??'')=='busy';
    }
    private function isCourierIdle(){
        if( !$this->isCourier() ){
            return false;
        }
        return in_array($this->courierGet()->status_type??null,['idle','taxi']);
    }
    private function isCourierTaxi(){
        if( !$this->isCourier() ){
            return false;
        }
        return in_array($this->courierGet()->status_type??null,['taxi']);
    }
    private function courierSetReady(){
        $courier=$this->courierGet();
        if( $courier->is_disabled==1 || $courier->deleted_at ){
            $this->api->setText("Не удалось открыть смену: анкета курьера не активна. Обратитесь к администратору");
            return $this->api->messagesSend($this->client_id);
        }
        if( !$this->isCourierBusy() && !$this->isCourierReady() ){
            $CourierModel=model("CourierModel");
            $result = $CourierModel->itemShiftOpen($courier->courier_id);
            
            session()->remove('courier');
        }
    }
    private function courierSetIdle(){
        $courier=$this->courierGet();
        if( $this->isCourierIdle() || $this->isCourierBusy() ){
            return false;
        }
        $lastLocationUpdateMessage=session()->get('lastLocationUpdateMessage');
        if($lastLocationUpdateMessage){
            session()->remove('lastLocationUpdateMessage');
        }
        $courier=$this->courierGet();
        $CourierModel=model("CourierModel");
        //$CourierModel->itemUpdateStatus($courier->courier_id,'idle');
        $CourierModel->itemShiftClose($courier->courier_id);
        session()->remove('courier');
        return true;
    }
}
