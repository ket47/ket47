<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

use function PHPUnit\Framework\stringContains;

class Cardacquirer extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function statusGet(){
        $order_id=$this->request->getVar('order_id');
        $OrderModel=model('OrderModel');
        $order_data=$OrderModel->itemDataGet($order_id);
        if($order_data->payment_card_acq_rncb??0){
            $Acquirer=new \App\Libraries\AcquirerRncb();
        } else {
            $Acquirer=\Config\Services::acquirer();
        }
        $result=$Acquirer->statusGet($order_id);
        if($result && isset($result->order_id)){
            return $this->statusApply($result);
        }
        return $this->respond('notpayed');
    }


    ///////////////////////////////////////////////////////////////////////
    //ACQUIRER INCOMING REQUESTS SECTION
    ///////////////////////////////////////////////////////////////////////
    public function statusSet(){
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->statusParse($this->request);
        if( $result=='unauthorized' ){
            return $this->failUnauthorized();
        }
        return $this->statusApply($result);
    }
    public function statusHook(){
        $Acquirer=new \App\Libraries\AcquirerRncb();
        $result=$Acquirer->statusParse($this->request);
        if( $result=='unauthorized' ){
            return $this->failUnauthorized();
        }
        return $this->statusApply($result);
    }

    private function statusApply($incomingStatus){
        $order_id=$incomingStatus->order_id;
        if( !$this->authorizeAsSystem($order_id) ){
            $this->log_message('error', "paymentStatusSet $incomingStatus->status; order_id:#$order_id  CANT AUTORIZE AS SYSTEM. (ORDER_ID MAY BE WRONG)");
            return $this->fail('CAN\'T AUTHORIZE AS SYSTEM');
        }
        $OrderModel=model('OrderModel');
        $result='ok';
        switch(strtolower($incomingStatus->status)){
            case 'authorized':
            case 'paid':
                if( $this->paymentIsDone($order_id) ){
                    madd('order','pay','ok',$order_id);
                    return $this->respond('OK');
                }
                $result=$OrderModel->itemStageAdd( $order_id, 'customer_payed_card', $incomingStatus, false );
                break;
            case 'canceled':
                return $this->respond('OK');
                break;
            case 'partly canceled':
            case 'waiting':
                $this->log_message('error', "paymentStatusSet $incomingStatus->status; Waiting what?");
                return $this->failValidationErrors('waiting');
                break;
            case 'not authorized':
                $this->log_message('error', " order_id:#$order_id paymentStatusSet:'$incomingStatus->status'; Not enough money? ".json_encode($incomingStatus));
                return $this->failValidationErrors('not_authorized');
                break;
            default:
                $this->log_message('error', "paymentStatusSet $incomingStatus->status; wrong_status");
                return $this->failValidationErrors('wrong_status');
                break;
        }
        if( $result=='ok' ){
            madd('order','pay','ok',$order_id);
            return $this->respond('OK'); 
        }
        madd('order','pay','error',$order_id,$incomingStatus->status);
        $this->log_message('error', "paymentStatusSet $incomingStatus->status; order_id:#$order_id; STAGE CANT BE CHANGED $result=='ok'");
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
        $order_id=$this->request->getPost('order_id');
        $enable_auto_cof=$this->request->getPost('enable_auto_cof');

        $ua=$this->request->getUserAgent();
        $platform=$ua->getPlatform();
        $browser=$ua->getBrowser();


        $is_ios_webview=$platform=='iOS' && $browser=='Mozilla';
        $Acquirer=\Config\Services::acquirer(true,$is_ios_webview);
        $isAlreadyPayed=$Acquirer->statusCheck( $order_id );
        if( 'ok'==$isAlreadyPayed ){//order is already payed and started
            return $this->fail('already_payed');
        }
        $result=$this->orderValidate($order_id);
        if( $result!='ok' ){
            return $this->fail($result);
        }
        $OrderModel=model('OrderModel');
        $order_all=$OrderModel->itemGet($order_id,'all');
        $orderData=$OrderModel->itemDataGet($order_id);
        /**
         * If link was created within timeout then reuse it.
         * Otherwise create new order on acq
         */
        if( ($orderData->await_payment_until??0)>time() && ($orderData->payment_card_acq_url??null) ){
            return $orderData->payment_card_acq_url;
        }
        $paymentLink=$Acquirer->linkGet($order_all,$enable_auto_cof);
        $await_payment_timeout=time()+10*60;//10min
        $OrderModel->itemDataUpdate($order_id,(object)['await_payment_until'=>$await_payment_timeout]);
        return $paymentLink;
    }

    public function paymentDo(){
        $order_id=$this->request->getPost('order_id');
        $card_id=$this->request->getPost('card_id');
        $result=$this->orderValidate($order_id);
        if( $result!='ok' ){
            return $this->fail($result);
        }
        if( !$card_id ){
            return $this->fail('error_nocof');
        }
        $Acquirer=new \App\Libraries\AcquirerRncb();//\Config\Services::acquirer();
        $OrderModel=model('OrderModel');
        $order_all=$OrderModel->itemGet($order_id,'all');
        $result=$Acquirer->pay($order_all,$card_id);
        if( $result=='ok' ){
            return $this->respond('ok');
        }
        return $this->fail($result);
    }

    private function orderValidate( $order_id ){
        $OrderModel=model('OrderModel');
        $order_all=$OrderModel->itemGet($order_id,'all');

        if( !is_object($order_all) ){
            return 'order_notfound';
        }
        $order_data=$OrderModel->itemDataGet($order_id);
        if( !($order_all->order_sum_total>0) || !in_array($order_all->stage_current,['customer_confirmed']) ){
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
    
    public function cardRegisteredLinkGet(){
        $user_id=session()->get('user_id');
        if( !($user_id>0) ){
            return $this->failForbidden('forbidden');
        }
        $Acquirer=new \App\Libraries\AcquirerRncb();//\Config\Services::acquirer();
        $result=$Acquirer->cardRegisteredLinkGet($user_id);
        if( !$result || $result=='nocardid' ){
            return $this->fail($result);
        }
        return $result;
    }

    public function cardRegisteredSync(){
        $user_id=session()->get('user_id');
        if( !($user_id>0) ){
            return $this->failForbidden('forbidden');
        }
        $Acquirer=new \App\Libraries\AcquirerRncb();//\Config\Services::acquirer();
        $result=$Acquirer->cardRegisteredSync($user_id);
        if( $result=='ok' ){
            return $this->respond($result);
        }
        return $this->fail($result);
    }
    
    
    public function cardRegisterLinkGet(){
        return $this->cardRegisteredLinkGet();
    }
    
    public function cardRegisterActivate(){
        return $this->cardRegisteredSync();
    }
    
    public function cardRegisteredActivate(){
        return $this->cardRegisteredSync();
    }

    private function paymentIsDone( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->isMemberOf($order_id,'customer_payed_card');
    }
    
    // private function paymentIsRefunded( $order_id ){
    //     $OrderGroupMemberModel=model('OrderGroupMemberModel');
    //     return $OrderGroupMemberModel->isMemberOf($order_id,'customer_refunded');
    // }

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
