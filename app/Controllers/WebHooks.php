<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Webhooks extends \App\Controllers\BaseController{
    use ResponseTrait;

    public function telegramWebhook(){
        $telegramToken=getenv('telegram.token');
        $Telegram=new \App\Libraries\Telegram\Telegram($telegramToken);
        $Tbot=new \App\Libraries\Telegram\TelegramBot();
        $Tbot->dispatch($Telegram);
    }

    public function telegramPoll(){
        $telegramToken=getenv('telegram.token');
        $Telegram=new \App\Libraries\Telegram\Telegram($telegramToken);
        $Tbot=new \App\Libraries\Telegram\TelegramBot();
        $Telegram->getUpdates($offset = 0, $limit = 3, 1, $update = true);
        for ($i = 0; $i < $Telegram->UpdateCount(); $i++) {
            $Telegram->serveUpdate($i);
            $Tbot->dispatch($Telegram);
        }
    }
    public function telegramPing(){
        if(PHP_SAPI !== 'cli'){
            return false;
        }

        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );
        while(1){
            $result=$this->telegramPoll();
            //$result=@file_get_contents('http://tezkel.local/WebHooks/telegramPoll', false, stream_context_create($arrContextOptions));
            if($result){
                \CodeIgniter\CLI\CLI::write("W HELPER:".$result);
            }
        }
    }
    
    public function viberWebhook(){
        $Viber= new \App\Libraries\Viber();
        $data=$this->request->getJSON();
        $response=null;
        if( isset($data->event) ){
            $this->incoming=$data;
            $eventName="on{$data->event}";
            if( method_exists($Viber,$eventName) ){
                $response=$Viber->$eventName($data->sender??null,$data->message??'');
            } else {
                $email = \Config\Services::email();
                $config=[
                    'SMTPHost'=>getenv('email_server'),
                    'SMTPUser'=>getenv('email_username'),
                    'SMTPPass'=>getenv('email_password'),
                    'mailType'=>'text',
                ];
                $email->initialize($config);
                $email->setFrom(getenv('email_from'), getenv('email_sendername'));
                $email->setTo(getenv('email_admin'));
                $email->setSubject(getenv('app.baseURL').' Viber webhook');
                $email->setMessage(json_encode($data));
                $email_send_ok=$email->send();
            }
        }
        if( is_array($response) || is_object($response) ){
            return $this->respond($response);
        }
        $webhook_response['status']=0;
        $webhook_response['status_message']="ok";
        return $this->respond($webhook_response);
    }
    
    public function viberSetWebhook(){
        if( !sudo() ){
            die("Access denied!");
        }
        $Viber= new \App\Libraries\Viber();
        $request=[
            "url"=>getenv('app.baseURL').'WebHooks/viberWebhook',
            "event_types"=>[
                "delivered",
                "seen",
                "failed",
                "subscribed",
                "unsubscribed",
                "conversation_started"
            ],
            "send_name"=>false,
            "send_photo"=>false
        ];
        $response=$Viber->call_api('set_webhook',$request);
    }
}
