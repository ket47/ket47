<?php
namespace App\Libraries;
class Messenger{
   
    public function itemSend( $message ){
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
        switch( $message->message_transport ){
            case 'email':
                $this->itemSendEmail($message);
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
    
    private function itemSendEmail( $message ){
        $UserModel=model('UserModel');
        $reciever=$UserModel->select("user_name,user_phone,user_email")->where('user_id',$message->message_reciever_id)->get()->getRow();
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
        $email->setTo($reciever->user_email);
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
        $UserModel=model('UserModel');
        $reciever=$UserModel->select("user_name,user_phone,user_email")->where('user_id',$message->message_reciever_id)->get()->getRow();
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
            log_message('error', 'Cant send sms:'. json_encode($message).$sms_send_ok );
            return false;
        }
        return true;
    }
    
    private function itemSendPush( $message ){
        return false;
    }
    
//    private function itemSendViber( $message ){
//        $UserModel=model('UserModel');
//        $reciever=$UserModel->select("user_name,user_phone,user_email")->where('user_id',$message->message_reciever_id)->get()->getRow();
//        if( isset($message->template) ){
//            if(is_object($message->context)){
//                $message->context=(array)$message->context;
//            }
//            $message->context['reciever']=$reciever;
//            $message->message_text=view($message->template,$message->context);
//        }
//        if( !$message->message_text ){
//            return false;
//        }
//        
//        
//        $Viber = new \App\Libraries\Viber();
//   
//        
//        $result=$Viber->send_message($reciever->user_phone,$message->message_text);
//        
//        p($result);
//        log_message('VIBER', $result);
//    }
    
    public function listSend( array $message_list, $lazy_send=false ){
        foreach( $message_list as $message){
            $this->itemSend($message,$lazy_send);
        }
    }
}