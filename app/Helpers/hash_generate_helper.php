<?php

function generate_hash( $length=4, $range='alphanum' ){
    if( $range=='alphanum' ){
        $alphabet = 'abcdefghijklmnopqrstuvwxyz1234567890';//ABCDEFGHIJKLMNOPQRSTUVWXYZ
    } else {
        $alphabet = '1234567890';
    }
    $password = array(); 
    $alpha_length = strlen($alphabet) - 1; 
    for ($i = 0; $i < $length; $i++) 
    {
        $n = random_int(0, $alpha_length);
        $password[] = $alphabet[$n];
    }
    return implode($password);
}