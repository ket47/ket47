<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Viber extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    private $url_api = "https://chatapi.viber.com/pa/";

    private function call_api($method, $data){
        $url = $this->url_api.$method;

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json\r\nX-Viber-Auth-Token: ".getenv('viber.token')."\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            )
        );
        //p($options);
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return json_decode($response);
    }
    
    private function send_message($receiver,$text){
        $data['receiver']   = $receiver;
        $data['sender'] = [
            'name' => getenv('viber.title'),
            'avatar' => getenv('viber.avatar')
        ];
        $data['type']   = 'text';
        $data['text']   = $text;
        return $this->call_api('send_message', $data);
    }
    
    public function webhook(){
        $data=$this->request->getJSON();
        if( isset($data->event) ){
            $this->incoming=$data;
            $eventName="on{$data->event}";
            $this->$eventName($data->sender,$data->message);
        }
        $webhook_response['status']=0;
        $webhook_response['status_message']="ok";
        $webhook_response['event_types']='delivered';
        return $this->respond($webhook_response);
    }
    
    private function onMessage($sender,$message){
        $UserModel=model('UserModel');
        $viberId=$sender->id;
        if( !isset($sender->id) ){
            return false;
        }
        $user_id=$UserModel->query("SELECT user_id FROM user_list WHERE JSON_EXTRACT(user_data,'$.viber.id')='$viberId'")->getRow('user_id');
        if( !$user_id ){
            helper('phone_number');
            $user_phone_cleared= clearPhone($message->text);
            die('--'.$user_phone_cleared);
            
            
            if( strlen($user_phone_cleared)==11 ){
                $this->phoneVerificationSend($user_phone_cleared,$viberId);
                $this->send_message($viberId, 'I dont recognize you. Please text me verification code');
            } else {
                $this->phoneVerificationCheck($viberId,$message->text);
            }
        }
    }
 
    ///////////////////////////////////////////////
    //VERIFICATION SECTION
    ///////////////////////////////////////////////
    private function phoneVerificationSend($user_phone,$viberId){
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        
        $UserModel=model('UserModel');
        $unverified_user_id=$UserModel->getUnverifiedUserIdByPhone($user_phone_cleared);
        if( !$unverified_user_id ){
            return $this->failNotFound('unverified_phone_not_found');
        }
        
        helper('hash_generate');
        $verification_code=generate_hash(4,'numeric');
        $data=[
            'user_id'=>$unverified_user_id,
            'verification_type'=>'viber',
            'verification_value'=>$viberId.$verification_code
        ];

        $UserVerificationModel=model('UserVerificationModel');
        $UserVerificationModel->insert($data);
        $msg_data=[
            'verification_code'=>$verification_code
        ];
        
        $devinoSenderName=getenv('devinoSenderName');
        $devinoUserName=getenv('devinoUserName');
        $devinoPassword=getenv('devinoPassword');
        $Sms=new \App\Libraries\DevinoSms($devinoUserName,$devinoPassword,$devinoSenderName);
        $ok=$Sms->send($user_phone_cleared,view('messages/phone_verification_sms.php',$msg_data));
    }
    
    private function phoneVerificationCheck($viberId,$verification_code){
        $UserVerificationModel=model('UserVerificationModel');
        $UserVerificationModel->where('verification_value',$viberId.$verification_code);
        $vrf=$UserVerificationModel->get()->getRow();
        
        
        
        $this->send_message($viberId, json_encode($vrf));
        
    }
}
