<?php

function jobCreate($job){
    require_once '../app/ThirdParty/Credis/Client.php';
    $predis = new \Credis_Client();
    $predis->rpush('queue.priority.normal', $job);
}