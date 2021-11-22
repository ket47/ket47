<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Cloudpayments extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    private function authorize( $data ){
        if( !$data ){
            return false;
        }
        /*
         * CHECK X-Content-HMAC и Content-HMAC
         */
        $UserModel=model('UserModel');
        $OrderModel=model('OrderModel');
        
        $order_owner_id=$OrderModel
                ->select('owner_id')
                ->where('order_id',$data->InvoiceId)
                ->get()
                ->getRow('owner_id');
        if( !$order_owner_id || $order_owner_id!=$data->AccountId){
            return false;
        }
        $UserModel->systemUserLogin();
        \CodeIgniter\Events\Events::on('post_system', function() use($UserModel){
            $UserModel->systemUserLogout();
        },1);
        return true;
    }
    
    
    
    public function check(){
        $data=$this->request->getJSON();
        if( !$this->authorize($data) ){
            return $this->fail(['code'=>13]);
        }
        
        $OrderModel=model('OrderModel');
        
        $order=$OrderModel->itemGet($data->InvoiceId,'basic');
        if( $order->stage_current!=='customer_confirmed' ){
            return $this->respond(['code'=>20]);//Платеж просрочен
        }
        if($order->order_sum_total!=$data->Amount){
            return $this->respond(['code'=>12]);//Неверная сумма
        }
        if($order->owner_id!=$data->AccountId){
            return $this->respond(['code'=>11]);//Некорректный AccountId
        }
        return $this->respond(['code'=>0]);
    }
    
    public function pay(){
        $data=$this->request->getJSON();
        if( !$this->authorize($data) ){
            return $this->fail(['code'=>403]);
        }
        
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemStageCreate( $data->InvoiceId, 'customer_payed_cloud', $data, false );
        if( $result=='ok' ){
            return $this->respond(['code'=>0]); 
        }
        return $this->fail($result); 
    }
    
    public function refund(){
        $data=$this->request->getJSON();
        if( !$this->authorize($data) ){
            return $this->fail(['code'=>403]);
        }
        
        if( !$data || !$data->InvoiceId??0 ){
            return $this->respond(['code'=>13]);
        }
        
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemStageCreate( $data->InvoiceId, 'customer_refunded', $data, false );
        if( $result=='ok' ){
            return $this->respond(['code'=>0]); 
        }
        return $this->respond(['code'=>500]); 
    }
    
    public function cancel(){
        $data=$this->request->getJSON();
        
        if( !$data || !$data->InvoiceId??0 ){
            return $this->respond(['code'=>13]);
        }
        
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemStageCreate( $data->InvoiceId, 'customer_refunded', $data, false );
        if( $result=='ok' ){
            return $this->respond(['code'=>0]); 
        }
        return $this->respond(['code'=>500]); 
    }
    
    public function paymentFail(){
        $data=$this->request->getJSON();
        $this->sendErrorEmail( $data );
        return $this->respond(['code'=>0]);
    }
    
    private function sendErrorEmail( $data ){
        $email = \Config\Services::email();
        $config=[
            'SMTPHost'=>getenv('email_server'),
            'SMTPUser'=>getenv('email_username'),
            'SMTPPass'=>getenv('email_password')
        ];
        $email->initialize($config);
        $email->setFrom(getenv('email_from'), getenv('email_sendername'));
        $email->setTo(getenv('email_admin'));
        $email->setSubject('Cloud Payments Error');
        $email->setMessage(json_encode($data));
        $email_send_ok=$email->send();
        if( !$email_send_ok ){
            return $this->fail($email->printDebugger(['headers']));
        }
    }
}
