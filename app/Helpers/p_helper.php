<?php

function p( $object ){
    header("Content-Type:text/plain");
    print '<pre>';
    print_r($object);die;
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