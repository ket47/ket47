<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Metric extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        $ua=$this->request->getUserAgent();
        $metricsHeader=(object)[];
        $metricsHeader->come_media_id=    $this->request->getPost('come_media_id');
        $metricsHeader->come_inviter_id=  $this->request->getPost('come_inviter_id');
        $metricsHeader->come_referrer=    $this->request->getPost('come_referrer');
        $metricsHeader->come_url=         $this->request->getPost('come_url');
        $metricsHeader->device_is_mobile= $ua->isMobile();
        $metricsHeader->device_platform=  $ua->getPlatform();

        $MetricModel=model('MetricModel');
        $metricsHeaderId=$MetricModel->itemSave($metricsHeader);
        if($metricsHeaderId??0){
            return $this->respondUpdated($metricsHeaderId);
        }
        return $this->fail(0);
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
 

    private $action_emoji = [
        'homeget' => '🏠 Главная',
        'storeget' => '🏪',
        'productget' => '🍔',
        'orderlistget'=>'Заказы',
        'authin' => '🚶➡️ Вход',
        'authup' => '➕👨 Регистрация',
        'authout' => '⬅️🚶 Выход',
        'ordercreate' => '🛒 (Оформление)',
        'orderstart' => '🛒 (В обработке)',
        'searchget' => '🔎',
        'locationswitch' => '🔄 Адрес' 
    ];
    private $user_group_emoji = [
        'admin' => '👑',
        'courier' => '🛵',
        'customer' => '👨',
        'supplier' => '🏪',
        'guest' => '👤'
    ];
    private $device_platform = [
        'iOS' => '🍏 iOS',
        'Windows 10' => '🖥️ Windows 10',
        'Windows 7' => '🖥️ Windows 7',
        'Windows 8.1' => '🖥️  Windows 8.1',
        'Android' => '🤖 Android',
        'Linux' => '🐧 Linux',
        'Mac OS X' => '🍏 MacOS'
    ];
    
    public function buildView()
    {
        $MetricActModel=model('MetricActModel');
        $filter=(object)[
            'finish_at'     => $this->request->getVar('finish_at'),
            'user_group'    => $this->request->getVar('user_group'),
            'order_only'    => $this->request->getVar('order_only') == 'true',
            'page'          => $this->request->getVar('page')
        ];
        $filter = $this->filterDatePageRecalc($filter);
        if(!$filter){
            return $this->respond('');
        }
        $rawData = $MetricActModel->getHourlyUserActivity($filter);
        $timeline = [];
        foreach ($rawData as $key => $row) {
            $hour     = $row['hour_slot'];
            $session  = $row['metric_id'];
            $user     = ($row['user_name'] ?? 'Гость') ;
            $user_phone = $row['user_phone'];
            $come_referrer  = $this->parseComeReferrer($row['come_referrer']);
            $timeline[$hour]['hour_slot'] = $this->humanizeDateTime($row['hour_slot']);
            $timeline[$hour]['list'][$session] = $timeline[$hour]['list'][$session] ?? [
                'user' => $user,
                'user_phone' => $user_phone,
                'user_avatar' => $this->user_group_emoji[$row['group_type']] ?? '', 
                'user_orders' => $row['user_orders'],
                'come_referrer' => $come_referrer,
                'session_start' => $row['created_at'],
                'device_platform' => $this->device_platform[$row['device_platform']] ?? $row['device_platform'],
                'actions' => []
            ];
    
            $timeline[$hour]['list'][$session]['actions'][] = [
                'type' => $this->action_emoji[$row['act_group'].$row['act_type']] ?? $row['act_group'].$row['act_type'],
                'desc' => $row['act_description'],
                'time' =>  $this->formatDuration($timeline[$hour]['list'][$session]['session_start'], $row['created_at']),
                'act_result' => $row['act_result'],
            ];
        }
        krsort($timeline);
        return $this->respond(view('admin/metric_activity', ['timeline' => $timeline]));
    }
    private $url_labels = [
        'google.com' => 'Google',
        'ok.ru' => 'Одноклассники',
        'tezkel.com' => 'Tezkel',
        'l.instagram.com' => 'Instagram',
        'yandex.ru' => 'Яндекс',
        'yandex.kz' => 'Яндекс',
        'yandex.by' => 'Яндекс',
    ];
    private function parseComeReferrer($come_referrer)
    {
        if(empty($come_referrer)) return;
        $url = ($come_referrer ?? '') ;
            
        $host  = parse_url($url, PHP_URL_HOST) ?? '';
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        $host  = preg_replace('/^www\./', '', $host);
        
        $result = $this->url_labels[$host] ?? $host;
        if($host === 'tezkel.com' && !empty($query)){
            $result .= ' ('.$query.')';
        }
        return $result;
    }
    private function formatDuration($start_date, $end_date) {
        $time = strtotime($end_date) - strtotime($start_date);
        $hours = floor($time / 3600);
        $minutes = floor(($time % 3600) / 60);
        $seconds = floor(($time % 60));
    
        $parts = [''];
    
        if ($hours > 0) {
            $parts[] = $hours . ' ч.';
        }
    
        if ($minutes > 0 || $hours === 0) {
            $parts[] = $minutes . ' м.';
        }
        if ($seconds > 0 || $minutes === 0) {
            $parts[] = $seconds . ' с.';
        }
    
        return implode(' ', $parts);
    }
    private function humanizeDateTime($datetime) {
        $ts = strtotime($datetime);
        $months = [1 => 'января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];

        $days = ['воскресенье','понедельник','вторник','среда','четверг','пятница','суббота'];

        $hour = date('H:i', $ts);
        $day = date('j', $ts);
        $month = $months[date('n', $ts)];
        $weekday = $days[date('w', $ts)];

        return "$hour $day $month ($weekday)";
    }
    private function filterDatePageRecalc($filter){
        $step = 6 * 3600;
        $finishTs = strtotime($filter->finish_at. ' +1 day');
        if($finishTs > strtotime(date("Y-m-d H:i:s"))) {
            $finishTs = strtotime(date("Y-m-d H:i:s"));
        }
        $startTs  = $finishTs - ($step * ($filter->page + 1));
        $endTs    = $finishTs - ($step * $filter->page);
    
        if ($startTs >= $endTs) return null;
    
        $filter->start_at  = date('Y-m-d H:i:s', $startTs);
        $filter->finish_at = date('Y-m-d H:i:s', $endTs);
        return $filter;
    }
    
}
