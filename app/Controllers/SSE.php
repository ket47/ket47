<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class SSE extends BaseController
{
    public function index()
    {
        $user_id = 0;
        ini_set('max_execution_time', 0);
        header("Cache-Control: no-cache");
        header('Connection: keep-alive');
        header("Content-Type: text/event-stream");
        $i = 0;
        while (1) {
            $i++;
            $updates = $this->getUpdates($user_id);
            if(!empty($updates)){
                foreach($updates as $update){
                    echo "event:".$update['code']."\n";
                    echo "data:".json_encode($update)."\n\n";
                }
            }
            echo str_pad('',65536)."\n";
            if (ob_get_contents()) ob_get_flush();
            flush();
            if (connection_aborted()){
                exit();
            }
            sleep(1);
        }
    }
    private function getUpdates($user_id)
    {
        return [[ 
            'id' => '1',
            'code' => 'achievement',
            'data' => ['text' => 'hello']
        ]];
    }
}