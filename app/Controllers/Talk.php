<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Talk extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function inquiryCreate(){
        $user_id=$this->request->getPost('user_id');

        $type=$this->request->getPost('type');
        $from=$this->request->getPost('from',FILTER_SANITIZE_SPECIAL_CHARS);
        $subject=$this->request->getPost('subject',FILTER_SANITIZE_SPECIAL_CHARS);
        $body=$this->request->getPost('body',FILTER_SANITIZE_SPECIAL_CHARS);

        if( !in_array($type,['outofrange','suggest_new_store','suggest_feedback']) ){
            return $this->failNotFound();
        }
        if( session()->get('inquiryTypeOnce'.$type) ){
            return $this->failTooManyRequests();
        }
        session()->set('inquiryTypeOnce'.$type,1);

        if( $user_id ){
            $user=model('UserModel')->itemGet($user_id);
            if($user){
                $from="{$user->user_name} +{$user->user_phone} ".($user->user_email??'');
            }
        }
        $context=[
            'from'=>$from,
            'subject'=>$subject,
            'body'=>$body,
        ];


        if( $type=='outofrange' ){
            $admin_sms=(object)[
                'message_transport'=>'telegram',
                'message_reciever_id'=>-100,
                'template'=>'messages/talk/outofrange_inquiry_ADMIN_sms.php',
                'context'=>$context
            ];
            $admin_email=(object)[
                'message_transport'=>'email',
                'message_reciever_id'=>-100,
                'message_subject'=>"Запрос на уведомление о новых продавцах от ".getenv('app.title'),
                'template'=>'messages/talk/outofrange_inquiry_ADMIN_email.php',
                'context'=>$context
            ];
            $messages=[$admin_sms,$admin_email];
        }
        if( $type=='suggest_new_store' ){
            $admin_sms=(object)[
                'message_transport'=>'telegram',
                'message_reciever_id'=>-100,
                'template'=>'messages/talk/suggest_new_store_inquiry_ADMIN_sms.php',
                'context'=>$context
            ];
            $admin_email=(object)[
                'message_transport'=>'email',
                'message_reciever_id'=>-100,
                'message_subject'=>"Запрос на добавление продавцов от ".getenv('app.title'),
                'template'=>'messages/talk/suggest_new_store_inquiry_ADMIN_email.php',
                'context'=>$context
            ];
            $messages=[$admin_sms,$admin_email];
        }
        if( $type=='suggest_feedback' ){
            $admin_sms=(object)[
                'message_transport'=>'telegram',
                'message_reciever_id'=>-100,
                'template'=>'messages/talk/suggest_feedback_inquiry_ADMIN_sms.php',
                'context'=>$context
            ];
            $admin_email=(object)[
                'message_transport'=>'email',
                'message_reciever_id'=>-100,
                'message_subject'=>"Отзыв о работе сервиса ".getenv('app.title'),
                'template'=>'messages/talk/suggest_feedback_inquiry_ADMIN_email.php',
                'context'=>$context
            ];
            $messages=[$admin_sms,$admin_email];
        }

        $notification_task=[
            'task_name'=>"Inquiry send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$messages]]
                ]
        ];
        jobCreate($notification_task);
        return $this->respond('ok');
    }

    public function orderChatSend(){
        $order_id=$this->request->getPost('order_id');
        $reciever=$this->request->getPost('reciever');

        $subject=$this->request->getPost('subject',FILTER_SANITIZE_SPECIAL_CHARS);
        $body=$this->request->getPost('body',FILTER_SANITIZE_SPECIAL_CHARS);

        $OrderModel=model('OrderModel');
        $order=$OrderModel->itemGet($order_id,'basic');

        if( !is_object($order) ){
            return $this->fail($order);
        }
        if( $order->stage_current=='system_finish' ){
            return $this->fail('order_is_finished');
        }
        if( $reciever=='customer' ){
            $reciever_id=$order->owner_id;
        } else if( $reciever=='store' ){
            $reciever_id=$order->order_store_admins;
        } else {
            return $this->fail('unknown_reciever_type');            
        }

        $sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$reciever_id,
            'message_subject'=> "Сообщение по заказу #$order_id",
            'message_text'=>$body,
            'message_data'=>(object)[
                'sound'=>'long.wav',
                'link'=>"/order/order-{$order_id}"
            ],
            'telegram_options'=>[
                'buttons'=>[['',"onOrderOpen-{$order_id}",'⚡ Открыть заказ']]
            ],
        ];

        $voice_greeting="Вас приветствует тез кель.";
        $voice=(object)[
            'message_transport'=>'voice',
            'message_reciever_id'=>$reciever_id,
            'message_subject'=> "Прозвон по заказу #$order_id",
            'message_text'=>$voice_greeting.$body,
        ];
        $Messenger=new \App\Libraries\Messenger();
        $ok=$Messenger->itemSend($voice);
        $ok=$Messenger->itemSend($sms);
        if( $ok ){
            return $this->respond('ok');
        }
        return $this->fail('notsent');
    }


    
    
    public function noticeListGet(){
        session_write_close();
        set_time_limit(120);

        header("X-Accel-Buffering: no");
        header("Content-Type: text/event-stream");
        header("Cache-Control: no-cache");
        header('Connection: keep-alive');

        ini_set('zlib.output_compression', '0');
        ini_set('implicit_flush', '1');
        ini_set('output_buffering', 'Off');



        $target_user_id=$this->request->getPost('target_user_id');
        $last_event_id=$this->request->getPost('last_event_id');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        $lastHeartbeat = 0;
        while (true) {
            if (connection_aborted()) {
                break;
            }
            $notices = $this->noticeListUpdateGet2($target_user_id,$last_event_id);
            if ($notices) {
                foreach($notices as $notice){
                    echo "event: {$notice->notice_type}\n";
                    echo "id: " . time() . "\n";
                    echo "retry: 5000\n";
                    echo "data: " . json_encode($notice) . "\n\n";
                }
                flush();
            }
            usleep(750000);
            if (time() - $lastHeartbeat > 15) {
                echo ": heartbeat \n\n";
                flush();
                $lastHeartbeat = time();
            }
        }
        exit();
    }

    private function noticeListUpdateGet2($target_user_id,$last_event_id):array{

        $this->fakeMessageCreate();









        return [
            (object)[
                'notice_type'=>'chat',
                'text'=>date("Y-m-d H:i:s")
            ],
        ];
    }

    private function noticeListUpdateGet($target_user_id,$last_event_id):array{
        $predis = \Config\Services::predis();
        $user_has_update=$predis->get("sse_hasupdate_{$target_user_id}");
        if( $user_has_update==0 ){
            return [];
        }

        $NoticeModel=model('NoticeModel');
        $result=$NoticeModel->listGet($target_user_id,$last_event_id);
        if( $result['end_of_list'] ){
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

    private function fakeMessageCreate(){

        $text_seed=explode(' ',"Generating a random string involves creating a sequence of characters where each character is selected unpredictably from a defined set (e.g., letters, numbers, symbols). This process is used in programming to produce unique identifiers, passwords, tokens, or keys for security and randomness in applications.");
        $text_length=rand(3,10);
        $rand_keys = array_rand($text_seed, $text_length);
        $text_rand="";
        for($i=0; $i<$text_length; $i++){
            $text_rand.=$text_seed[$rand_keys[$i]]." ";
        }

        $message=(object)[
            'notice_type'=>'chat',
            'notice_holder_id'=>1,//testroom
            'notice_data'=>[
                'text'=>$text_rand
            ],
        ];
        $NoticeModel=model('NoticeModel');
        $NoticeModel->itemCreate();
    }
















    public function roomItemGet(){
        $notice_holder_id=$this->request->getPost('notice_holder_id');
        $NoticeHolderModel=model('NoticeHolderModel');
        $room=$NoticeHolderModel->itemGet($notice_holder_id);
        return $this->respond($room);
    }
    public function roomListGet(){
        ///$user_id=$this->request->getPost('user_id');
        $user_id=session()->get('user_id');
        if( $user_id<1 ){
            return $this->failUnauthorized();
        }
        $NoticeHolderModel=model('NoticeHolderModel');
        $roomList=$NoticeHolderModel->listGet($user_id);
        return $this->respond($roomList);
    }



    public function chatListGet(){
        $notice_holder_id=(int) $this->request->getPost('notice_holder_id');
        $last_notice_id=(int) $this->request->getPost('last_notice_id');
        //$limit_days=$this->request->getPost('limit_days');
        $NoticeDataModel=model('NoticeDataModel');
        $chatList=$NoticeDataModel->chatListGet($notice_holder_id,$last_notice_id);
        return $this->respond($chatList);
    }

    
    public function chat(){
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
            const evtSource = new EventSource("/Talk/noticeListGet");

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
}
