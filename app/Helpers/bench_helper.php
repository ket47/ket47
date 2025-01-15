<?php

function bench($mark){
    $timer=session()->get('timer')??0;
    $now=microtime(1);
    session()->set('timer',$now);
    pl("TIMER $mark ".round(1000*($now-$timer))."ms ".date('i:s'));
}


session()->set('timer',microtime(1));