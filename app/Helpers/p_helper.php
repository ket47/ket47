<?php

function p( $object ){
    header("Content-Type:text/plain");
    $args = func_get_args();
    if( count($args)==1 && !is_array($args[0]) && !is_object($args[0]) ){
        echo 'P HELPER '.$args[0];
        return;
    }
    echo json_encode($args,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
function pl(){
    $args = func_get_args();
    if( count($args)==1 && !is_array($args[0]) && !is_object($args[0]) ){
        log_message('error','PL HELPER '.$args[0]);
        return;
    }
    log_message('error','PL HELPER '.json_encode($args,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function e( $model ){
    $err=$model->errors();
    if(!$err){
        return;
    }
    header("Content-Type:text/plain");
    print '<pre>';
    print_r($err);die;
}



function tl(){
    $args = func_get_args();
    if( count($args)==1 && !is_array($args[0]) && !is_object($args[0]) ){
        $text='TL HELPER '.$args[0];
    } else {
        $text='TL HELPER '.json_encode($args,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    $message=(object)[
        'message_reciever_id'=>41,
        'message_transport'=>'telegram',//
        'message_text'=>$text,
        'telegram_options'=>[
            'opts'=>[
                'disable_notification'=>1,
            ]
        ]
    ];
    jobCreate([
        'task_programm'=>[
                ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[ [$message] ] ]
            ]
    ]);
}