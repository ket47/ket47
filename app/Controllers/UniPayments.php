<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class UniPayments extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function paymentLinkGet(){
        $order_id=$this->request->getVar('order_id');
        $order_sum_total=$this->request->getVar('order_sum_total');
        $user_id=$this->request->getVar('user_id');

        if( !$order_id??0 || !$order_sum_total??0 || !$user_id??0 ){
            return $this->fail('missing_required_fields');
        }
        $UserModel=model('UserModel');
        $user=$UserModel->itemGet($user_id);

        if( !is_object($user) ){
            return $this->failForbidden('user_notfound');
        }
        $p=(object)[
            'Shop_IDP' => getenv('uniteller.Shop_IDP'),
            'Order_IDP' => $order_id,
            'Subtotal_P' => $order_sum_total,
            'Customer_IDP' => $user_id,
            'Email' => $user->user_email??'',
            'Phone' => $user->user_phone,
            'PhoneVerified' => $user->user_phone,
            'FirstName'=>$user->user_name,
            'LastName'=>$user->user_surname,
            'MiddleName'=>$user->user_middlename,
            'URL_RETURN_OK'=>getenv('app.baseURL').'UniPayments/paymentOk',
            'URL_RETURN_NO'=>getenv('app.baseURL').'UniPayments/paymentNo',
            'Preauth'=>1,
            'IsRecurrentStart'=>0,
            'Lifetime' => 5*60*60,// 5 min
            'CallbackFields'=>'Total Balance ApprovalCode BillNumber'
            //'MeanType' => '','EMoneyType' => '','OrderLifetime' => 15*60*60,'Card_IDP' => '','IData' => '','PT_Code' => '',
        ];
        $p->Signature = strtoupper(
            md5(
                md5($p->Shop_IDP) . "&" .
                md5($p->Order_IDP) . "&" .
                md5($p->Subtotal_P) . "&" .
                md5($p->MeanType??'') . "&" .
                md5($p->EMoneyType??'') . "&" .
                md5($p->Lifetime??'') . "&" .
                md5($p->Customer_IDP) . "&" .
                md5($p->Card_IDP??'') . "&" .
                md5($p->IData??'') . "&" .
                md5($p->PT_Code??'') . "&" .
                md5($p->PhoneVerified??'') . "&" .
                md5( getenv('uniteller.password') )
            )
        );
        $queryString = http_build_query($p);
        return getenv('uniteller.gateway').'pay?'.$queryString;
    }

    public function paymentLinkGo(){
        $link=$this->paymentLinkGet();
        return $this->response->redirect($link);
    }

    public function paymentOk(){
        return view('payment/payment_result_ok');
    }

    public function paymentNo(){
        return view('payment/payment_result_no');
    }

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



    /////////////////////////////////////////////////////////////////////////////////
    //ORDER BALANCE FINAL SETTLEMENT SECTION
    /////////////////////////////////////////////////////////////////////////////////
    // public function paymentBalanceSettle( $order_id ){
    //     if( !$this->authorizeAsSystem($order_id) ){
    //         $this->log_message('error', "paymentBalanceSettle order_id:$order_id  CANT AUTORIZE AS SYSTEM. (ORDER_ID MAY BE WRONG)");
    //         return $this->fail('CANT AUTORIZE AS SYSTEM');
    //     }
    //     $TransactionModel=model('TransactionModel');
    //     $orderSettlement=$TransactionModel->orderSettlementGet($order_id);














    //     $orderSumToClaim=$order->order_sum_total;
    //     $orderSumToRefund=$moneyReserveTrans->trans_amount-$order->order_sum_total;

    //     if( $this->paymentIsRefunded($order_id) ){
    //         $orderSumToClaim=0;
    //         $orderSumToRefund=$order->order_sum_total;
    //     }

    //     if($orderSumToClaim){
    //         $result_claim=$this->paymentBalanceClaim($moneyReserveTransData->billNumber,$orderSumToClaim);
    //         if(!$result_claim){
    //             return false;
    //         }
    //         $trans=[
    //             'trans_amount'=>$order_id,
    //             'trans_data'=>json_encode($result_claim),
    //             'trans_role'=>'money.acquirer.blocked->money.acquirer.claimed',
    //             'trans_tags'=>"#order-$order_id,#customer-{$order->owner_id},#uniteller",
    //             'owner_id'=>$order->owner_id,
    //             'is_disabled'=>0,
    //             'holder'=>'order',
    //             'holder_id'=>$order_id
    //         ];
    //         $TransactionModel->itemCreate($trans);
    //     }
    //     if($orderSumToRefund){
    //         $result_refund=$this->paymentBalanceRefund($moneyReserveTransData->billNumber,$orderSumToRefund);
    //         if(!$result_refund){
    //             return false;
    //         }
    //         $trans=[
    //             'trans_amount'=>$order_id,
    //             'trans_data'=>json_encode($result_refund),
    //             'trans_role'=>'money.acquirer.blocked->customer.card',
    //             'trans_tags'=>"#order-$order_id,#customer-{$order->owner_id},#uniteller",
    //             'owner_id'=>$order->owner_id,
    //             'is_disabled'=>0,
    //             'holder'=>'order',
    //             'holder_id'=>$order_id
    //         ];
    //         $TransactionModel->itemCreate($trans);
    //     }
    //     return true;
    // }



    // private function paymentBalanceClaim($billNumber,$orderSumToClaim){
    //     $request=[
    //         'Billnumber'=>$billNumber,
    //         'Shop_ID'=>getenv('uniteller.Shop_IDP'),
    //         'Login'=>getenv('uniteller.login'),
    //         'Password'=>getenv('uniteller.password'),
    //         'Format'=>'1',
    //         'Subtotal_P'=>$orderSumToClaim,
    //         'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber'
    //     ];
    //     $context  = stream_context_create([
    //         'http' => [
    //             'header'  => "Content-type: application/x-www-form-urlencoded",
    //             'method'  => 'POST',
    //             'content' => http_build_query($request)
    //             ]
    //     ]);
    //     $result = file_get_contents(getenv('uniteller.gateway').'confirm/', false, $context);
    //     if(!$result){
    //         return null;
    //     }
    //     $response=explode(';',$result);
    //     return [
    //         'order_id'=>$response[0],
    //         'status'=>$response[1],
    //         'total'=>$response[2],
    //         'approvalCode'=>$response[3],
    //         'billNumber'=>$response[4]
    //     ];
    // }

    // private function paymentBalanceRefund($billNumber,$orderSumToRefund){
    //     $request=[
    //         'Billnumber'=>$billNumber,
    //         'Shop_ID'=>getenv('uniteller.Shop_IDP'),
    //         'Login'=>getenv('uniteller.login'),
    //         'Password'=>getenv('uniteller.password'),
    //         'Format'=>'1',
    //         'Subtotal_P'=>$orderSumToRefund,
    //         'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber'
    //     ];
    //     $context  = stream_context_create([
    //         'http' => [
    //             'header'  => "Content-type: application/x-www-form-urlencoded",
    //             'method'  => 'POST',
    //             'content' => http_build_query($request)
    //             ]
    //     ]);
    //     $result = file_get_contents(getenv('uniteller.gateway').'unblock/', false, $context);
    //     if(!$result){
    //         return null;
    //     }
    //     $response=explode(';',$result);
    //     return [
    //         'order_id'=>$response[0],
    //         'status'=>$response[1],
    //         'total'=>$response[2],
    //         'approvalCode'=>$response[3],
    //         'billNumber'=>$response[4]
    //     ];
    // }
















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
        $email->setSubject(getenv('app.baseURL').' Uniteller Payment Error');
        $email->setMessage($data);
        $email_send_ok=$email->send();
        if( !$email_send_ok ){
            log_message('error', $email->printDebugger(['headers']) );
        }
    }
 
}
