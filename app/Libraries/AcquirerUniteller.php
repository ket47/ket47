<?php
namespace App\Libraries;
class AcquirerUniteller{
    public function linkGet($order_all){
        $p=(object)[
            'Shop_IDP' => getenv('uniteller.Shop_IDP'),
            'Order_IDP' => getenv('uniteller.orderPreffix').$order_all->order_id,
            'Subtotal_P' => $order_all->order_sum_total,
            'Customer_IDP' => $order_all->customer->user_id,
            'Email' => $order_all->customer?->user_email??"user{$order_all->customer->user_id}@tezkel.com",
            'Phone' => $order_all->customer?->user_phone,
            'PhoneVerified' => $order_all->customer->user_phone,
            //'FirstName'=>$order_all->customer->user_name,
            'URL_RETURN_OK'=>getenv('app.baseURL').'CardAcquirer/pageOk',
            'URL_RETURN_NO'=>getenv('app.baseURL').'CardAcquirer/pageNo',
            'Preauth'=>1,
            'IsRecurrentStart'=>1,
            'Lifetime' => 5*60,// 5 min
            //'OrderLifetime' => 5*60,// 5 min
            'CallbackFields'=>'Total Balance ApprovalCode BillNumber',
            //'MeanType' => '','EMoneyType' => '','Card_IDP' => '','IData' => '','PT_Code' => '',
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

    public function cardRegisterLinkGet($user_id){
        $UserCardModel=model('UserCardModel');
        $card_id=$UserCardModel->itemCreate();
        if( !$card_id || $card_id=='forbidden' ){
            return 'nocardid';
        }
        $UserModel=model('UserModel');
        $customer=$UserModel->itemGet($user_id,'basic');
        $p=(object)[
            'Shop_IDP' => getenv('uniteller.Shop_IDP'),
            'Order_IDP' => getenv('uniteller.orderPreffix').'REG'.$card_id,
            'Subtotal_P' => 11,
            'Customer_IDP' => $user_id,
            'Email' => $customer?->user_email??"user{$user_id}@tezkel.com",
            'Phone' => $customer?->user_phone,
            'PhoneVerified' => $customer->user_phone,
            'URL_RETURN_OK'=>getenv('app.baseURL').'CardAcquirer/pageOk',
            'URL_RETURN_NO'=>getenv('app.baseURL').'CardAcquirer/pageNo',
            'Preauth'=>1,
            'IsRecurrentStart'=>1,
            'Card_Registration'=>1,
            'Lifetime' => 5*60
        ];
        $p->Signature = strtoupper(
            md5(
                md5($p->Shop_IDP) . "&" .
                md5($p->Order_IDP) . "&" .
                md5($p->Lifetime??'') . "&" .
                md5($p->Customer_IDP) . "&" .
                md5( getenv('uniteller.password') )
            )
        );
        $queryString = http_build_query($p);
        return getenv('uniteller.gateway').'pay?'.$queryString;
    }

    public function cardRegisterActivate(){
        $UserCardModel=model('UserCardModel');
        $disabledCard=$UserCardModel->itemDisabledGet();
        if( !($disabledCard->card_id??0) ){
            return 'nodisabledcard';
        }
        $request=[
            'Shop_ID'=>getenv('uniteller.Shop_IDP'),
            'Login'=>getenv('uniteller.login'),
            'Password'=>getenv('uniteller.password'),
            'Format'=>'1',
            'ShopOrderNumber'=>getenv('uniteller.orderPreffix').'REG'.$disabledCard->card_id,
            'S_FIELDS'=>'OrderNumber;Status;Total;BillNumber;Card_IDP;CardType;CardNumber'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('uniteller.gateway').'results/', true, $context);
        if(!$result){
            return 'fail';
        }
        $rows=str_getcsv($result,"\n");
        $response=str_getcsv($rows[0],";");
        if(!$result || str_contains($result,'ErrorCode') || !$response){
            log_message('error','RESPONSE cardActivation UNITELLER REQUEST:'.json_encode($request).' RESPONSE:'.$result);
            return 'fail';
        }
        $registrationData=(object)[
            'status'=>$response[1],
            'total'=>$response[2],
            'billNumber'=>$response[3],
            'card_id'=>$disabledCard->card_id,
            'card_remote_id'=>$response[4],
            'card_type'=>$response[5],
            'card_mask'=>$response[6],
            'card_acquirer'=>'uniteller'
        ];
        //if($registrationData->card_remote_id){
            $this->refund($registrationData->billNumber,$registrationData->total);
            //$this->confirm($registrationData->billNumber,$registrationData->total);
        //}
        $UserCardModel=model("UserCardModel");
        return $UserCardModel->itemUpdate($registrationData);
    }

    public function statusGet($order_id){
        $request=[
            'Shop_ID'=>getenv('uniteller.Shop_IDP'),
            'Login'=>getenv('uniteller.login'),
            'Password'=>getenv('uniteller.password'),
            'Format'=>'1',
            'ShopOrderNumber'=>getenv('uniteller.orderPreffix').$order_id,
            'S_FIELDS'=>'OrderNumber;Status;Total;BillNumber;CardType;CardNumber;need_confirm;'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('uniteller.gateway').'results/', true, $context);
        //pl([getenv('uniteller.gateway').'results/'.http_build_query($request),$request,$result],false);
        error_log("\n\n#$order_id ".date(" H:i:s")."\n".json_encode([getenv('uniteller.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3,  WRITEPATH."uniteller-".date('Y-m-d').".log");
        if(!$result){
            return null;
        }
        $rows=str_getcsv($result,"\n");
        $response=str_getcsv($rows[0],";");
        $order_id=str_replace(getenv('uniteller.orderPreffix'),'',$response[0]);
        return (object)[
            'order_id'=>$order_id,
            'status'=>$response[1],
            'total'=>$response[2],
            'billNumber'=>$response[3],
            'cardType'=>$response[4],
            'cardNumber'=>$response[5],
            'needConfirm'=>$response[6],
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

    // public function orderStatusReport($request){
    //     $order_id=$request->getVar('order_id');
    //     $upoint_id=$request->getVar('upoint_id');
    //     if( $upoint_id!=getenv('uniteller.Shop_IDP') ){
    //         log_message('error', "paymentStatusCheck; order_id:$order_id Shop_IDP DO NOT MATCH upoint_id:$upoint_id");
    //         return 'unauthorized';
    //     }
    //     return (object)[
    //         'order_id'=>$order_id,
    //         'new'=>'NEW',
    //         'payed'=>'PAID',
    //         'canceled'=>'CANCELED'
    //     ];
    // }

    public function pay( object $order_all, int $card_id){
        $request=(object)[
            'Shop_IDP'=>getenv('uniteller.Shop_IDP'),
            'Order_IDP'=>getenv('uniteller.orderPreffix').$order_all->order_id,
            'Subtotal_P'=>number_format($order_all->order_sum_total,2,'.',''),
            'Parent_Order_IDP'=>getenv('uniteller.orderPreffix').'REG'.$card_id,
//            'Parent_Order_IDP'=>'loc_1077',
        ];
        $request->Signature = strtoupper(
            md5(
                md5($request->Shop_IDP) . "&" .
                md5($request->Order_IDP) . "&" .
                md5($request->Subtotal_P) . "&" .
                md5($request->Parent_Order_IDP) . "&" .
                md5( getenv('uniteller.password') )
            )
        );
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('uniteller.gateway').'recurrent/', false, $context);
        $rows=str_getcsv($result,"\n");
        $response=str_getcsv($rows[1],";");
        error_log("\n\n#{$order_all->order_id}".date(" H:i:s")."\n".json_encode([getenv('uniteller.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3,  WRITEPATH."uniteller-".date('Y-m-d').".log");
        if(!$result || str_contains($result,'Error_Code') || !$response){
            log_message('error','RESPONSE pay UNITELLER REQUEST:'.json_encode($request).' RESPONSE:'.$result);
            return null;
        }
        return (object)[
            'order_id'=>$response[0],
            'status'=>$response[1],
            'total'=>$response[2],
            'approvalCode'=>$response[3],
            'billNumber'=>$response[4]
        ];
    }


    public function confirm($billNumber,$sum){
        $request=[
            'Billnumber'=>$billNumber,
            'Subtotal_P'=>(float)$sum,
            'Shop_ID'=>getenv('uniteller.Shop_IDP'),
            'Login'=>getenv('uniteller.login'),
            'Password'=>getenv('uniteller.password'),
            'Format'=>'1',
            'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber;'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('uniteller.gateway').'confirm/', false, $context);
        //pl([getenv('uniteller.gateway').'confirm/'.http_build_query($request),$request,$result],false);
        $rows=str_getcsv($result,"\n");
        $response=str_getcsv($rows[1],";");

        error_log("\n\nBN#{$billNumber}".date(" H:i:s")."\n".json_encode([getenv('uniteller.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3,  WRITEPATH."uniteller-".date('Y-m-d').".log");


        if(!$result || str_contains($result,'ErrorCode') || !$response){
            log_message('error','RESPONSE confirm UNITELLER REQUEST:'.json_encode($request).' RESPONSE:'.$result);
            return null;
        }
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
            'Billnumber'=>$billNumber,
            'Subtotal_P'=>(float)$sum,
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
        $rows=str_getcsv($result,"\n");
        $response=str_getcsv($rows[1],";");
        error_log("\n\nBN#{$billNumber} ".date(" H:i:s")."\n".json_encode([getenv('uniteller.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3, WRITEPATH."uniteller-".date('Y-m-d').".log");
        if(!$result || str_contains($result,'ErrorCode') || !$response){
            log_message('error','RESPONSE refund UNITELLER REQUEST:'.json_encode($request).' RESPONSE:'.$result);
            return null;
        }
        return (object)[
            'order_id'=>$response[0],
            'status'=>$response[1],
            'total'=>$response[2],
            'approvalCode'=>$response[3],
            'billNumber'=>$response[4]
        ];
    }

    // public function cardListGet( int $user_id ){
    //     $request=[
    //         'Shop_IDP'=>getenv('uniteller.Shop_IDP'),
    //         'Login'=>getenv('uniteller.login'),
    //         'Password'=>getenv('uniteller.password'),
    //         'Format'=>1,
    //         'Customer_IDP'=>$user_id,
    //         'CardStatus'=>1
    //     ];
    //     $context  = stream_context_create([
    //         'http' => [
    //             'header'  => "Content-type: application/x-www-form-urlencoded",
    //             'method'  => 'POST',
    //             'content' => http_build_query($request)
    //             ]
    //     ]);
    //     $result = file_get_contents(getenv('uniteller.gateway').'cardv4/', false, $context);
    //     return $result;
    // }
}