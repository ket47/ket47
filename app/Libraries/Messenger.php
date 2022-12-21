<?php
namespace App\Libraries;
class Messenger{
    
    public function listSend( array $message_list, $lazy_send=false ){
        foreach( $message_list as $message){
            try{
                $this->itemSendMulticast($message,$lazy_send);
            } catch(\Exception $e){
                log_message('error', 'Messenger->listSend Error '.$e->getMessage()."\n".$e->getTraceAsString());
            }
        }
    }
   
    public function itemSendMulticast( $message ){
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
        $this->itemSend($message);
    }

    public function itemSend( $message ){
        $message->reciever=$this->itemRecieverGet($message->message_reciever_id??0);
        if( !isset($message->message_text) ){
            if( isset($message->template) ){
                $message->message_text=$this->itemRender($message);
            }
            //  else {
            //     log_message('error','Message could not be send Empty text:'.json_encode($message,JSON_PRETTY_PRINT));
            //     return false;
            // }
        }
        //log_message('error','Message to be send:'.json_encode($message,JSON_PRETTY_PRINT));
        switch( $message->message_transport ){
            case 'email':
                return $this->itemSendEmail($message);
                break;
            case 'message':
                return $this->itemSendMessage($message);
                break;
            case 'sms':
                return $this->itemSendSms($message);
                break;
            case 'telegram':
                return $this->itemSendTelegram($message);
                break;
            // case 'viber':
            //     return $this->itemSendViber($message);
            //     break;
            case 'push':
                return $this->itemSendPush($message);
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
        if( !$user_id || $user_id<1){
            return (object)[];
        }
        if(!empty($this->reciever_cache[$user_id])){
            return $this->reciever_cache[$user_id];
        }
        $UserModel=model('UserModel');
        $reciever=$UserModel->select("user_name,user_phone,user_email,user_data")->where('user_id',$user_id)->get()->getRow();

        //log_message('error',json_encode($reciever));
        if( !$reciever ){
            return (object)[];
        }
        if($reciever->user_data){
            $reciever->user_data= json_decode($reciever->user_data);
        }
        $reciever->subscriptions=model('MessageSubModel')->listGet($user_id);
        $this->reciever_cache[$user_id]=$reciever;
        return $reciever;
    }

    private function itemRender($message){
        if( is_object($message->context) ){
            $message->context=(array)$message->context;
        }
        $message->context['reciever']=$message->reciever;
        return view($message->template,$message->context);
    }
    
    private function itemSendEmail( $message ){
        $email_to=$message->message_reciever_email??$message->reciever->user_email??'';
        if(!$email_to){
            //log_message('error','Email cant be send: no email address');
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

    private function itemSendMessage( $message ){
        if( $this->itemSendPush($message) ){
            //return true;//try to send viber even if push is successfull
        }
        if( $this->itemSendTelegram($message) ){
            return true;
        }
        return $this->itemSendSms($message);
    }
    
    private function itemSendSms( $message ){
        $phone_to=$message->message_reciever_phone??$message->reciever->user_phone??'';
        if(!$phone_to){
            log_message('error','Sms cant be send: no phone number');
            return false;
        }
        $Sms=\Config\Services::sms();
        $sms_send_ok=$Sms->send($phone_to,$message->message_text);
        if( !$sms_send_ok ){
            log_message('error', "Cant send sms to {$phone_to}:". json_encode($message).$sms_send_ok );
            return false;
        }
        return true;
    }
    
    private function itemSendPush( $message ){
        if( !count($message->reciever->subscriptions??[]) ){
            return false;
        }
        if( !isset($message->message_data) ){
            $message->message_data=[
                'title'=>$message->message_subject??'',
                'body'=>$message->message_text??'',
                'link'=>$message->message_link??'',
                'icon'=>'/img/icons/monochrome.png'
            ];
        }
        $pushsent=false;
        $FirePush = new \App\Libraries\FirePush();
        foreach($message->reciever->subscriptions as $sub){
            $result=$FirePush->sendPush((object)[
                'token'=>$sub->sub_registration_id,
                'data'=>$message->message_data,
                //'title'=>$message->message_subject??'',
                //'body'=>$message->message_text??'',
                //'link'=>$message->message_link,
            ]);
            if($result){
                $pushsent=true;
            }
        }
        return $pushsent;
    }
    
    private function itemSendTelegram( $message ){
        if( !isset($message->reciever->user_data->telegramChatId) ){
            return false;
        }
        $telegramToken=getenv('telegram.token');
        $TelegramBot = new \App\Libraries\Telegram\TelegramBot();
        $TelegramBot->Telegram=new \App\Libraries\Telegram\Telegram($telegramToken);

        $result=$TelegramBot->sendNotification($message->reciever->user_data->telegramChatId,$message->message_text,$message->telegram_options??null);



        if( $result && ($result['ok']??null)==1 ){
            return true;
        }
        log_message('error', 'Telegram message failed: '.json_encode([$result,$message]));
        return false;
    }

    private function itemSendViber( $message ){
        if( !isset($message->reciever->user_data->viberId) ){
            //log_message('error', 'No viberId for user_id:'.$message->message_reciever_id);
            return false;
        }
        $Viber = new \App\Libraries\Viber();
        $result=$Viber->send_message($message->reciever->user_data->viberId,$message->message_text);
        if( $result && ($result->status??null)==0 ){
            return true;
        }
        log_message('error', 'Viber message failed: '.json_encode([$result,$message]));
        return false;
    }
}