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
        if( !isset($sender->id) ){
            return false;
        }
        $UserModel=model('UserModel');
        $viberId=$sender->id;
        $user_id=$UserModel->query("SELECT user_id FROM user_list WHERE JSON_EXTRACT(user_data,'$.viberId')='$viberId'")->getRow('user_id');
        if( $user_id ){
            $user=$UserModel->where('user_id',$user_id)->get()->getRow();
            $this->send_message($viberId, view('messages/viber/dont_understand',['user'=>$user]));
        } else {
            helper('phone_number');
            $user_phone_cleared= clearPhone($message->text);
            if( strlen($user_phone_cleared)==11 ){
                $this->phoneVerificationSend($user_phone_cleared,$viberId);
            } else if($this->phoneVerificationIsSent($viberId)) {
                $this->phoneVerificationCheck($viberId,$message->text);
            } else {
                $this->send_message($viberId, view('messages/viber/write_me_phone_number',[]));
            }
        }
    }
    
    public function onConversation_started($sender,$message){
        if( !isset($sender->id) ){
            return false;
        }
        $UserModel=model('UserModel');
        $viberId=$sender->id;
        $user=$UserModel->query("SELECT user_id,user_name FROM user_list WHERE JSON_EXTRACT(user_data,'$.viberId')='$viberId'")->getRow();
        if( $user->user_id ){
            $text=view('messages/viber/hello',['user'=>$user]);
        } else {
            $text= view('messages/viber/write_me_phone_number',[]);
        }
        $response=[
            'sender'=>[
                'name'=> getenv('viber.title'),
                'avatar'=>getenv('viber.avatar')
            ],
            'text'=>$text
        ];
        return $response;
    }

    public function ondelivered($sender,$message){
        return [
            'status'=>0,
            'status_message'=>'ok'
        ];
    }

    public function onseen($sender,$message){
        return [
            'status'=>0,
            'status_message'=>'ok'
        ];
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
            $this->send_message($viberId, view('messages/viber/verification_code_send_notfound',['user_phone'=>$user_phone_cleared]));
            return false;
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
        
        $Sms=\Config\Services::sms();
        $ok=$Sms->send($user_phone_cleared,view('messages/phone_verification_sms.php',$msg_data));
        if( $ok ){
            $this->send_message($viberId, view('messages/viber/verification_code_sent',['user_phone'=>$user_phone_cleared]));
            return true;
        }
        $this->send_message($viberId, view('messages/viber/verification_code_send_error',['user_phone'=>$user_phone_cleared]));
        return false;
    }
    
    private function phoneVerificationCheck($viberId,$verification_code){
        $UserVerificationModel=model('UserVerificationModel');
        $UserVerificationModel->where('verification_value',$viberId.'|'.$verification_code);
        $verification=$UserVerificationModel->get()->getRow();
        if( !$verification ){
            $this->send_message($viberId, view('messages/viber/verification_code_wrong',[]));
            return false;
        }
        $UserModel=model('UserModel');
        $ok=$UserModel->query("UPDATE user_list SET user_data=JSON_SET(COALESCE(user_data,'{}'),'$.viberId','$viberId') WHERE user_id='$verification->user_id'");
        if( $ok ){
            $user=$UserModel->where('user_id',$verification->user_id)->get()->getRow();
            $this->send_message($viberId, view('messages/viber/verification_code_right',['user'=>$user]));
            $this->send_message($viberId, view('messages/viber/hello',['user'=>$user]));
            $UserVerificationModel->delete($verification->user_verification_id);
            return true;
        }
        $this->send_message($viberId, view('messages/viber/verification_code_wrong',[]));
        return false;
    }
}
