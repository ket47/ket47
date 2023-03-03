<?php
namespace App\Libraries\Telegram;
trait SystemTrait{
    private $systemButtons=[
        ['isAdmin',  'onSystemMetricsDay',      "📲 Метрика день"],
        ['isAdmin',  'onSystemMetricsWeek',     "📲 Метрика нед."],
        ['isAdmin',  'onSystemRegistrations',   "👦🏻 Регистрации"],
    ];
    public function systemButtonsGet(){
        return $this->systemButtons;
    }

    private function isAdmin(){
        $user=$this->userGet();
        $isAdmin=str_contains($user->member_of_groups->group_types??'','admin');
        if( $isAdmin ){
            return true;
        }
        return false;
    }
    private function onSystemMetricsDay(){
        $this->onSystemMetrics( "DAY" );
    }
    private function onSystemMetricsWeek(){
        $this->onSystemMetrics( "WEEK" );
    }
    private function onSystemMetrics( $interval ){
        if(!$this->isAdmin()){
            return false;
        }
        $MetricModel=model('MetricModel');
        $MetricModel->limit(20);
        $MetricModel->join('metric_media_list','come_media_id=media_tag','left');
        $MetricModel->select('come_referrer,come_media_id,media_name,COUNT(*) metric_count');
        $MetricModel->groupBy('CONCAT(come_referrer,"||",come_media_id)');
        $MetricModel->where("created_at between date_sub(now(),INTERVAL 1 $interval) and now()");
        $MetricModel->orderBy('metric_count DESC');
        $metrics=$MetricModel->listGet();
        $MetricModel->where("created_at between date_sub(now(),INTERVAL 1 $interval) and now()");
        $MetricModel->groupBy('device_platform');
        $MetricModel->select('device_platform,COUNT(*) metric_count');
        $MetricModel->orderBy('metric_count DESC');
        $devices=$MetricModel->listGet();

        $context=[
            'coming_list'=>$metrics,
            'device_list'=>$devices
        ];
        $metric_html=View('messages/telegram/metricsReport',$context);
        return  $this->sendHTML($metric_html,'','system_message');
    }

    private function onSystemRegistrations(){
        if(!$this->isAdmin()){
            return false;
        }
        $UserModel=model('UserModel');
        $UserModel->orderBy('created_at DESC')->limit(10);

        $users=$UserModel->listGet();

        $context=[
            'user_list'=>$users
        ];
        $metric_html=View('messages/telegram/metricRegistrations',$context);
        return  $this->sendHTML($metric_html,'','system_message');
    }

}