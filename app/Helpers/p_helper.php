<?php

function p( $object ){
    header("Content-Type:text/plain");
    print '<pre>';
    print_r($object);die;
}
function pl( $object, $die=false ){
    if(is_array($object) || is_object($object)){
        log_message('error','PL HELPER '.json_encode($object,JSON_UNESCAPED_UNICODE));
    } else {
        log_message('error','PL HELPER '.$object);
    }
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