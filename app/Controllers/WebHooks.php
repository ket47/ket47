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
            $Viber->$eventName($data->sender,$data->message);
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
            "send_name"=>0,
            "send_photo"=>0
        ];
        $response=$Viber->call_api('setWebhook',$request);
        print_r($request);
        p($response);
    }
}
