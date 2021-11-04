<?php
//apache_setenv('no-gzip', 1); 
ini_set('zlib.output_compression', 0); 
ini_set('implicit_flush', 1);

function finishHere1($content){
        ob_start();
        echo "json_encode()";
        header('Connection: close');
        header('Content-Length: '.ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();
}

function finishHere($content){
    ignore_user_abort(1);
    ob_end_clean();
    ob_end_flush(); // This line does the trick
    header("Content-Encoding: none");
    header("Connection: close");
    http_response_code(200);
    header('Content-type: application/json; charset=utf-8');
    ignore_user_abort(); // optional
    ob_start();
    echo $content;
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush(); // Again for print content
    flush(); // Unless both are called !
    session_write_close();
}

ob_start();

finishHere('{}');
set_time_limit(30);

sleep(2);
file_put_contents('aftertest'.time().'txt', 'hello again: ' . date('Y-m-d H:i:s'));
sleep(2);