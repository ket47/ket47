<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class WebHooks extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function viberWebhook(){
        $Viber= new \App\Libraries\Viber();
        $data=$this->request->getJSON();
        if( isset($data->event) ){
            $this->incoming=$data;
            $eventName="on{$data->event}";
            if( method_exists($Viber,$eventName) ){
                $Viber->$eventName($data->sender,$data->message);
            } //else {
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
                $email->setSubject('Viber webhook');
                $email->setMessage(json_encode($data));
                $email_send_ok=$email->send();
            //}
        }
        $webhook_response['status']=0;
        $webhook_response['status_message']="ok";
        $webhook_response['event_types']='delivered';
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
        $response=$Viber->call_api('setWebhook',$request);
        print_r($request);
        p($response);
    }
}
