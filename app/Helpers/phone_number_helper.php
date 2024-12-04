<?php

function clearPhone( $phone_number ){
    if(!$phone_number){
        return '';
    }
    return '7'.substr(preg_replace('/[^\d]/', '', $phone_number),-10);
}