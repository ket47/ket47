<?php

function p( $object ){
    header("Content-Type:text/plain");
    print_r($object);die;
}