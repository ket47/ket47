<?php
namespace App\Libraries;
class SmsP1{
    private $url_api = "https://admin.p1sms.ru/apiSms/";

    public function sendSms($phone,$text){
        $data=[
            'sms'=>[
                [
                    'phone'=>$phone,
                    'text'=>$text,
                    'channel'=>'char',
                    'sender'=>'tezkel'
                ]
            ]
        ];
        return $this->apiExecute('create',$data);
    }
    private function apiExecute(string $method, array $data){
        $url = $this->url_api.$method;
        $data['apiKey']=getenv('p1sms.apiKey');
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json\r\naccept: application/json",
                'method'  => 'POST',
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE)
            )
        );
        $context  = stream_context_create($options);
        $response_text = file_get_contents($url, false, $context);
        if(!$response_text){
            log_message('critical',"SmsP1 on phone #{$data['sms']['phone']} api ResultCode is:".$response_text);
            return null;
        }
        $response=json_decode($response_text);
        return $response;
    }

}