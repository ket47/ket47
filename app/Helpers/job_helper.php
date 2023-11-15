<?php

function jobCreate($job_input){
    $job=is_array($job_input)?(object)$job_input:$job_input;
    $priority=$job->task_priority??'normal';
    $start_time=$job->task_next_start_time??0;
    $job_json=json_encode($job);
    require_once APPPATH.'/ThirdParty/Credis/Client.php';
    $predis = new \Credis_Client();
    if($start_time>0){
        $predis->zAdd("queue.delayed", $start_time, $job_json);
    } else {
        $predis->rpush("queue.priority.{$priority}", $job_json);
    }
}