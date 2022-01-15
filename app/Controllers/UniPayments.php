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
            'IsRecurrentStart'=>0,
            'Lifetime' => 5*60*60,// 5 min
            'CallbackFields'=>'Total Balance ApprovalCode'
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
        return $this->response->redirect(getenv('uniteller.gateway').'pay?'.$queryString);
    }

    public function paymentOk(){
        echo 'Payment successfull!!!!!';
    }

    public function paymentNo(){
        echo 'Payment basarisiz?!?!?!?';
    }

    public function paymentStatusSet(){
        $order_id=$this->request->getVar('Order_ID');
        $status=$this->request->getVar('Status');
        $signature=$this->request->getVar('Signature');
        $total=$this->request->getVar('Total');
        $balance=$this->request->getVar('Balance');
        $approvalCode=$this->request->getVar('ApprovalCode');


        //$this->log_message('error',"$order_id.$status.$total.$balance.$approvalCode".getenv('uniteller.password'));

        $signature_check = strtoupper(md5($order_id.$status.$total.$balance.$approvalCode.getenv('uniteller.password')));
        if($signature!=$signature_check){
            $this->log_message('error', "paymentStatusSet $status; order_id:$order_id SIGNATURES NOT MATCH $signature!=$signature_check");
            return $this->failUnauthorized();
        }
        if( !$this->authorizeAsSystem($order_id) ){
            $this->log_message('error', "paymentStatusSet $status; order_id:$order_id  CANT AUTORIZE AS SYSTEM");
            return $this->failUnauthorized();
        }
        $data=(object)[
            'total'=>$total,
            'balance'=>$balance,
            'approvalCode'=>$approvalCode
        ];
        $OrderModel=model('OrderModel');
        switch($status){
            case 'authorized':
                $result=$OrderModel->itemStageCreate( $order_id, 'customer_payed_card', $data, false );
                break;
            case 'paid':
                $result=$OrderModel->itemStageCreate( $order_id, 'customer_payed_card', $data, false );
                break;
            case 'canceled':
                $result=$OrderModel->itemStageCreate( $order_id, 'customer_refunded', $data, false );
                break;
            case 'partly canceled':
                $result=$OrderModel->itemStageCreate( $order_id, 'customer_refunded', $data, false );
                break;
            case 'waiting':
                $result='ok';
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

    public function paymentStatusCheck(){
        $order_id=$this->request->getVar('order_id');
        $upoint_id=$this->request->getVar('upoint_id');
        if( $upoint_id!=getenv('uniteller.Shop_IDP') ){
            $this->log_message('error', "paymentStatusCheck; order_id:$order_id Shop_IDP NOT MATCH $upoint_id");
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
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $customer_payed_card=$OrderGroupMemberModel->where('group_type','customer_payed_card')->memberOfGroupsGet($order->order_id);
        if($customer_payed_card){
            $this->log_message('error', "paymentStatusCheck; order_id:$order_id = REPORTED AS PAID");
            return $this->respond('PAID');//Заказ оплачен
        }
        $this->log_message('error', "paymentStatusCheck; order_id:$order_id = REPORTED AS CANCELED");
        return $this->respond('CANCELLED');
    }

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
