<?php
namespace App\Libraries;
class Sms4Bv1{
    private $url_api = "https://api.sms4b.ru/v1/";

    public function send($phone,$text){
        $data=[
            'sender'=>getenv('sms4b.name'),
            'messages'=>[
                [
                'number'=>"$phone",
                'text'=>"$text"
                ]
            ]
        ];
        return $this->apiExecute('sms',$data);
    }
    private function apiExecute(string $method, array $data){
        $url = $this->url_api.$method;

        $auth=getenv('sms4b.token');
        $headers=["Authorization: {$auth}"];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $out = curl_exec($curl);
        pl($out);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if( $statusCode=='200' ){
            return 'ok';
        }

        $response=json_decode($out);
        pl(['sms4b error',$url,$data,$response]);
        return $out;
    }

}