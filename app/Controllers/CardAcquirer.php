<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;
class Cardacquirer extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function statusGet(){
        $order_id=$this->request->getVar('order_id');
        $OrderModel=model('OrderModel');
        $orderData=$OrderModel->itemDataGet($order_id);
        
        $payment_card_acquirer=$orderData->payment_card_acquirer??'AcquirerRncb';
        $Acquirer=\Config\Services::acquirer(true,$payment_card_acquirer);
        $result=$Acquirer->statusGet($order_id);
        //pl($payment_card_acquirer,$result);

        if($result && isset($result->order_id)){
            return $this->statusApply($result);
        }
        return $this->respond('notpayed');
    }


    ///////////////////////////////////////////////////////////////////////
    //ACQUIRER INCOMING REQUESTS SECTION
    ///////////////////////////////////////////////////////////////////////
    public function statusSet(){
        $Acquirer=new \App\Libraries\AcquirerUniteller();
        $result=$Acquirer->statusParse($this->request);
        if( $result=='unauthorized' ){
            return $this->failUnauthorized();
        }
        return $this->statusApply($result);
    }
    // public function statusHook(){
    //     $Acquirer=new \App\Libraries\AcquirerRncb();
    //     $result=$Acquirer->statusParse($this->request);
    //     if( $result=='unauthorized' ){
    //         return $this->failUnauthorized();
    //     }
    //     return $this->statusApply($result);
    // }

    private function statusApply( object $incomingStatus ){
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
                madd('order','pay','error',$order_id,$incomingStatus->status);
                return $this->failValidationErrors('waiting');
                break;
            case 'not authorized':
                //$this->log_message('error', " order_id:#$order_id paymentStatusSet:'$incomingStatus->status'; Not enough money? ".json_encode($incomingStatus));
                madd('order','pay','error',$order_id,$incomingStatus->status);
                return $this->failValidationErrors('not_authorized');
                break;
            default:
                $this->log_message('error', "paymentStatusSet $incomingStatus->status; wrong_status");
                madd('order','pay','error',$order_id,$incomingStatus->status);
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
        $payment_type=$this->request->getPost('payment_type');

        $OrderModel=model('OrderModel');
        $order_all=$OrderModel->itemGet($order_id,'all');
        $orderData=$OrderModel->itemDataGet($order_id);

        $result=$this->orderValidate($order_id);
        if( $result!='ok' ){
            return $this->fail($result);
        }

        $orderDataUpdate=(object)[];
        if( $payment_type=='use_card' ){// || isset($orderData->payment_card_acquirer) && $orderData->payment_card_acquirer=='AcquirerUniteller'
            $Acquirer=\Config\Services::acquirer(true,'AcquirerUniteller');
            $orderDataUpdate->payment_card_acquirer='AcquirerUniteller';
        } else
        if( $payment_type=='use_card_sbp' ){// || isset($orderData->payment_card_acquirer) && $orderData->payment_card_acquirer=='AcquirerUnitellerSBP'
            $Acquirer=\Config\Services::acquirer(true,'AcquirerUnitellerSBP');
            $orderDataUpdate->payment_card_acquirer='AcquirerUnitellerSBP';
        } else {
            $Acquirer=\Config\Services::acquirer(true,'AcquirerRncb');
            $orderDataUpdate->payment_card_acquirer='AcquirerRncb';
        }

        $isAlreadyPayed=$Acquirer->statusCheck( $order_id );
        if( 'ok'==$isAlreadyPayed ){//order is already payed and started
            return $this->fail('already_payed');
        }
        /**
         * If link was created within timeout then reuse it.
         * Otherwise create new order on acq
         */
        if( ($orderData->await_payment_until??0)>time() && ($orderData->payment_card_acq_url??null) ){
            return $orderData->payment_card_acq_url;
        }
        $paymentLink=$Acquirer->linkGet($order_all,$enable_auto_cof);
        $orderDataUpdate->await_payment_timeout=time()+10*60;//10min
        $OrderModel->itemDataUpdate($order_id,$orderDataUpdate);
        return $paymentLink;
    }

    public function paymentDo(){
        $order_id=$this->request->getPost('order_id');
        $result=$this->orderValidate($order_id);
        if( $result!='ok' ){
            return $this->fail($result);
        }
        $OrderModel=model('OrderModel');
        $UserCardModel=model('UserCardModel');

        $order_all=$OrderModel->itemGet($order_id,'all');
        $CoF=$UserCardModel->itemMainGet($order_all->owner_id);
        if( $CoF=='notfound' ){
            return $this->fail('error_nocof');
        }
        if( $CoF->card_acquirer=='rncbCard' ){
            $Acquirer=\Config\Services::acquirer(true,'AcquirerRncb');
        } else 
        if( $CoF->card_acquirer=='AcquirerUnitellerSBP' ){
            $Acquirer=\Config\Services::acquirer(true,'AcquirerUnitellerSBP');
        }
        $result=$Acquirer->recurrentPay($CoF->card_remote_id,$order_all,$order_all->owner_id);
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
        $Acquirer=\Config\Services::acquirer(true,'AcquirerRncb');
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
        //$Acquirer=new \App\Libraries\AcquirerRncb();//\Config\Services::acquirer();
        //$result=$Acquirer->cardRegisteredSync($user_id);
        
        $AcquirerUnitellerSBP=\Config\Services::acquirer(true,'AcquirerRncb');
        $result=$AcquirerUnitellerSBP->cardRegisteredSync($user_id);
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
