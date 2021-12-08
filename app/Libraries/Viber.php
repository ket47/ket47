<?php
namespace App\Libraries;
class Viber{
    private $url_api = "https://chatapi.viber.com/pa/";

    public function call_api($method, $data){
        $url = $this->url_api.$method;

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json\r\nX-Viber-Auth-Token: ".getenv('viber.token')."\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            )
        );
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return json_decode($response);
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
    
    public function onMessage($sender,$message){
        $UserModel=model('UserModel');
        $viberId=$sender->id;
        if( !isset($sender->id) ){
            return false;
        }
        $user_id=$UserModel->query("SELECT user_id FROM user_list WHERE JSON_EXTRACT(user_data,'$.viberId')='$viberId'")->getRow('user_id');
        if( $user_id ){
            $user=$UserModel->where('user_id',$user_id)->get()->getRow();
            $this->send_message($viberId, "{$user->user_name}, I don't understand :(. I'm only for notifying you");
        } else {
            helper('phone_number');
            $user_phone_cleared= clearPhone($message->text);
            if( strlen($user_phone_cleared)==11 ){
                $this->phoneVerificationSend($user_phone_cleared,$viberId);
                $this->send_message($viberId, 'Sms with code has been sent. Please text me verification code');
            } else if($this->phoneVerificationIsSent($viberId)) {
                $this->phoneVerificationCheck($viberId,$message->text);
            } else {
                $this->send_message($viberId, 'I dont recognize you. Please text me your phone');
            }
        }
    }
    
    public function onConversation_started($sender,$message){
        $UserModel=model('UserModel');
        $viberId=$sender->id;
        if( !isset($sender->id) ){
            return false;
        }
        $user_id=$UserModel->query("SELECT user_id FROM user_list WHERE JSON_EXTRACT(user_data,'$.viberId')='$viberId'")->getRow('user_id');
        if( !$user_id ){
            $this->send_message($viberId, 'I dont recognize you. Please text me your phone');
        }
    }


    ///////////////////////////////////////////////
    //VERIFICATION SECTION
    ///////////////////////////////////////////////
    private function phoneVerificationIsSent($viberId){
        $UserVerificationModel=model('UserVerificationModel');
        $UserVerificationModel->like('verification_value',$viberId)->where('created_at<DATE_ADD(NOW(),INTERVAL -5 MINUTE)')->delete();
        $user_verification_id=$UserVerificationModel->like('verification_value',$viberId)->get()->getRow('user_verification_id');
        return $user_verification_id?1:0;
    }
    private function phoneVerificationSend($user_phone,$viberId){
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        
        $UserModel=model('UserModel');
        $unverified_user_id=$UserModel->where('user_phone',$user_phone_cleared)->get()->getRow('user_id');
        if( !$unverified_user_id ){
            return $this->failNotFound('unverified_phone_not_found');
        }
        
        helper('hash_generate');
        $verification_code=generate_hash(4,'numeric');
        $data=[
            'user_id'=>$unverified_user_id,
            'verification_type'=>'viber',
            'verification_value'=>$viberId.'|'.$verification_code
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
        $UserVerificationModel->where('verification_value',$viberId.'|'.$verification_code);
        $verification=$UserVerificationModel->get()->getRow();
        if( !$verification ){
            return 'verification_not_found';
        }
        $UserModel=model('UserModel');
        $ok=$UserModel->query("UPDATE user_list SET user_data=JSON_SET(COALESCE(user_data,'{}'),'$.viberId','$viberId') WHERE user_id='$verification->user_id'");
        if( $ok ){
            $user=$UserModel->where('user_id',$verification->user_id)->get()->getRow();
            $this->send_message($viberId, 'Thank you.'.$user->user_name);
            $UserVerificationModel->delete($verification->user_verification_id);
            return true;
        }
        $this->send_message($viberId, 'Unfortunately verification code is wrong'.$user->user_name);
        return false;
    }
}
