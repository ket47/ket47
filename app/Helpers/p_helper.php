<?php

function p( $object ){
    header("Content-Type:text/plain");
    print '<pre>';
    print_r($object);die;
}