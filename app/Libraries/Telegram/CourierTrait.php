<?php
namespace App\Libraries\Telegram;
trait CourierTrait{
    private $courierButtons=[
        ['isCourierReady',  'onCourierJobsGet',   "🔍 Список заданий"],
        ['isCourierReady',  'onCourierSetIdle',   "🏁 Завершить смену"],
    ];

    public function courierStatusGet(){
        if( !$this->isCourier() ){
            return '';
        }
        $courier=$this->courierGet();
        $user=$this->userGet();
        $CourierModel=model("CourierModel");
        $jobs=$CourierModel->listJobGet($courier->courier_id);

        $context=[
            'courier'=>$courier,
            'user'=>$user,
            'job_count'=>is_array($jobs)?count($jobs):0
        ];
        return View('messages/telegram/courierStatus',$context);
    }
    
    public function onCourierSetIdle(){
        $user=$this->userGet();
        if( $this->isCourierBusy() ){
            $this->sendMainMenu();
            return $this->sendText("{$user->user_name}, нельзя закрыть смену во время задания",'','courier_message');
        }
        if( $this->isCourierIdle() ){
            $this->sendMainMenu();
            return  $this->sendText("{$user->user_name}, ваша смена уже была закрыта",'','courier_message');
        }
        return $this->courierSetIdle();
    }

    public function onCourierUpdateLocation($location){
        $lastUpdateMsg=session()->get('lastLocationUpdateMessage');
        if($lastUpdateMsg && ($lastUpdateMsg['updated_at']??0)>time()-30){
            //to many requests
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
        if( $location['longitude']<$bound_longitude_min || $location['longitude']>$bound_longitude_max ){
            $location['longitude']= ($bound_longitude_min+$bound_longitude_max)/2;//set at midpoint
            $location_is_distorted=1;
        }
        if( $location['latitude']<$bound_latitude_min || $location['latitude']>$bound_latitude_max ){
            $location['latitude']= ($bound_latitude_min+$bound_latitude_max)/2;//set at midpoint
            $location_is_distorted=1;
        }

        $courier=$this->courierGet();
        if( !$location_is_distorted ){
            $CourierShiftModel=model('CourierShiftModel');
            $CourierShiftModel->fieldUpdateAllow('actual_longitude');
            $CourierShiftModel->fieldUpdateAllow('actual_latitude');
            $CourierShiftModel->allowWrite();
            $CourierShiftModel->where('courier_id',$courier->courier_id)->where('shift_status','open');
            $CourierShiftModel->update(null,['actual_longitude'=>$location['longitude'],'actual_latitude'=>$location['latitude']]);
        }
        $courier_location=[
            'location_holder'   =>'courier',
            'location_holder_id'=>$courier->courier_id,
            'location_longitude'=>$location['longitude'],
            'location_latitude' =>$location['latitude']
        ];
        $LocationModel=model('LocationModel');
        $result= $LocationModel->itemAdd($courier_location);
        if( $result!='ok' ){
            $user=$this->userGet();
            $this->sendText("{$user->user_name}, не удалось обновить ваше местоположение",'','courier_message');
        }
        $incomingData=$this->Telegram->IncomingData();
        session()->set('lastLocationUpdateMessage',[
            'message_id'=>$incomingData['message_id'],
            'chat_id'=>$incomingData['chat']['id'],
            'updated_at'=>time()
            ]
        );

        // $content=$location;
        // $content['proximity_alert_radius']="100";
        // $content['disable_notification']=1;
        // $content['reply_markup']=$this->Telegram->buildInlineKeyBoard([[
        //     $this->Telegram->buildInlineKeyboardButton("Курьер: {$courier->courier_name}",'',"onNoop")
        // ]]);

        // $content['chat_id']=getenv("telegram.adminChatId");
        // $this->sendLocation( $content, null, 'copy_to_admin'.$courier->courier_id );
    }

    public function onCourierJobsGet(){
        $CourierModel=model("CourierModel");
        $courier=$this->courierGet();
        $jobs=$CourierModel->listJobGet($courier->courier_id);
        if( !count($jobs) ){
            $this->sendText("Нет доступных заданий",'','courier_message');
            return true;
        }
        foreach($jobs as $job){
            $html="<b>Задание <u>#{$job->order_id}</u></b>\nЗабрать из: {$job->store_name}\nАдрес:<a href='https://yandex.ru/maps/?pt={$job->location_longitude},{$job->location_latitude}&z=19&l=map,trf'>{$job->location_address}</a>";
            $opts=[
                'disable_web_page_preview'=>1
            ];
            if( $this->isCourierReady() ){
                $opts['reply_markup']=$this->Telegram->buildInlineKeyBoard([[
                    $this->Telegram->buildInlineKeyboardButton("\xF0\x9F\x9A\x80 Взять задание",'',"onCourierJobStart-{$job->order_id}")
                ]]);
            }
            $this->sendHTML($html,$opts);
        }
    }
    public function onCourierJobStart($order_id){
        $courier=$this->courierGet();
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemJobStart($order_id,$courier->courier_id);
        if($result=='ok'){
            $this->onOrderOpen($order_id);
            return true;
        }
        $error=$result;
        if($result=='notsearching'){
            $error='Курьер уже не требуется.';
        }
        if($result=='notready'){
            $error='Ваш статус или ЗАНЯТ или ОТБОЙ.';
        }
        if($result=='notactive'){
            $error='Ваша анкета курьера не активна.';
        }
        $this->sendText("Не удалось начать задание! ".$error,'','courier_message');
    }




    private function courierGet(){
        /**
         * If status has been changed outside of bot then cache data become outdated!!!
         */
        //$courier=session()->get('courier');
        //if(!$courier){
            $CourierModel=model('CourierModel');
            $courier=$CourierModel->itemGet(null,'basic');
        //    session()->set('courier',$courier);
        //}
        return $courier;
    }
    private function isCourier(){
        if( !$this->isUserSignedIn() ){
            return false;
        }
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
        return ($this->courierGet()->status_type??'')=='idle';
    }
    private function courierSetReady(){
        $courier=$this->courierGet();
        if( $this->isCourierIdle() ){
            $user=$this->userGet();
            $CourierModel=model("CourierModel");
            //$CourierModel->itemUpdateStatus($courier->courier_id,'ready');
            $CourierModel->itemShiftOpen($courier->courier_id);
            session()->remove('courier');
            return $this->sendMainMenu();
        }
    }
    private function courierSetIdle(){
        $courier=$this->courierGet();
        if( $this->isCourierIdle() || $this->isCourierBusy() ){
            return false;
        }
        $lastLocationUpdateMessage=session()->get('lastLocationUpdateMessage');
        if($lastLocationUpdateMessage){
            $this->Telegram->deleteMessage($lastLocationUpdateMessage);
            session()->remove('lastLocationUpdateMessage');
        }
        $courier=$this->courierGet();
        $CourierModel=model("CourierModel");
        //$CourierModel->itemUpdateStatus($courier->courier_id,'idle');
        $CourierModel->itemShiftClose($courier->courier_id);
        session()->remove('courier');
        return $this->sendMainMenu();
    }
}
