<?php
namespace App\Libraries;
class Viber{
    private $url_api = "https://chatapi.viber.com/pa/";

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
        $data['url']   = '';
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
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return json_decode($response);
    }
}
