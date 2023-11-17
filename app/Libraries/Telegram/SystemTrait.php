<?php
namespace App\Libraries\Telegram;
trait SystemTrait{
    private $systemButtons=[
        ['isAdmin',  'onSystemMetricsDay',      "📲 Метрика день"],
        ['isAdmin',  'onSystemMetricsWeek',     "📲 Метрика нед."],
        ['isAdmin',  'onSystemMetricsMonth',    "📲 Метрика мес."],
        ['isAdmin',  'onSystemRegistrations',   "👦🏻 Регистрации"],

        // ['isAdmin',  'onSystemHeavyDelivery-0',   "🌤️ Сброс"],
        // ['isAdmin',  'onSystemHeavyDelivery-1',   "⛈️ Дост +60"],
        // ['isAdmin',  'onSystemHeavyDelivery-2',   "⛈️ Дост +100"],
        // ['isAdmin',  'onSystemHeavyDelivery-3',   "⛈️ Дост +150"],
    ];


    public function systemStatusGet(){
        if( !$this->isAdmin() ){
            return '';
        }

        $PrefModel=model('PrefModel');
        $delivery_heavy_level=$PrefModel->itemGet("delivery_heavy_level",'pref_value');
        $delivery_heavy=[
            'delivery_heavy_level'=>$delivery_heavy_level,
            'delivery_heavy_cost'=>$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level}",'pref_value'),
            'delivery_heavy_bonus'=>$PrefModel->itemGet("delivery_heavy_bonus_{$delivery_heavy_level}",'pref_value')
        ];

        $context=[
            'delivery_heavy'=>$delivery_heavy
        ];
        return View('messages/telegram/systemStatus',$context);
    }


    public function systemButtonsGet(){
        $PrefModel=model('PrefModel');
        $delivery_heavy_level=$PrefModel->itemGet("delivery_heavy_level",'pref_value');
        $delivery_heavy_level_prev=$delivery_heavy_level-1;
        $delivery_heavy_level_next=$delivery_heavy_level+1;
        if($delivery_heavy_level_prev==0){
            $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_0",'pref_value');
            $this->systemButtons[]=['isAdmin',  "onSystemHeavyDelivery-0",   "🌤️ Доставка норм."];
        }
        if($delivery_heavy_level_prev>0){
            $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level_prev}",'pref_value');
            $this->systemButtons[]=['isAdmin',  "onSystemHeavyDelivery-{$delivery_heavy_level_prev}",   "☁️ Доставка +{$delivery_heavy_cost}"];
        }
        if($delivery_heavy_level_next<=3){
            $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level_next}",'pref_value');
            $this->systemButtons[]=['isAdmin',  "onSystemHeavyDelivery-{$delivery_heavy_level_next}",   "⛈️ Доставка +{$delivery_heavy_cost}"];
        }
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
        if(!$this->isAdmin()){
            return false;
        }
        if($delivery_heavy_level<0 || $delivery_heavy_level>3){
            return $this->sendHTML('wrong level','','system_message');
        }
        $PrefModel=model('PrefModel');
        $PrefModel->save([
            'pref_name'=>'delivery_heavy_level',
            'pref_value'=>$delivery_heavy_level
        ]);

        $this->sendMainMenu();

        $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level}",'pref_value');
        $delivery_heavy_bonus=$PrefModel->itemGet("delivery_heavy_bonus_{$delivery_heavy_level}",'pref_value');
        $context=[
            'delivery_heavy_level'=>$delivery_heavy_level,
            'delivery_heavy_cost'=>$delivery_heavy_cost,
            'delivery_heavy_bonus'=>$delivery_heavy_bonus,
        ];
        $heavy_html=View('messages/telegram/deliveryHeavy',$context);
        $content=[
            'chat_id'=>getenv("telegram.adminChatId"),
            'text'=>$heavy_html,
            'parse_mode'=>'HTML'
        ];
        return  $this->sendMessage($content,'','system_message');
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