<?php
namespace App\Libraries\Telegram;
trait SystemTrait{
    private $systemButtons=[
        ['isAdmin',  'onSystemMetricsDay',      "📲 Метрика день"],
        ['isAdmin',  'onSystemMetricsWeek',     "📲 Метрика нед."],
        ['isAdmin',  'onSystemMetricsMonth',    "📲 Метрика мес."],
        ['isAdmin',  'onSystemRegistrations',   "👦🏻 Регистрации"],

        ['isAdmin',  'onSystemHeavyDelivery-0',   "⛈️ Сброс"],
        ['isAdmin',  'onSystemHeavyDelivery-1',   "⛈️ Дост +60"],
        ['isAdmin',  'onSystemHeavyDelivery-2',   "⛈️ Дост +100"],
        ['isAdmin',  'onSystemHeavyDelivery-3',   "⛈️ Дост +150"],
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

    private function onSystemHeavyDelivery( $delivery_heavy_level=0 ){
        $PrefModel=model('PrefModel');
        $PrefModel->itemUpdateValue('delivery_heavy_level',$delivery_heavy_level);
        $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level}",'pref_value');
        $delivery_heavy_bonus=$PrefModel->itemGet("delivery_heavy_bonus_{$delivery_heavy_level}",'pref_value');
        $context=[
            'delivery_heavy_level'=>$delivery_heavy_level,
            'delivery_heavy_cost'=>$delivery_heavy_cost,
            'delivery_heavy_bonus'=>$delivery_heavy_bonus,
        ];
        $heavy_html=View('messages/telegram/deliveryHeavy',$context);
        return  $this->sendHTML($heavy_html,'','system_message');
    }


    private function onSystemMetricsDay(){
        $this->onSystemMetrics( "DAY" );
    }
    private function onSystemMetricsWeek(){
        $this->onSystemMetrics( "WEEK" );
    }
    private function onSystemMetricsMonth(){
        $this->onSystemMetrics( "MONTH" );
    }
    private function onSystemMetrics( $interval ){
        if(!$this->isAdmin()){
            return false;
        }
        $MetricModel=model('MetricModel');
        $MetricModel->limit(20);
        $MetricModel->join('metric_media_list','come_media_id=media_tag','left');
        $MetricModel->select('come_media_id,media_name,COUNT(*) metric_count');
        $MetricModel->groupBy('come_media_id');
        $MetricModel->where("created_at between date_sub(now(),INTERVAL 1 $interval) and now()");
        $MetricModel->orderBy('metric_count DESC');
        $metrics=$MetricModel->listGet();

        $MetricModel->limit(20);
        $MetricModel->select('come_referrer,COUNT(*) metric_count');
        $MetricModel->groupBy('come_referrer');
        $MetricModel->where("created_at between date_sub(now(),INTERVAL 1 $interval) and now()");
        $MetricModel->orderBy('metric_count DESC');
        $refferrers=$MetricModel->listGet();
        
        $MetricModel->where("created_at between date_sub(now(),INTERVAL 1 $interval) and now()");
        $MetricModel->groupBy('device_platform');
        $MetricModel->select('device_platform,COUNT(*) metric_count');
        $MetricModel->orderBy('metric_count DESC');
        $devices=$MetricModel->listGet();

        $context=[
            'refferrer_list'=>$refferrers,
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
        $UserModel->orderBy('user_list.created_at DESC')->limit(30);
        $UserModel->join('metric_list','user_id','left');
        $UserModel->join('metric_media_list','come_media_id=media_tag','left');

        $users=$UserModel->get()->getResult();

        $context=[
            'user_list'=>$users
        ];
        $metric_html=View('messages/telegram/metricRegistrations',$context);
        return  $this->sendHTML($metric_html,'','system_message');
    }

}