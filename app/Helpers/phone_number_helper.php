<?php

function clearPhone( $phone_number ){
    return '7'.substr(preg_replace('/[^\d]/', '', $phone_number),-10);
}