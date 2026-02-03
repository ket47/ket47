<?php

namespace App\Controllers;
require_once '../app/ThirdParty/Credis/Client.php';
use CodeIgniter\HTTP\ResponseInterface;

class ServerEvent extends BaseController
{

    public function test(){
        ?>

            <!DOCTYPE html>
            <html>
            <head>
                <title>SSE Test</title>
                <meta charset="UTF-8">
            </head>
            <body>
            <h1>Server-Sent Events Demo</h1>
            <ul id="result"></ul>

            <script>
            const evtSource = new EventSource("/ServerEvent/sse");

            // evtSource.onmessage = function(event) {
            //     const data = JSON.parse(event.data);
            //     document.getElementById("result").innerHTML += 
            //         `<p>[${data.timestamp}] ${data.message} (value: ${data.value})</p>`;
            // };

            evtSource.addEventListener("update", function(e) {
                const data = JSON.parse(e.data);
                console.log("Structured update received:", data);

                const newElement = document.createElement("li");
                newElement.textContent = `message: ${e.data}`;
                eventList.appendChild(newElement);
            });

            evtSource.onerror = function() {
                console.log("SSE connection error");
            };


            const eventList = document.querySelector("ul");

            evtSource.onmessage = (e) => {
                console.log("SSE onmessage"+e.data);
                const newElement = document.createElement("li");

                newElement.textContent = `message: ${e.data}`;
                eventList.appendChild(newElement);
            };
            </script>
            </body>
            </html>

        <?php
        return true;
    }

    // public function stream()
    // {
    //     // Set the necessary headers for SSE

    //     header('Content-Type: text/event-stream');
    //     header('Cache-Control: no-cache');
    //     header('Connection: keep-alive');
    //     // Disable output buffering for instant data pushing
    //     // if (ob_get_level() > 0) {
    //     //     for ($i = 0; $i < ob_get_level(); $i++) {
    //     //         ob_end_flush();
    //     //     }
    //     // }
    //     // ini_set('implicit_flush', true);
    //     ob_implicit_flush(true);






    //     session_write_close(); // Close the session to allow other requests

    //     while (true) {
    //         // Your logic to check for updates (e.g., from a database or Redis)
    //         $latestData = "Current time is: " . date("H:i:s");

    //         // Format the data as an SSE message
    //         echo "event: update\n";
    //         echo "data: " . json_encode(['message' => $latestData]) . "\n\n";
    //         //echo str_pad(' ',10000)."\n";
    //         // Push the output buffer to the client
    //         if (ob_get_contents()) {
    //             ob_end_flush();
    //         }
    //         flush();
    //         if (connection_aborted()){
    //             exit();
    //         }
    //         // Wait for a period before checking again (e.g., 3 seconds)
    //         sleep(1);
    //     }
    // }




    // public function sse2(){
    //     $user_id = 0;
    //     ini_set('max_execution_time', 60);
    //     ini_set('zlib.output_compression', 0);
    //     ini_set('implicit_flush', 1);
    //     ini_set('output_buffering', 0);

    //     header("Cache-Control: no-cache");
    //     header('Connection: keep-alive');
    //     header("Content-Type: text/event-stream");

    //     while (ob_get_level() > 0) {
    //         ob_end_flush();
    //     }

    //     while (1) {
    //         $updates = $this->getUpdates($user_id);
    //         if(!empty($updates)){
    //             foreach($updates as $update){
    //                 echo "event:".$update['code']."\n";
    //                 echo "data:".json_encode($update)."\n\n";
    //             }
    //         }
    //         echo str_pad('',10000)."\n";
    //         //pl(ob_get_status());

    //         // if (ob_get_contents()){
    //         //     ob_get_flush();
    //         // }
    //         // flush();
    //         if (connection_aborted()){
    //             exit();
    //         }
    //         usleep(750000); // 0.75 second
    //     }
    // }
    // private function getUpdates($user_id){
    //     return [[ 
    //         'id' => '1',
    //         'code' => 'update',
    //         'data' => ['text' => 'hello']
    //     ]];
    // }


    // public function sse(){
    //     header("X-Accel-Buffering: no");
    //     header("Content-Type: text/event-stream");
    //     header("Cache-Control: no-cache");
    //     header('Connection: keep-alive');
    //     ini_set('zlib.output_compression', '0');
    //     ini_set('implicit_flush', '1');
    //     ini_set('output_buffering', 'Off');
    //     session_write_close();
    //     set_time_limit(120);

    //     $counter = rand(1, 10); // a random counter
    //     while (1) {
    //         $curDate = date(DATE_ISO8601);
    //         echo "event: message\n",
    //             'data: {"time": "' . $curDate . '"}', "\n\n";

    //         $counter--;
    //         if (!$counter) {
    //             echo 'data: This is a message at time ' . $curDate, "\n\n";
    //             $counter = rand(1, 10); // reset random counter
    //         }
    //         while (ob_get_level() > 0) {
    //             ob_end_flush();
    //         }
    //         flush();
    //         if ( connection_aborted() ) {
    //             break;
    //         }
    //         usleep(1000000); // 0.75 second
    //     }
    // }


    public function listGet(): ResponseInterface{
        session_write_close();
        set_time_limit(120);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);

        $this->response
            ->setHeader('Content-Type', 'text/event-stream')
            ->setHeader('Cache-Control', 'no-cache')
            ->setHeader('Connection', 'keep-alive')
            ->setHeader('X-Accel-Buffering', 'no') // Important for nginx
            ->noCache();

        $target_user_id=$this->request->getPost('target_user_id');
        $last_event_id=$this->request->getPost('last_event_id');

        while (true) {
            if (connection_aborted()) {
                break;
            }
            $hasUpdate = $this->hasUpdate($target_user_id);
            if ($hasUpdate) {
                $data = $this->getLatestData($target_user_id,$last_event_id);
                echo "event: update\n";
                echo "id: " . time() . "\n";           // Event ID for reconnection
                echo "retry: 5000\n";                   // Reconnect after 5 seconds
                echo "data: " . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n\n";
                
                flush();
            }
            usleep(750000);
            static $lastHeartbeat = 0;
            if (time() - $lastHeartbeat > 15) {
                echo ": heartbeat " . date('H:i:s') . "\n\n";
                flush();
                $lastHeartbeat = time();
            }
        }
        return $this->response;
    }

    private function hasUpdate( $target_user_id ): bool{
        $predis = new \Credis_Client();
        $user_has_update=$predis->get("sse_hasupdate_{$target_user_id}");
        if( $user_has_update==0 ){
            return false;
        }
        return true;
    }

    private function getLatestData($target_user_id,$last_event_id): array{
        $ServerEvent=model('ServerEvent');
        $result=$ServerEvent->listGet($target_user_id,$last_event_id);
        if( $result['end_of_list'] ){
            $predis = new \Credis_Client();
            $predis->setEx("sse_hasupdate_{$target_user_id}",30*60,0);//30 min
        }
        if( $result['events'] ){
            $event_target_ids=[];
            foreach($result['events'] as $event_target){
                $event_target_ids[]=$event_target->event_target_id;
                  
            }
        }




        return $result;
    }
}