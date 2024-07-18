<?php
namespace App\Libraries;
class SmsCVoice{
    private $url_api = "https://smsc.ru/sys/";

    public function send($phone,$text){
        $data=[
            'login'=>getenv('smsc.login'),
            'psw'=>getenv('smsc.psw'),
            'phones'=>$phone,
            'mes'=>$text,
            'voice'=>getenv('smsc.voice'),
            'param'=>'30,30,4',
            'valid'=>'00:05',
            'call'=>1,
            'fmt'=>3,
            'cost'=>3
        ];
        return $this->apiExecute('send',$data);
    }
    private function apiExecute(string $method, array $data){
        $url = $this->url_api.$method.'.php';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $out = curl_exec($curl);

        $response=json_decode($out);
        if( $response->error_code??null ){
            pl(['smsc error',$url,$data,$response]);
            return $response->error;
        }
        return 'ok';
    }

}