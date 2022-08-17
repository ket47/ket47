<?php
namespace App\Libraries;
class AcquirerUniteller{
    public function linkGet($order_id){
        $order_basic=model('OrderModel')->itemGet($order_id,'basic');
        if( !is_object($order_basic) ){
            return 'order_notfound';
        }
        $customer=model('UserModel')->itemGet($order_basic->owner_id);
        if( !is_object($customer) ){
            return 'user_notfound';
        }
        $p=(object)[
            'Shop_IDP' => getenv('uniteller.Shop_IDP'),
            'Order_IDP' => $order_id,
            'Subtotal_P' => $order_basic->order_sum_total,
            'Customer_IDP' => $customer->user_id,
            'Email' => $customer->user_email??'',
            'Phone' => $customer->user_phone,
            'PhoneVerified' => $customer->user_phone,
            'FirstName'=>$customer->user_name,
            'LastName'=>$customer->user_surname,
            'MiddleName'=>$customer->user_middlename,
            'URL_RETURN_OK'=>getenv('app.baseURL').'CardAcquirer/pageOk',
            'URL_RETURN_NO'=>getenv('app.baseURL').'CardAcquirer/pageNo',
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

    public function statusGet($order_id){
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
            return null;
        }
        $response=explode(';',$result);
        return (object)[
            'order_id'=>$response[0],
            'status'=>$response[1],
            'total'=>$response[2],
            'approvalCode'=>$response[3],
            'billNumber'=>$response[4]
        ];
    }

    public function statusParse($request){
        $order_id=$request->getVar('Order_ID');
        $status=$request->getVar('Status');
        $signature=$request->getVar('Signature');
        $total=$request->getVar('Total');
        $balance=$request->getVar('Balance');
        $approvalCode=$request->getVar('ApprovalCode');
        $billNumber=$request->getVar('ApprovalCode');

        $signature_check = strtoupper(md5($order_id.$status.$total.$balance.$approvalCode.$billNumber.getenv('uniteller.password')));
        if($signature!=$signature_check){
            log_message('error', "paymentStatusSet $status; order_id:$order_id SIGNATURES NOT MATCH $signature!=$signature_check");
            return 'unauthorized';
        }
        return (object)[
            'order_id'=>$order_id,
            'status'=>$status,
            'total'=>$total,
            'balance'=>$balance,
            'approvalCode'=>$approvalCode,
            'billNumber'=>$billNumber
        ];
    }

    public function orderStatusReport($request){
        $order_id=$request->getVar('order_id');
        $upoint_id=$request->getVar('upoint_id');
        if( $upoint_id!=getenv('uniteller.Shop_IDP') ){
            log_message('error', "paymentStatusCheck; order_id:$order_id Shop_IDP DO NOT MATCH upoint_id:$upoint_id");
            return 'unauthorized';
        }
        return (object)[
            'order_id'=>$order_id,
            'new'=>'NEW',
            'payed'=>'PAID',
            'canceled'=>'CANCELED'
        ];
    }


    public function confirm($billNumber,$sum){
        $request=[
            'Billnumber'=>$billNumber,
            'Subtotal_P'=>$sum,
            'Shop_ID'=>getenv('uniteller.Shop_IDP'),
            'Login'=>getenv('uniteller.login'),
            'Password'=>getenv('uniteller.password'),
            'Format'=>'1',
            'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('uniteller.gateway').'confirm/', false, $context);
        if(!$result || str_contains($result,'ErrorCode')){
            log_message('error','Uniteller confirm error billNumber:'.$billNumber.$result);
            return null;
        }
        $response=explode(';',$result);
        return (object)[
            'order_id'=>$response[0],
            'status'=>$response[1],
            'total'=>$response[2],
            'approvalCode'=>$response[3],
            'billNumber'=>$response[4]
        ];
    }

    public function refund($billNumber,$sum){
        $request=[
            'BillNumber'=>$billNumber,
            'Subtotal_P'=>$sum,
            'Shop_ID'=>getenv('uniteller.Shop_IDP'),
            'Login'=>getenv('uniteller.login'),
            'Password'=>getenv('uniteller.password'),
            'Format'=>'1',
            'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('uniteller.gateway').'unblock/', false, $context);
        if(!$result){
            return null;
        }
        $response=explode(';',$result);
        return (object)[
            'order_id'=>$response[0],
            'status'=>$response[1],
            'total'=>$response[2],
            'approvalCode'=>$response[3],
            'billNumber'=>$response[4]
        ];
    }
}