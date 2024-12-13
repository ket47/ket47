<?php
namespace App\Libraries;
class Sms4B{
    private $url_api = "https://api.sms4b.ru/webHooks/";

    public function send($phone,$text){
        $data=[
            'login'=>getenv('sms4b.login'),
            'password'=>getenv('sms4b.password'),
            'name'=>getenv('sms4b.name'),
            'phone'=>$phone,
            'text'=>$text
        ];
        if(!$data['login'] || !$data['password']){
            return 'nopass';
        }
        return $this->apiExecute('SendSms',$data);
    }
    private function apiExecute(string $method, array $data){
        $url = $this->url_api.$method.'.php';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $out = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if( $statusCode=='200' ){
            return 'ok';
        }

        $response=json_decode($out);
        pl(['sms4b error',$url,$data,$response]);
        return $out;
    }

}