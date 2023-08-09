<?php

function jobCreate($job){
    $priority=is_array($job)?($job['task_priority']??'normal'):($job->task_priority??'normal');
    if( is_array($job) || is_object($job) ){
        $job=json_encode($job);
    }
    //log_message('error', 'jobCreate '.$job);
    require_once APPPATH.'/ThirdParty/Credis/Client.php';
    $predis = new \Credis_Client();
    $predis->rpush("queue.priority.{$priority}", $job);
}