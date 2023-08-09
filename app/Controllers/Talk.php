<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Talk extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function inquiryCreate(){
        $user_id=$this->request->getPost('user_id');

        $type=$this->request->getPost('type');
        $from=$this->request->getPost('from',FILTER_SANITIZE_SPECIAL_CHARS);
        $subject=$this->request->getPost('subject',FILTER_SANITIZE_SPECIAL_CHARS);
        $body=$this->request->getPost('body',FILTER_SANITIZE_SPECIAL_CHARS);

        if( !in_array($type,['outofrange','suggest_new_store','suggest_feedback']) ){
            return $this->failNotFound();
        }
        if( session()->get('inquiryTypeOnce'.$type) ){
            return $this->failTooManyRequests();
        }
        session()->set('inquiryTypeOnce'.$type,1);

        if( $user_id ){
            $user=model('UserModel')->itemGet($user_id);
            if($user){
                $from="{$user->user_name} +{$user->user_phone} ".($user->user_email??'');
            }
        }
        $context=[
            'from'=>$from,
            'subject'=>$subject,
            'body'=>$body,
        ];


        if( $type=='outofrange' ){
            $admin_sms=(object)[
                'message_transport'=>'telegram',
                'message_reciever_id'=>-100,
                'template'=>'messages/talk/outofrange_inquiry_ADMIN_sms.php',
                'context'=>$context
            ];
            $admin_email=(object)[
                'message_transport'=>'email',
                'message_reciever_id'=>-100,
                'message_subject'=>"Запрос на уведомление о новых продавцах от ".getenv('app.title'),
                'template'=>'messages/talk/outofrange_inquiry_ADMIN_email.php',
                'context'=>$context
            ];
            $messages=[$admin_sms,$admin_email];
        }
        if( $type=='suggest_new_store' ){
            $admin_sms=(object)[
                'message_transport'=>'telegram',
                'message_reciever_id'=>-100,
                'template'=>'messages/talk/suggest_new_store_inquiry_ADMIN_sms.php',
                'context'=>$context
            ];
            $admin_email=(object)[
                'message_transport'=>'email',
                'message_reciever_id'=>-100,
                'message_subject'=>"Запрос на добавление продавцов от ".getenv('app.title'),
                'template'=>'messages/talk/suggest_new_store_inquiry_ADMIN_email.php',
                'context'=>$context
            ];
            $messages=[$admin_sms,$admin_email];
        }
        if( $type=='suggest_feedback' ){
            $admin_sms=(object)[
                'message_transport'=>'telegram',
                'message_reciever_id'=>-100,
                'template'=>'messages/talk/suggest_feedback_inquiry_ADMIN_sms.php',
                'context'=>$context
            ];
            $admin_email=(object)[
                'message_transport'=>'email',
                'message_reciever_id'=>-100,
                'message_subject'=>"Отзыв о работе сервиса ".getenv('app.title'),
                'template'=>'messages/talk/suggest_feedback_inquiry_ADMIN_email.php',
                'context'=>$context
            ];
            $messages=[$admin_sms,$admin_email];
        }

        $notification_task=[
            'task_name'=>"Inquiry send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$messages]]
                ]
        ];
        jobCreate($notification_task);
        return $this->respond('ok');
    }




    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
 
}
