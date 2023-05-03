<?php

function p( $object ){
    header("Content-Type:text/plain");
    print '<pre>';
    print_r($object);die;
}
function pl( $object, $die=false ){
    log_message('error','PL HELPER '.json_encode($object,JSON_UNESCAPED_UNICODE));
    if($die)
        die();
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