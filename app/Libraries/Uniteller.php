<?php
namespace App\Libraries;
class Uniteller{
    public function linkGet(){
        $UserModel=model('UserModel');
        $user=$UserModel->itemGet($user_id);

        if( !is_object($user) ){
            return 'user_notfound';
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



    public function preauth(){

    }

    public function settle(){

    }

    public function claim(){

    }

    public function refund(){

    }
}