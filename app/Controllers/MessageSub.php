<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class MessageSub extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }

    public function itemCreate(){
        $registration_id=$this->request->getVar('registration_id');
        $type=$this->request->getVar('type');
        $user_agent=$this->request->getVar('user_agent');
        $MessageSubModel=model('MessageSubModel');
        $result=$MessageSubModel->itemCreate($registration_id,$type,$user_agent);
        if( $result=='notauthorized' ){
            return $this->failUnauthorized('notauthorized');
        }
        return $this->respond($result);
    }

    public function listGet($user_id){
        $user_id=$this->request->getVar('user_id');
        $MessageSubModel=model('MessageSubModel');

        $result=$MessageSubModel->listGet($user_id);
        return $this->respond($result);
    }

    public function test(){
        $message=(object)[
            'template'=>'messages/order/on_customer_refunded_CUST_push',
            'context'=>[
                'order'=>(object)[
                    'order_id'=>999
                ]
            ],
            //'message_subject'=>'Заказ №999',
            //'message_link'=>'http://localhost:8100/#/order-999',
            'message_transport'=>'message',
            'message_reciever_id'=>41,
            'message_data'=>[
                'type'=>'event',
                'title'=>'Заказ №999',
                'body'=>'Refund will be initiated',
                'link'=>'http://localhost:8100/#/order-999',
                'order_id'=>999,
                'stageNew'=>'customer_refunded'
            ]
        ];
        $transport=new \App\Libraries\Messenger();
        echo $transport->itemSend($message);
    }
}
