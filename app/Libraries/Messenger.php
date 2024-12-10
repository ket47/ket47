<?php
namespace App\Libraries;
class Messenger{
    
    public function listSend( array $message_list ){
        foreach( $message_list as $message){
            try{
                $this->itemSendMulticast($message);
            } catch(\Throwable $e){
                log_message('error', 'Messenger->listSend Error '.$e->getMessage()."\n".$e->getTraceAsString());
            }
        }
    }
   
    public function itemSendMulticast( $message ){
        if( !$message ){
            return false;
        }
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
        return $this->itemSend($message);
    }

    public function itemSend( $message ){
        $message->reciever=$this->itemRecieverGet($message->message_reciever_id??0);
        if( isset($message->template) ){
            $message->message_text=$this->itemRender($message);
        }
        //log_message('error',json_encode($message,JSON_UNESCAPED_UNICODE));
        $ok=1;
        if( str_contains($message->message_transport,'email') ){
            $ok*=$this->itemSendEmail($message);
        }
        if( str_contains($message->message_transport,'message') ){
            $ok*=$this->itemSendMessage($message);
        }
        if( str_contains($message->message_transport,'sms') ){
            $ok*=$this->itemSendSms($message);
        }
        if( str_contains($message->message_transport,'telegram') ){
            $ok*=$this->itemSendTelegram($message);
        }
        if( str_contains($message->message_transport,'push') ){
            $ok*=$this->itemSendPush($message);
        }
        if( str_contains($message->message_transport,'voice') ){
            $ok*=$this->itemSendVoice($message);
        }
        if( $ok ){
            return $ok;
        }
        //log_message('error', "Sending error ($message->message_transport). Cant send message:". json_encode($message->reciever));
    }
    
    private $reciever_cache=null;
    private function itemRecieverGet($user_id){
        if( $user_id==-100 ){//system user==admin
            return (object)[
                'user_name'=>"Администратор",
                'user_phone'=>getenv('phone_admin'),
                'user_email'=>getenv('email_admin'),
                'user_data'=>(object)[
                    'telegramChatId'=>getenv('telegram.adminChatId')
                ]
            ];
        }
        if( $user_id==-50 ){//system user==courier
            return (object)[
                'user_name'=>"Курьер",
                'user_phone'=>getenv('phone_admin'),
                'user_email'=>getenv('email_admin'),
                'user_data'=>(object)[
                    'telegramChatId'=>getenv('telegram.courierChatId')
                ]
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

        //log_message('error',json_encode($reciever,JSON_UNESCAPED_UNICODE));
        if( !$reciever ){
            return (object)[];
        }
        if($reciever->user_data){
            $reciever->user_data= json_decode($reciever->user_data);
        }
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
        if(getenv('test.emailMock')==1){
            return true;
        }
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
            'SMTPPort'=>getenv('email_port'),
            'SMTPCrypto'=>getenv('email_encryption'),
            'mailType'=>'html',
        ];
        $email->initialize($config);
        $email->setFrom(getenv('email_from'), getenv('email_sendername'));
        $email->setTo($email_to);
        $email->setSubject($message->message_subject??getenv('email_sendername'));

        $images = (object) [
            'header' => ROOTPATH.'/public/img/tezbanner.jpg', 
            'buttonPM' => ROOTPATH.'/public/img/playgoogle.png'
        ];


        $email->attach($images->header);
        $email->attach($images->buttonPM);

        $message->images = (object) [
            'buttonPM' => $email->setAttachmentCID($images->buttonPM),
            'header' =>  $email->setAttachmentCID($images->header)
        ];
        if(!empty($message->message_data->image)){
            $image_hash = basename($message->message_data->image, '.1000.1000.webp');
            file_get_contents(base_url('/image/get.php/').'/'.$image_hash.'.450.450.jpg', false, stream_context_create(["ssl"=>["verify_peer"=>false,"verify_peer_name"=>false]]));
            $images->content = WRITEPATH.'images/optimised/'.$image_hash.'.450.450.jpg';
            $email->attach($images->content);
            $message->images->content = $email->setAttachmentCID($images->content);
        }
        $email_html = view('messages/mailing/email_basic', (array) $message);
        $email->setMessage($email_html);
        $email_send_ok=$email->send();
        if( !$email_send_ok ){
            //log_message('error', 'Cant send email:'. $email->printDebugger(['headers']) );
            return false;
        }
        return true;
    }

    private function itemSendMessage( $message ){
        if( $this->itemSendPush($message) ){
            return true;
        }
        if( $this->itemSendTelegram($message) ){
            return true;
        }
        return $this->itemSendSms($message);
    }
    
    private function itemSendSms( $message ){
        $phone_to=$message->message_reciever_phone??$message->reciever->user_phone??'';
        if(!$phone_to){
            pl(['Sms cant be send: no phone number',$message]);
            return false;
        }
        $Sms=\Config\Services::sms();
        $result=$Sms->send($phone_to,strip_tags($message->message_text));
        if( $result!=='ok' ){
            log_message('error', "Cant send sms to {$phone_to}:". json_encode($message).$result );
            return false;
        }
        return true;
    }
    
    private function itemSendVoice( $message ){
        $phone_to=$message->message_reciever_phone??$message->reciever->user_phone??'';
        if(!$phone_to){
            pl(['voice cant be send: no phone number',$message]);
            return false;
        }
        $Sms=\Config\Services::voice();
        $result=$Sms->send($phone_to,strip_tags($message->message_text));
        if( $result!=='ok' ){
            log_message('error', "Cant send voice to {$phone_to}:". json_encode($message).$result );
            return false;
        }
        return true;
    }

    private function itemPushTokensGet($user_id){
        $MessageSubModel=model('MessageSubModel');
        $MessageSubModel->select('sub_registration_id');
        $MessageSubModel->where('sub_user_id',$user_id);
        $MessageSubModel->orderBy("sub_type='push'","DESC");
        $MessageSubModel->orderBy("created_at","DESC");
        $MessageSubModel->limit(3);
        $rows=$MessageSubModel->get()->getResult();
        if(!$rows){
            return [];
        }
        $tokens=[];
        foreach($rows as $row){
            $tokens[]=$row->sub_registration_id;
        }
        return $tokens;
    }

    private function itemSendPush( $message ){
        if(getenv('test.pushMock')==1){
            return true;
        }
        $tokens=$this->itemPushTokensGet($message->message_reciever_id);
        if( !count($tokens) ){
            return false;
        }
        $message->message_data??=(object)[];
        $message->message_data->title??=$message->message_subject??'';
        $message->message_data->body=strip_tags($message->message_data->body??$message->message_text??'');
        $FirePush = new \App\Libraries\FirePushKreait();
        return $FirePush->sendPush((object)[
            'token'=>$tokens,
            'title'=>$message->message_data->title,
            'body'=>$message->message_data->body,
            'data'=>$message->message_data,
        ]);
    }
    
    private function itemSendTelegram( $message ){
        if(getenv('test.telegramMock')==1){
            return true;
        }
        if( !isset($message->reciever->user_data->telegramChatId) ){
            return false;
        }
        $message->telegram_options??=(object)[];
        $message->telegram_options->opts??=(object)[];
        if( !empty($message->message_data->image) ){//for messages with images append is not possible
            $message->telegram_options->opts->caption='<b>'.$message->message_subject.'</b> '.$message->message_text;
            $message->telegram_options->opts->photo=$message->message_data->image;
            $message->message_text=null;
            // $message->message_text='<b>'.$message->message_subject.'</b> '.$message->message_text;
            // $message->message_text.="<a href='{$message->message_data->image}'> </a>";
        }

        $append_result=$this->itemAppendRead($message->telegram_options->append_order_id??null,$message->message_text);
        $message->message_text=$append_result['message_text'];
        $message->telegram_options->opts->message_id=$append_result['message_id'];

        $telegramToken=getenv('telegram.token');
        $TelegramBot = new \App\Libraries\Telegram\TelegramBot();
        $TelegramBot->Telegram=new \App\Libraries\Telegram\Telegram($telegramToken);
        $result=$TelegramBot->sendNotification($message->reciever->user_data->telegramChatId,$message->message_text,$message->telegram_options);

        $this->itemAppendWrite( $message->telegram_options->append_order_id??null, $message->message_text, $result['result']['message_id']??null );

        if( !empty($result['ok']) ){
            return true;
        }
        log_message('error', 'Telegram message failed: '.json_encode([$result,$message]));
        if( 403==($result['error_code']??0) ){
            $this->itemUnsubscribeTelegram($message->message_reciever_id);
            log_message('error', "Telegram reciever was UNSUBSCRIBED: {$message->reciever->user_phone} {$message->reciever->user_name}");
        }
        return false;
    }

    private function itemAppendRead( int $order_id=null, string $append_text=null ){
        if( !$order_id || !$append_text ){
            return[
                'message_id'=>null,
                'message_text'=>$append_text
            ];
        }
        $text=$append_text;
        $message_id=null;
        $OrderModel=model('OrderModel');
        $OrderModel->allowRead();
        $order_data=$OrderModel->itemDataGet($order_id);
        if( $order_data->telegram_message_id??null ){
            $text=$order_data->telegram_message_text??'';
            $text="{$text}\n".date('H:i')." {$append_text}";
            $message_id=$order_data->telegram_message_id;
        }
        return [
            'message_id'=>$message_id,
            'message_text'=>$text
        ];
    }

    private function itemAppendWrite( int $order_id=null, string $message_text=null, int $message_id=null ){
        if( !$order_id ){
            return;
        }
        $update=(object)[
            'telegram_message_id'=>$message_id,
            'telegram_message_text'=>$message_text
        ];
        $OrderModel=model('OrderModel');
        $OrderModel->allowWrite();
        $OrderModel->itemDataUpdate($order_id, $update);
    }

    private function itemUnsubscribeTelegram( int $user_id ){
        $UserModel=model('UserModel');
        $UserModel->set("user_data","JSON_REMOVE(`user_data`,'$.telegramChatId')",false);
        $UserModel->update($user_id);
    }
}