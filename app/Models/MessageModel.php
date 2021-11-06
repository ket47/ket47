<?php
namespace App\Models;
use CodeIgniter\Model;

class MessageModel extends Model{
    
    protected $table      = 'message_list';
    protected $primaryKey = 'message_id';
    protected $allowedFields = [
        'message_reciever_id',
        'message_subject',
        'message_text',
        'message_transport'
    ];
    protected $validationRules    = [
        'message_reciever_id'   => 'required',
        'message_text'          => 'required'
    ];

    protected $useSoftDeletes = false;

    public function itemCreate( object $message ){
        \CodeIgniter\Events\Events::on('post_response', [$this, 'itemSend'], $message);
    }
    
    private function itemSend( $message ){
        $multiple_recievers=explode($message->reciever_id);
        if( count($multiple_recievers)>1 ){
            foreach($multiple_recievers as $current_reciever_id){
                $message->reciever_id=$current_reciever_id;
                $this->itemSend($message);
            }
        }
        if( isset($message->template) ){
            $message->message_text=view($message->template,$message->context);
        }
        switch( $message->message_tarnsport??null ){
            case 'email':
                $this->itemSendEmail($message);
                break;
            case 'sms':
                $this->itemSendSms($message);
                break;
            case 'push':
                $this->itemSendPush($message);
                break;
            default:
                log_message('error', 'Unknown transport. Cant send message:'. json_encode($message));
        }
    }
    
    private function itemSendEmail( $message ){
        $UserModel=model('UserModel');
        $user=$UserModel->itemGet($message->reciever_id);
        if( !$user->user_email_verified || !$message->message_text ){
            return false;
        }
        $email = \Config\Services::email();
        $config=[
            'SMTPHost'=>getenv('email_server'),
            'SMTPUser'=>getenv('email_username'),
            'SMTPPass'=>getenv('email_password')
        ];
        $email->initialize($config);
        $email->setFrom(getenv('email_from'), getenv('email_sendername'));
        $email->setTo($user->user_email);
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
        $user=$UserModel->itemGet($message->reciever_id);
        if( !$user->user_phone_verified || !$message->message_text ){
            return false;
        }
        $devinoSenderName=getenv('devinoSenderName');
        $devinoUserName=getenv('devinoUserName');
        $devinoPassword=getenv('devinoPassword');
        $Sms=new \App\Libraries\DevinoSms($devinoUserName,$devinoPassword,$devinoSenderName);
        $sms_send_ok=$Sms->send($user->user_phone,$message->message_text);        
        if( !$sms_send_ok ){
            log_message('error', 'Cant send sms:'. json_encode($message).$sms_send_ok );
            return false;
        }
        return true;
    }
    
    private function itemSendPush( $message ){
        return false;
    }
    
    public function listSend( array $message_list ){
        foreach( $message_list as $message){
            $this->itemCreate($message);
        }
    }
}