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