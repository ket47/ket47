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


        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        p(json_decode($out));







        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json\r\naccept: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)//, JSON_UNESCAPED_UNICODE
            )
        );
        $context  = stream_context_create($options);
        try{
            $response_text = file_get_contents($url, false, $context);
        } catch( \Exception $e){
            log_message('critical',"SmsP1 on phone #{$data['sms'][0]['phone']} api FAILED error".$e->getMessage());
            return null;
        }
        return 'ok';
    }

}