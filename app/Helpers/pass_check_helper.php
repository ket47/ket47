<?php

function pass_check($password){
    $uppercase = preg_match('@[A-ZА-Я]@', $password);
    $lowercase = preg_match('@[a-zа-я]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $specialChars = preg_match('@[^\w]@', $password);
    if( !$lowercase || !$number || strlen($password) < 4 ) {
        return false;
    }
    return true;
}
