<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

use function PHPUnit\Framework\stringContains;

class Cardacquirer extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function statusGet(){
        $order_id=$this->request->getVar('order_id');
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->statusGet($order_id);
        if($result && isset($result->order_id)){
            return $this->statusApply($result);
        }
        return $this->respond('notpayed');
    }


    ///////////////////////////////////////////////////////////////////////
    //ACQUIRER INCOMING REQUESTS SECTION
    ///////////////////////////////////////////////////////////////////////
    public function statusSet(){//handle request from acquirer //do we need this???
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->statusParse($this->request);
        if( $result=='unauthorized' ){
            return $this->failUnauthorized();
        }
        return $this->statusApply($result);
    }
    private function statusApply($incomingStatus){
        $order_id_full=$incomingStatus->order_id;//with affix if any
        if( !$this->authorizeAsSystem($order_id_full) ){
            $this->log_message('error', "paymentStatusSet $incomingStatus->status; order_id:#$order_id_full  CANT AUTORIZE AS SYSTEM. (ORDER_ID MAY BE WRONG)");
            return $this->fail('CAN\'T AUTHORIZE AS SYSTEM');
        }
        list($order_id)=explode('-',$order_id_full);
        if( str_contains($order_id_full,'s') ){//is shipping
            $OrderModel=model('ShipmentModel');
        } else {
            $OrderModel=model('OrderModel');
        }
        $result='ok';
        switch(strtolower($incomingStatus->status)){
            case 'authorized':
            case 'paid':
                if( $this->paymentIsDone($order_id) ){
                    return $this->respond('OK');
                }
                $result=$OrderModel->itemStageAdd( $order_id, 'customer_payed_card', $incomingStatus, false );
                break;
            case 'canceled':
                if( $this->paymentIsRefunded($order_id) ){
                    return $this->respond('OK');
                }
                $result=$OrderModel->itemStageCreate( $order_id, 'customer_refunded', $incomingStatus, false );
                break;
            case 'partly canceled':
            case 'waiting':
                $this->log_message('error', "paymentStatusSet $incomingStatus->status; Waiting what?");
                return $this->failValidationErrors('waiting');
                break;
            case 'not authorized':
                $this->log_message('error', " order_id:#$order_id_full paymentStatusSet:'$incomingStatus->status'; Not enough money? ".json_encode($incomingStatus));
                return $this->failValidationErrors('not_authorized');
                break;
            default:
                $this->log_message('error', "paymentStatusSet $incomingStatus->status; wrong_status");
                return $this->failValidationErrors('wrong_status');
                break;
        }
        if( $result=='ok' ){
            return $this->respond('OK'); 
        }
        $this->log_message('error', "paymentStatusSet $incomingStatus->status; order_id:#$order_id_full; STAGE CANT BE CHANGED $result=='ok'");
        return $this->fail('cant_change_order_stage');     
    }

    // public function orderStatusReport(){//do we need this???
    //     $Acquirer=\Config\Services::acquirer();
    //     $result=$Acquirer->orderStatusReport($this->request);
    //     if( $result=='unauthorized' ){
    //         return $this->failUnauthorized();
    //     }
    //     if( !$this->authorizeAsSystem($result->order_id) ){
    //         $this->log_message('error', "paymentStatusCheck; order_id:$result->order_id CAN'T AUTHORIZE AS SYSTEM");
    //         return $this->failUnauthorized();
    //     }

    //     $OrderModel=model('OrderModel');
    //     $order=$OrderModel->itemGet($result->order_id);
    //     if( $order->stage_current==='customer_confirmed' ){
    //         $this->log_message('error', "paymentStatusCheck; order_id:$result->order_id = REPORTED AS NEW");
    //         return $this->respond($result->new);//Заказ не оплачен
    //     }
    //     if( $this->paymentIsDone($result->order_id) ){
    //         return $this->respond($result->paid);//Заказ оплачен
    //     }
    //     $this->log_message('error', "paymentStatusCheck; order_id:$result->order_id = REPORTED AS CANCELED");
    //     return $this->respond($result->canceled);//Заказ отменен или не готов к оплате
    // }
    ///////////////////////////////////////////////////////////////////////
    //PAYMENT SECTION
    ///////////////////////////////////////////////////////////////////////
    
    public function paymentLinkGet(){
        $order_id_full=$this->request->getVar('order_id');
        $Acquirer=\Config\Services::acquirer();
        $paymentStatus=$Acquirer->statusGet($order_id_full,'beforepayment');

        if( isset($paymentStatus->status) && $paymentStatus->status=='Authorized' ){
            return $this->fail('already_payed');
        }
        $result=$this->orderValidate($order_id_full);
        if( $result!='ok' ){
            return $this->fail($result);
        }
        list($order_id)=explode('-',$order_id_full);
        // if( str_contains($order_id_full,'s') ){//is shipping
        //     $OrderModel=model('ShipmentModel');
        // } else {
            
        // }
        $OrderModel=model('OrderModel');
        $order_all=$OrderModel->itemGet($order_id,'all');

        $await_payment_timeout=time()+10*60;//10min
        $OrderModel->itemDataUpdate($order_id,(object)['await_payment_until'=>$await_payment_timeout]);
        return $Acquirer->linkGet($order_all);
    }

    public function paymentDo(){
        $order_id_full=$this->request->getVar('order_id');
        $card_id=$this->request->getVar('card_id');
        $result=$this->orderValidate($order_id_full);
        if( $result!='ok' ){
            return $this->fail($result);
        }
        if( !$card_id ){
            return $this->fail('nocardid');
        }
        $Acquirer=\Config\Services::acquirer();
        list($order_id)=explode('-',$order_id_full);
        // if( str_contains($order_id_full,'s') ){//is shipping
        //     $OrderModel=model('ShipmentModel');
        // } else {
            
        // }
        $OrderModel=model('OrderModel');
        $order_all=$OrderModel->itemGet($order_id,'all');
        $result=$Acquirer->pay($order_all,$card_id);
        if( $result ){
            return $this->respond('ok');
        }
        return $this->fail('not_authorized');
    }

    private function orderValidate( $order_id_full ){
        list($order_id)=explode('-',$order_id_full);
        // if( str_contains($order_id_full,'s') ){//is shipping
        //     $OrderModel=model('ShipmentModel');
        // } else {
           
        // }
        $OrderModel=model('OrderModel');
        $order_all=$OrderModel->itemGet($order_id,'all');

        if( !is_object($order_all) ){
            return 'order_notfound';
        }
        $order_data=$OrderModel->itemDataGet($order_id);
        if( !($order_all->order_sum_total>0) || !in_array($order_all->stage_current,['customer_confirmed','customer_draft']) ){
            return 'order_notvalid';
        }
        // if we will use customer_await then store can be not ready
        
        // $store_is_ready=$StoreModel->itemIsReady($order_all->order_store_id);
        // if( $store_is_ready!==1 ){
        //     return 'store_notready';
        // }
        if( !($order_all->customer??null) ){
            return 'user_notfound';
        }
        if( ($order_data->payment_by_card??0)!=1 ){
            return 'card_payment_notallowed';
        }
        return 'ok';
    }








    public function pageOk(){
        return view('payment/payment_result_ok');
    }

    public function pageNo(){
        return view('payment/payment_result_no');
    }
    
    public function cardRegisterLinkGet(){
        $user_id=session()->get('user_id');
        if( !($user_id>0) ){
            return $this->failForbidden('forbidden');
        }
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->cardRegisterLinkGet($user_id);
        if($result=='nocardid'){
            return $this->fail($result);
        }
        return $result;
    }

    public function cardRegisterActivate(){
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->cardRegisterActivate();
        if( $result=='ok' ){
            return $this->respond($result);
        }
        return $this->fail($result);
    }

    private function paymentIsDone( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->isMemberOf($order_id,'customer_payed_card');
    }
    
    private function paymentIsRefunded( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->isMemberOf($order_id,'customer_refunded');
    }

    private function log_message($severity,$message){
        log_message($severity, $message);
        $this->sendErrorEmail($message);
    }

    private function authorizeAsSystem( $order_id_full ){
        if( !$order_id_full ){
            return false;
        }
        list($order_id)=explode('-',$order_id_full);
        // if( str_contains($order_id_full,'s') ){//is shipping
        //     $OrderModel=model('ShipmentModel');
        // } else {
            
        // }
        $OrderModel=model('OrderModel');
        $UserModel=model('UserModel');
        
        $order_owner_id=$OrderModel
                ->select('owner_id')
                ->where('order_id',$order_id)
                ->get()
                ->getRow('owner_id');
        if( !$order_owner_id ){
            log_message('error', "authorizeAsSystem order_id:$order_id NO SUCH ORDER IN SYSTEM");
            return false;
        }
        $UserModel->systemUserLogin();
        \CodeIgniter\Events\Events::on('post_system', function() use($UserModel){
            $UserModel->systemUserLogout();
        },1);
        return true;
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
        $email->setSubject(getenv('app.baseURL').' TEZKELAPP Uniteller Payment Error');
        $email->setMessage($data);
        $email_send_ok=$email->send();
        if( !$email_send_ok ){
            log_message('error', $email->printDebugger(['headers']) );
        }
    }
 
}
