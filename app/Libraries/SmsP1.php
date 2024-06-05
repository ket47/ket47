<?php
namespace App\Libraries;
class SmsP1{
    private $url_api = "https://admin.p1sms.ru/apiSms/";

    public function send($phone,$text){
        $data=[
            'sms'=>[
                [
                    'phone'=>$phone,
                    'text'=>$text,
                    'channel'=>'char',
                    'sender'=>'VIRTA'
                ]
            ]
        ];
        return $this->apiExecute('create',$data);
    }
    private function apiExecute(string $method, array $data){
        $url = $this->url_api.$method;
        $data['apiKey']=getenv('p1sms.apiKey');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'accept: application/json'));
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $out = curl_exec($curl);
        $response=json_decode($out);

        if($response?->status=='success'){
            return 'ok';
        }
        return $response?->status;
    }

}