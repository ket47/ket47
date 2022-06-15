<?php
namespace App\Libraries;
class Messenger{
   
    public function itemSend( $message ){
        if( isset($message->message_reciever_id) ){
            $multiple_recievers=explode(',',$message->message_reciever_id);
            if( count($multiple_recievers)>1 ){
                foreach($multiple_recievers as $current_reciever_id){
                    if( !$current_reciever_id ){
                        continue;
                    }
                    $message->message_reciever_id=$current_reciever_id;
                    $this->itemSend($message);
                }
                return true;
            }
        }
        
        //log_message('error','Message to be send:'.json_encode($message));
        
        
        switch( $message->message_transport ){
            case 'email':
                $this->itemSendEmail($message);
                break;
            case 'message':
                $this->itemSendMessage($message);
                break;
            case 'sms':
                $this->itemSendSms($message);
                break;
            case 'viber':
                $this->itemSendViber($message);
                break;
            case 'push':
                $this->itemSendPush($message);
                break;
            default:
                log_message('error', "Unknown transport ($message->message_transport). Cant send message:". json_encode($message));
        }
    }
    
    private $reciever_cache=null;
    private function itemRecieverGet($user_id){
        if( $user_id==-100 ){//system user==admin
            return (object)[
                'user_name'=>"Администратор",
                'user_phone'=>getenv('phone_admin'),
                'user_email'=>getenv('email_admin'),
                'user_data'=>(object)[]
            ];
        }
        if(!empty($this->reciever_cache[$user_id])){
            return $this->reciever_cache[$user_id];
        }
        $UserModel=model('UserModel');
        $reciever=$UserModel->select("user_name,user_phone,user_email,user_data")->where('user_id',$user_id)->get()->getRow();
        if($reciever->user_data){
            $reciever->user_data= json_decode($reciever->user_data);
        }
        $this->reciever_cache[$user_id]=$reciever;
        return $reciever;
    }

    private function itemSendMessage( $message ){
        if( $this->itemSendPush($message) ){
            return true;
        }
        if( $this->itemSendViber($message) ){
            return true;
        }
        return $this->itemSendSms($message);
    }
    
    private function itemSendEmail( $message ){
        if( isset($message->message_reciever_id) ){
            $reciever=$this->itemRecieverGet($message->message_reciever_id);
            $email_to=$reciever->user_email;
        } else {
            $reciever=(object)[];
            $email_to=$message->message_reciever_email;
        }
        if( !$email_to ){
            return false;
        }
        
        if( isset($message->template) ){
            if(is_object($message->context)){
                $message->context=(array)$message->context;
            }
            $message->context['reciever']=$reciever;
            $message->message_text=view($message->template,$message->context);
        }
        if( !$message->message_text ){
            return false;
        }

        $email = \Config\Services::email();
        $config=[
            'SMTPHost'=>getenv('email_server'),
            'SMTPUser'=>getenv('email_username'),
            'SMTPPass'=>getenv('email_password'),
            'mailType'=>'html',
        ];
        $email->initialize($config);
        $email->setFrom(getenv('email_from'), getenv('email_sendername'));
        $email->setTo($email_to);
        $email->setSubject($message->message_subject??getenv('email_sendername'));
        $email->setMessage($message->message_text);
        $email_send_ok=$email->send();
        
        if( !$email_send_ok ){
            log_message('error', 'Cant send email:'. json_encode($message).$email->printDebugger(['headers']) );
            return false;
        }
        return true;
    }
    
    private function itemSendSms( $message ){
        $reciever=$this->itemRecieverGet($message->message_reciever_id);
        if( isset($message->template) ){
            if(is_object($message->context)){
                $message->context=(array)$message->context;
            }
            $message->context['reciever']=$reciever;
            $message->message_text=view($message->template,$message->context);
        }
        if( !$message->message_text ){
            return false;
        }
        $devinoSenderName=getenv('devinoSenderName');
        $devinoUserName=getenv('devinoUserName');
        $devinoPassword=getenv('devinoPassword');
        $Sms=new \App\Libraries\DevinoSms($devinoUserName,$devinoPassword,$devinoSenderName);
        $sms_send_ok=$Sms->send($reciever->user_phone,$message->message_text);        
        if( !$sms_send_ok ){
            log_message('error', "Cant send sms to {$reciever->user_phone}:". json_encode($message).$sms_send_ok );
            return false;
        }
        return true;
    }
    
    private function itemSendPush( $message ){
        $reciever=$this->itemRecieverGet($message->message_reciever_id);
        if( isset($message->template) ){
            if(is_object($message->context)){
                $message->context=(array)$message->context;
            }
            $message->context['reciever']=$reciever;
            $message->message_text=view($message->template,$message->context);
        }
        if( !$message->message_text ){
            return false;
        }

        $MessageSubModel=model('MessageSubModel');
        $sub_registration_ids=$MessageSubModel->listGet($message->message_reciever_id);

        $to=[];
        foreach($sub_registration_ids as $id){
            $to[]=$id->sub_registration_id;
        }

        $notification=[
            "title"=>"Your Title",
            "text"=>$message->message_text,
            "click_action"=>"OPEN_ACTIVITY_1"
        ];
        $data=[
            'ohoho'=>35
        ];






        $notification=[
            'token'=>$to,
            'notification' => [
                // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#notification
                'title' => 'Notification title',
                'body' => $message->message_text,
                'image' => 'http://lorempixel.com/400/200/',
            ],
            'data' => [
                'key_1' => 'Value 1',
                'key_2' => 'Value 2',
            ],
            'android' => [
                // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#androidconfig
                'ttl' => '3600s',
                'priority' => 'normal',
                'notification' => [
                    'title' => '$GOOG up 1.43% on the day',
                    'body' => '$GOOG gained 11.80 points to close at 835.67, up 1.43% on the day.',
                    'icon' => 'stock_ticker_update',
                    'color' => '#f45342',
                ],
            ],
            'apns' => [
                // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#apnsconfig
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => '$GOOG up 1.43% on the day',
                            'body' => '$GOOG gained 11.80 points to close at 835.67, up 1.43% on the day.',
                        ],
                        'badge' => 42,
                    ],
                ],
            ],
            'webpush' => [
                // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#webpushconfig
                'headers' => [
                    'Urgency' => 'normal',
                ],
                'notification' => [
                    'title' => '$GOOG up 1.43% on the day',
                    'body' => '$GOOG gained 11.80 points to close at 835.67, up 1.43% on the day.',
                    'icon' => 'https://my-server/icon.png',
                ],
            ],
            'fcm_options' => [
                // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#fcmoptions
                //'analytics_label' => 'some-analytics-label'
            ]
        ];
    

        $Firebase = new \App\Libraries\Firebase();
        $result=$Firebase->send_message($to,$notification);
        if( $result && ($result->status??null)==0 ){
            return true;
        }
        log_message('error', 'Firebase message failed: '.json_encode([$result,$message]));
        return false;
    }
    
    private function itemSendViber( $message ){
        $reciever=$this->itemRecieverGet($message->message_reciever_id);
        if( isset($message->template) ){
            if(is_object($message->context)){
                $message->context=(array)$message->context;
            }
            $message->context['reciever']=$reciever;
            $message->message_text=view($message->template,$message->context);
        }
        if( !$message->message_text || !$reciever->user_data->viberId ){
            log_message('error', 'No text or viberId for user_id:'.$message->message_reciever_id);
            return false;
        }
        $Viber = new \App\Libraries\Viber();
        $result=$Viber->send_message($reciever->user_data->viberId,$message->message_text);
        if( $result && ($result->status??null)==0 ){
            return true;
        }
        log_message('error', 'Viber message failed: '.json_encode([$result,$message]));
        return false;
    }
    
    public function listSend( array $message_list, $lazy_send=false ){
        foreach( $message_list as $message){
            $this->itemSend($message,$lazy_send);
        }
    }
}