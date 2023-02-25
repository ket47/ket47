<?php
namespace App\Libraries\Telegram;
trait SystemTrait{
    private $systemButtons=[
        ['isAdmin',  'onSystemMetrics',         "📲 Метрика"],
        ['isAdmin',  'onSystemRegistrations',   "👦🏻 Регистрации"],
    ];
    public function systemButtonsGet(){
        return $this->systemButtons;
    }

    private function isAdmin(){
        if( session()->get('user_id')!=-100 ){
            return false;
        }

        $user=$this->userGet();
        $isAdmin=str_contains($user->member_of_groups->group_types??'','admin');
        if( !$isAdmin ){
            return false;
        }
        return true;
    }

    private function onSystemMetrics(){
        if(!$this->isAdmin()){
            return false;
        }
        $MetricModel=model('MetricModel');
        $MetricModel->orderBy('created_at DESC')->limit(10);
        $MetricModel->join('metric_media_list','come_media_id=media_tag','left');

        $metrics=$MetricModel->listGet();

        $context=[
            'coming_list'=>$metrics
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