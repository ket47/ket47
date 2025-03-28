<?php
namespace App\Libraries\Telegram;
class TelegramVerification{
    private $url_api = "https://gatewayapi.telegram.org/";

    public function send($phone,$text){
        // $data=[
        //     'phone_number'=>$phone
        // ];
        // $abilityStatus=$this->apiExecute('checkSendAbility',$data);

        // if( $abilityStatus->error??0 || !$abilityStatus->ok ){
        //     return false;
        // }
        $data=[
            'phone_number'=>$phone,
            'code'=>$text,
            //'request_id'=>$abilityStatus->result->request_id
        ];
        return $this->apiExecute('sendVerificationMessage',$data);
    }
    private function apiExecute(string $method, array $data){
        $url = $this->url_api.$method;

        $auth=getenv('telegram.gatewayToken');
        $headers=["Authorization: Bearer {$auth}"];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $out = curl_exec($curl);

        $response=json_decode($out);
        if( $response->error_code??null ){
            pl(['telegramVerification error',$url,$data,$response]);
            return $response->error;
        }
        return $response;
    }

}