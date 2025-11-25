<?php
namespace App\Models;

trait DeliveryJobNotificationTrait{
    /**
     * Gets summary of courier jobs
     * assigned 
     */
    private function itemCourierSummaryGet( array $filter=null ){
        if($filter['courier_id']??null){
            $this->where('courier_id',$filter['courier_id']);
        }
        if($filter['is_free']??null){//courier has no current jobs
            $this->having('current_count',0);
        }
        if($filter['is_expected']??null){//courier has next awaited jobs
            $this->having('awaited_count>0');
        }
        $this->select('courier_id');
        $this->select("SUM(stage IN ('awaited')) awaited_count");
        $this->select("SUM(stage IN ('assigned','started')) current_count");
        $this->groupBy('courier_id');
        return $this->get()->getResult();
    }

    /**
     * Checks if courier can be ready and notifies it if necessary
     * returns isCourierReadyForNext
     */
    private function itemNextCheck( int $courier_id ):bool{
        $summaries=$this->itemCourierSummaryGet(['courier_id'=>$courier_id]);
        $summary=array_shift($summaries);
        if($summary->current_count??0){
            return false;
        }
        if($summary->awaited_count??0){
            $awaitedNext=$this->itemNextGet($courier_id);
            $this->itemNextNotify($awaitedNext,$summary->awaited_count);
        }
        return true;
    }

    /**
     * Reminds free couriers about awaiting jobs
     * should be called from cronjob
     */
    public function itemNextRemind(){ 



        $this->offlineShiftSmsBackup();






        $freeCouriers=$this->itemCourierSummaryGet(['is_free'=>1,'is_expected'=>1]);
        if(!$freeCouriers){
            return false;
        }
        foreach($freeCouriers as $free){
            if(!$free->courier_id){
                continue;
            }
            $awaitedNext=$this->itemNextGet($free->courier_id);
            $this->itemNextNotify($awaitedNext,$free->awaited_count);
        }
        return true;
    }

    public function offlineShiftSmsBackup(){
        $CourierShiftModel=model('CourierShiftModel');
        $filter=(object)[
            'shift_status'=>'open',
            'updated_before'=>date('Y-m-d H:i:s',time()-10*60)//10 min
        ];
        $CourierShiftModel->allowRead();
        $shifts=$CourierShiftModel->listGet($filter);
        if( !$shifts ){
            return false;
        }
        //only stalled shifts
        foreach($shifts as $shift){
            $this->offlineShiftSmsBackupSend( $shift );
        }
    }
    private function offlineShiftSmsBackupSend( $shift ){
        $this->where('courier_id',$shift->courier_id);
        $this->join('order_list','order_id');
        $this->select("job_name,order_data->>'$.info_for_courier' info");
        $this->orderBy('start_plan');
        $jobs=$this->get()->getResult();



        $route_text='';
        $i=1;
        foreach($jobs as $job){
            $info=json_decode($job->info);
            $route_text.="\n{$i})".substr($job->job_name,0,5);
            $route_text.="\n".($info->supplier_phone??'')." ".($info->supplier_location_address??'');
            $route_text.="\n".($info->customer_phone??'')." ".($info->customer_location_address??'');
            $i++;
        }



        $file_message_path='../writable/cache/job_route_hash_'.$shift->owner_id.'.txt';
        $route_hash=md5($route_text);
        $old_route_hash=@file_get_contents($file_message_path);
        if($route_hash==$old_route_hash){
            $route_text.="\n\nSAME";
            return;
        }
        file_put_contents($file_message_path,$route_hash);


        $transport="telegram";
        $updated_before=date('Y-m-d H:i:s',time()-15*60);
        if($shift->updated_at<$updated_before){
            $transport.=",sms";
        }
        $messages[]=(object)[
            'message_reciever_id'=>"41,$shift->owner_id",
            'message_transport'=>$transport,//
            'message_text'=>$route_text,
            'telegram_options'=>[
                'opts'=>[
                    'disable_notification'=>1,
                ]
            ]
        ];
        jobCreate([
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[ $messages ] ]
                ]
        ]);
    }

    /**
     * Gets next awaited job of courier
     */
    private function itemNextGet( int $courier_id ):object{
        $this->where('courier_id',$courier_id);
        $this->whereIn('stage',['awaited']);
        $this->select('job_name,owner_id,order_id,stage');
        $this->orderBy('start_plan');
        $this->limit(1);
        return $this->get()->getRow();
    }

    /**
     * Sends notification to owner of job
     */
    private function itemNextNotify( object $awaitedNext=null, int $awaitedCount  ):bool{
        if( !$awaitedNext || !$awaitedCount ){
            return false;
        }
        $message=(object)[
            'message_reciever_id'=>$awaitedNext->owner_id,
            'message_transport'=>'push,telegram',//
            'message_data'=>[
                'type'=>'flash',
                'title'=>'Следующее задание',
                'link'=>getenv('app.frontendUrl').'order/order-list',
                'sound'=>'long.wav',
            ],
            'telegram_options'=>[
                'buttons'=>[['',"onCourierJobTake-{$awaitedNext->order_id}",'🚀 Взять']]
            ],
            'template'=>'messages/events/on_delivery_job_available_sms',
            'context'=>[
                'awaitedCount'=>$awaitedCount,
                'awaitedNext'=>$awaitedNext
            ]
        ];

        //tmp copy to admin
        $courier_name=model('CourierModel')->where('owner_id',$awaitedNext->owner_id)->select('courier_name')->get()->getRow('courier_name');
        $copy=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'telegram',
            'message_text'=>"{$courier_name} уведомлен о {$awaitedNext->job_name} #{$awaitedNext->order_id}",
            'telegram_options'=>[
                'opts'=>[
                    'disable_notification'=>1,
                ]
            ],
        ];
        jobCreate([
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[ [$message,$copy] ] ]//
                ]
        ]);
        return true;
    }
}