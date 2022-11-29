<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class CardAcquirer extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function statusGet(){
        $order_id=$this->request->getVar('order_id');
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->statusGet($order_id);
        if($result && isset($result->order_id)){
            return $this->statusApply($result);
        }
        return $this->fail('acquirer_rejected');
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
        if( !$this->authorizeAsSystem($incomingStatus->order_id) ){
            $this->log_message('error', "paymentStatusSet $incomingStatus->status; order_id:#$incomingStatus->order_id  CANT AUTORIZE AS SYSTEM. (ORDER_ID MAY BE WRONG)");
            return $this->fail('CAN\'T AUTHORIZE AS SYSTEM');
        }
        $OrderModel=model('OrderModel');
        $result='ok';
        switch(strtolower($incomingStatus->status)){
            case 'authorized':
            case 'paid':
                if( $this->paymentIsDone($incomingStatus->order_id) ){
                    return $this->respond('OK');
                }
                $result=$OrderModel->itemStageAdd( $incomingStatus->order_id, 'customer_payed_card', $incomingStatus, false );
                break;
            case 'canceled':
                if( $this->paymentIsRefunded($incomingStatus->order_id) ){
                    return $this->respond('OK');
                }
                $result=$OrderModel->itemStageCreate( $incomingStatus->order_id, 'customer_refunded', $incomingStatus, false );
                break;
            case 'partly canceled':
            case 'waiting':
                break;
            default:
                $this->log_message('error', "paymentStatusSet $incomingStatus->status; wrong_status");
                return $this->failValidationErrors('wrong_status');
                break;
        }
        if( $result=='ok' ){
            return $this->respond('OK'); 
        }
        $this->log_message('error', "paymentStatusSet $incomingStatus->status; order_id:#$incomingStatus->order_id; STAGE CANT BE CHANGED $result=='ok'");
        return $this->fail('cant_change_order_stage');     
    }

    public function orderStatusReport(){//do we need this???
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->orderStatusReport($this->request);
        if( $result=='unauthorized' ){
            return $this->failUnauthorized();
        }
        if( !$this->authorizeAsSystem($result->order_id) ){
            $this->log_message('error', "paymentStatusCheck; order_id:$result->order_id CAN'T AUTHORIZE AS SYSTEM");
            return $this->failUnauthorized();
        }

        $OrderModel=model('OrderModel');
        $order=$OrderModel->itemGet($result->order_id);
        if( $order->stage_current==='customer_confirmed' ){
            $this->log_message('error', "paymentStatusCheck; order_id:$result->order_id = REPORTED AS NEW");
            return $this->respond($result->new);//Заказ не оплачен
        }
        if( $this->paymentIsDone($result->order_id) ){
            return $this->respond($result->paid);//Заказ оплачен
        }
        $this->log_message('error', "paymentStatusCheck; order_id:$result->order_id = REPORTED AS CANCELED");
        return $this->respond($result->canceled);//Заказ отменен или не готов к оплате
    }
    ///////////////////////////////////////////////////////////////////////
    //PAYMENT SECTION
    ///////////////////////////////////////////////////////////////////////
    
    public function paymentLinkGet(){
        $order_id=$this->request->getVar('order_id');
        $Acquirer=\Config\Services::acquirer();
        $result=$Acquirer->linkGet($order_id);
        if( in_array($result,['order_notfound','order_notvalid','user_notfound','store_notready','card_payment_notallowed']) ){
            return $this->fail($result);
        }
        return $result;
    }

    public function paymentLinkGo(){
        $link=$this->paymentLinkGet();
        return $this->response->redirect($link);
    }

    public function pageOk(){
        return view('payment/payment_result_ok');
    }

    public function pageNo(){
        return view('payment/payment_result_no');
    }

    private function paymentIsDone( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->isMemberOf($order_id,'customer_payed_card');
    }
    
    private function paymentIsRefunded( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->isMemberOf($order_id,'customer_refunded');
    }









    /*

    public function paymentStatusRequest(){
        $order_id=$this->request->getVar('order_id');
        $request=[
            'Shop_ID'=>getenv('uniteller.Shop_IDP'),
            'Login'=>getenv('uniteller.login'),
            'Password'=>getenv('uniteller.password'),
            'Format'=>'1',
            'ShopOrderNumber'=>$order_id,
            'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('uniteller.gateway').'results/', false, $context);
        if(!$result){
            return 'noresponse';
        }
        $response=explode(';',$result);
        return $this->paymentStatusSetApply($response[0],$response[1],$response[2],$response[2],$response[3],$response[4]);
    }



    public function paymentStatusSet(){
        $order_id=$this->request->getVar('Order_ID');
        $status=$this->request->getVar('Status');
        $signature=$this->request->getVar('Signature');
        $total=$this->request->getVar('Total');
        $balance=$this->request->getVar('Balance');
        $approvalCode=$this->request->getVar('ApprovalCode');
        $billNumber=$this->request->getVar('ApprovalCode');

        $signature_check = strtoupper(md5($order_id.$status.$total.$balance.$approvalCode.$billNumber.getenv('uniteller.password')));
        if($signature!=$signature_check){
            $this->log_message('error', "paymentStatusSet $status; order_id:$order_id SIGNATURES NOT MATCH $signature!=$signature_check");
            return $this->failUnauthorized();
        }
        return $this->paymentStatusSetApply($order_id,$status,$total,$balance,$approvalCode,$billNumber);
    }

    private function paymentStatusSetApply($order_id,$status,$total,$balance,$approvalCode,$billNumber){
        if( !$this->authorizeAsSystem($order_id) ){
            $this->log_message('error', "paymentStatusSet $status; order_id:$order_id  CANT AUTORIZE AS SYSTEM. (ORDER_ID MAY BE WRONG)");
            return $this->respond('CANT AUTORIZE AS SYSTEM');
        }
        $data=(object)[
            'total'=>$total,
            'balance'=>$balance,
            'approvalCode'=>$approvalCode,
            'billNumber'=>$billNumber
        ];
        $OrderModel=model('OrderModel');
        $result='ok';
        switch(strtolower($status)){
            case 'authorized':
            case 'paid':
                if( $this->paymentIsDone($order_id) ){
                    return $this->respond('OK');
                }
                $result=$OrderModel->itemStageCreate( $order_id, 'customer_payed_card', $data, false );
                break;
            case 'canceled':
                if( $this->paymentIsRefunded($order_id) ){
                    return $this->respond('OK');
                }
                $result=$OrderModel->itemStageCreate( $order_id, 'customer_refunded', $data, false );
                break;
            case 'partly canceled':
            case 'waiting':
                break;
            default:
                $this->log_message('error', "paymentStatusSet $status; wrong_status");
                return $this->failValidationErrors('wrong_status');
                break;
        }
        if( $result=='ok' ){
            return $this->respond('OK'); 
        }
        $this->log_message('error', "paymentStatusSet $status; order_id:$order_id; STAGE CANT BE CHANGED $result=='ok'");
        return $this->failValidationErrors('cant_change_order_stage');        
    }
    
    private function paymentIsDone( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->isMemberOf($order_id,'customer_payed_card');
    }
    
    private function paymentIsRefunded( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        return $OrderGroupMemberModel->isMemberOf($order_id,'customer_refunded');
    }

    public function paymentStatusCheck(){
        $order_id=$this->request->getVar('order_id');
        $upoint_id=$this->request->getVar('upoint_id');
        if( $upoint_id!=getenv('uniteller.Shop_IDP') ){
            $this->log_message('error', "paymentStatusCheck; order_id:$order_id Shop_IDP DO NOT MATCH upoint_id:$upoint_id");
            return $this->failUnauthorized();
        }
        if( !$this->authorizeAsSystem($order_id) ){
            $this->log_message('error', "paymentStatusCheck; order_id:$order_id CANT AUTORIZE AS SYSTEM");
            return $this->failUnauthorized();
        }
        $OrderModel=model('OrderModel');
        $order=$OrderModel->itemGet($order_id);
        if( $order->stage_current==='customer_confirmed' ){
            $this->log_message('error', "paymentStatusCheck; order_id:$order_id = REPORTED AS NEW");
            return $this->respond('NEW');//Заказ не оплачен
        }
        if( $this->paymentIsDone($order_id) ){
            //$this->log_message('error', "paymentStatusCheck; order_id:$order_id = REPORTED AS PAID");
            return $this->respond('PAID');//Заказ оплачен
        }
        $this->log_message('error', "paymentStatusCheck; order_id:$order_id = REPORTED AS CANCELED");
        return $this->respond('CANCELLED');
    }


    */

    private function log_message($severity,$message){
        log_message($severity, $message);
        $this->sendErrorEmail($message);
    }

    private function authorizeAsSystem( $order_id ){
        if( !$order_id ){
            return false;
        }
        $UserModel=model('UserModel');
        $OrderModel=model('OrderModel');
        
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
