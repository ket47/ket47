<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Viber extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    private $url_api = "https://chatapi.viber.com/pa/";

    public function get_account_info(){
        $result=$this->call_api('send_message', (object)[]);
        return $this->respond($result);
    }
    
    public function send_message($receiver,$text){
        
        
        $data['receiver']   = $receiver;
        $data['sender'] = [
            'name' => getenv('viber.title'),
            'avatar' => getenv('viber.avatar')
        ];
        $data['type']   = 'text';
        $data['text']   = $text;
        return $this->call_api('send_message', $data);
    }
    
    public function set_webhook(){
        $data['url']   = 'https://api.tezkel.com/Viber/webhook';
        return $this->call_api('set_webhook', $data);
    }

    private function call_api($method, $data){
        $url = $this->url_api.$method;

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nX-Viber-Auth-Token: ".getenv('viber.token')."\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            )
        );
        //p($options);
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return json_decode($response);
    }    
    public function webhook(){
        return $this->respond(1);
    }
 
}
