<?php
namespace App\Libraries;
class AcquirerUnitellerSBP{

    public function linkGet($order_all,$params=null){
        $params=[
            'PaymentTypeLimits'=>"{\"13\":[{$order_all->order_sum_total},{$order_all->order_sum_total}]}"
        ];
        $p=(object)[
            'URL_RETURN_OK'=>getenv('app.baseURL').'CardAcquirer/pageOk',
            'URL_RETURN_NO'=>getenv('app.baseURL').'CardAcquirer/pageNo',
            'Email' => $order_all->customer?->user_email??"user{$order_all->customer->user_id}@tezkel.com",
            'Phone' => $order_all->customer?->user_phone,
            //'IsRecurrentStart'=>1,
            //'Registration'=>1,
            'CallbackFields'=>'Total Balance ApprovalCode BillNumber',
            'FirstName'=>$order_all->customer->user_name,
            //'OrderLifetime' => 5*60,// 5 min
            //'MeanType' => '',
            //'EMoneyType' => '',
            //'Card_IDP' => '',
            //'IData' => '',
            //'PT_Code' => '',

            'Shop_IDP' => getenv('unitellerSBP.Shop_IDP'),
            'Order_IDP' => getenv('unitellerSBP.orderPreffix').$order_all->order_id,
            'Subtotal_P' => $order_all->order_sum_total,
            'Lifetime' => 10*60,// 10 min
            'Customer_IDP' => $order_all->customer->user_id,
            'PhoneVerified' => $order_all->customer->user_phone,
        ];
        $sign_body=
                     md5($p->Shop_IDP)
                ."&".md5($p->Order_IDP)
                ."&".md5($p->Subtotal_P)
                ."&".md5($p->MeanType??'')
                ."&".md5($p->EMoneyType??'')
                ."&".md5($p->Lifetime??'')
                ."&".md5($p->Customer_IDP)
                ."&".md5($p->Card_IDP??'')
                ."&".md5($p->IData??'')
                ."&".md5($p->PT_Code??'')
                ."&".md5($p->PhoneVerified??'');
        if($params['PaymentTypeLimits']??0){
            $p->PaymentTypeLimits=$params['PaymentTypeLimits'];
            $sign_body.="&".md5($p->PaymentTypeLimits??'');
        }
        $p->Signature = strtoupper(md5(
            $sign_body."&".md5( getenv('unitellerSBP.password') )
        ));
        //pl($p);
        $queryString = http_build_query($p);
        return getenv('unitellerSBP.gateway').'pay?'.$queryString;
    }


    public function statusGet($order_id){
        $request=[
            'Shop_ID'=>getenv('unitellerSBP.Shop_IDP'),
            'Login'=>getenv('unitellerSBP.login'),
            'Password'=>getenv('unitellerSBP.password'),
            'Format'=>'1',
            'ShopOrderNumber'=>getenv('unitellerSBP.orderPreffix').$order_id,
            'S_FIELDS'=>'OrderNumber;Status;Total;BillNumber;CardType;CardNumber;need_confirm;'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('unitellerSBP.gateway').'results/', true, $context);
        pl([getenv('unitellerSBP.gateway').'results/'.http_build_query($request),$request,$result],false);
        //error_log("\n\n#$order_id ".date(" H:i:s")."\n".json_encode([getenv('unitellerSBP.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3,  WRITEPATH."uniteller-".date('Y-m-d').".log");
        if(!$result){
            return null;
        }
        $rows=str_getcsv($result,"\n");
        $response=str_getcsv($rows[0],";");
        $order_id=str_replace(getenv('unitellerSBP.orderPreffix'),'',$response[0]);
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

    /**
     * Gets status of payment and if payed applies to order
     */
    public function statusCheck( int $order_id ){
        $payment_data=$this->statusGet( $order_id );
        if( 'authorized'==$payment_data?->status ){
            $OrderModel=model('OrderModel');
            return $OrderModel->itemStageAdd( $order_id, 'customer_payed_card', $payment_data, false );
        }
        return 'order_not_payed';
    }

    public function statusParse($request){
        $order_id=$request->getVar('Order_ID');
        $status=$request->getVar('Status');
        $signature=$request->getVar('Signature');
        $total=$request->getVar('Total');
        $balance=$request->getVar('Balance');
        $approvalCode=$request->getVar('ApprovalCode');
        $billNumber=$request->getVar('BillNumber');
        //Total Balance ApprovalCode BillNumber
        $signature_check = strtoupper(md5($order_id.$status.$total.$balance.$approvalCode.$billNumber.getenv('unitellerSBP.password')));
        if($signature!=$signature_check){
            log_message('error', "paymentStatusSet $status; order_id:$order_id SIGNATURES NOT MATCH $signature!=$signature_check  $order_id.$status.$total.$balance.$approvalCode.$billNumber");
            return 'unauthorized';
        }
        /**
         * In webhook there is no billnumber. Should be removed
         */
        return (object)[
            'order_id'=>$order_id,
            'status'=>$status,
            'total'=>$total,
            'balance'=>$balance,
            'approvalCode'=>$approvalCode,
            'billNumber'=>$billNumber??'unset'
        ];
    }

    public function confirm($billNumber,$sum){
        return true;
    }

    public function refund($order_id,$sum){
        $request=(object)[
            'OrderID'=>getenv('unitellerSBP.orderPreffix').$order_id,
            'Shop_ID'=>getenv('unitellerSBP.Shop_IDP'),
            'Subtotal_P'=>number_format($sum,2,'.',''),
            'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        ];
        $sign_body=
             hash('sha256',$request->OrderID)
        ."&".hash('sha256',$request->Shop_ID)
        ."&".hash('sha256',$request->Subtotal_P)
        ."&".hash('sha256',$request->S_FIELDS)
        ."&".hash('sha256', getenv('unitellerSBP.password') );
        $request->Signature = strtoupper(hash('sha256',$sign_body));
                $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('unitellerSBP.gateway').'unblock/', false, $context);
        $rows=str_getcsv($result,"\n");
        $response=str_getcsv($rows[1],";");
        //error_log("\n\nBN#{$billNumber} ".date(" H:i:s")."\n".json_encode([getenv('unitellerSBP.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3, WRITEPATH."uniteller-".date('Y-m-d').".log");
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




















    


































    // private function apiSend( string $function, object $request, ?array $params=null ){
    //     $queryString=$this->apiQueryGet($request,$params);
    //     return $this->apiExecute($function,$queryString);
    // }

    // private function apiQueryGet(object $request, ?array $params){
    //     $sign_body=
    //                  md5($request->Shop_IDP)
    //             ."&".md5($request->Order_IDP)
    //             ."&".md5($request->Subtotal_P)
    //             ."&".md5($request->MeanType??'')
    //             ."&".md5($request->EMoneyType??'')
    //             ."&".md5($request->Lifetime??'')
    //             ."&".md5($request->Customer_IDP??'')
    //             ."&".md5($request->Card_IDP??'')
    //             ."&".md5($request->IData??'')
    //             ."&".md5($request->PT_Code??'')
    //             ."&".md5($request->PhoneVerified??'');
    //     if($params['PaymentTypeLimits']??0){
    //         $request->PaymentTypeLimits=$params['PaymentTypeLimits'];
    //         $sign_body.="&".md5($p->PaymentTypeLimits??'');
    //     }
    //     $request->Signature = strtoupper(md5(
    //         $sign_body."&".md5( getenv('unitellerSBP.password') )
    //     ));
    //     return http_build_query($request);
    // }
    // private function apiSbpExecute( string $function, object $request ){
    //     $url = "https://api.uniteller.ru/sbp/1/$function/";
    //     $queryString = http_build_query($request);   
    //     $context  = stream_context_create([
    //         'http' => [
    //             'header'  => "Content-type: application/x-www-form-urlencoded",
    //             'method'  => 'POST',
    //             'content' => $queryString
    //             ]
    //     ]);
    //     $result = file_get_contents($url, false, $context);



    //     pl($request,$result);
    //     $response=json_decode($result);
    //     return $response;
    // }


    private function gatewayExecute( string $function, string $queryString, bool $returnList=false ){
        if(getenv('test.acquirerMock')){
            $url = getenv('unitellerSBP.gateway')."$function/";
        } else {
            $url = getenv('unitellerMock.gateway')."$function/";         
        }
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => $queryString
                ]
        ]);
        $result = file_get_contents($url, false, $context);



        pl($queryString,$result);
        $rows=str_getcsv($result,"\n");
        $header_row=array_shift($rows);
        $header=str_getcsv($header_row,";");

        if( isset($header['ErrorCode']) || !$returnList ){
            return array_combine($header,str_getcsv($rows[0],";"));
        }
        $response=[];
        foreach($rows as $row){
            $response[]=array_combine($header,str_getcsv($row,";"));
        }
        return $response;
    }



    public function qrGet($order_all,$params=null){
        $request=(object)[
            //'Registration'=>1,
            'ShopID' => getenv('unitellerSBP.Shop_IDP'),
            'OrderID' => getenv('unitellerSBP.orderPreffix').$order_all->order_id,
            'CustomerID' => $order_all->customer->user_id,
            'Subtotal' => $order_all->order_sum_total,

            'OrderLifetime' => 100*60,// 10 min
            'Email' => $order_all->customer?->user_email??"user{$order_all->customer->user_id}@tezkel.com",
            'IsRecurrentStart'=>1,
            'DeepLink' =>  getenv('app.frontendUrl')."order/order-".$order_all->order_id,
            //'BackUrl'=>getenv('app.baseURL').'CardAcquirer/pageOk',
            //"CallbackFormat"=>"json"
        ];
        $response=$this->apiExecute('getqr',$request);
        if( isset($response->ResultSBP->Payload) ){
            $qr=new \App\Libraries\QRCode($response->ResultSBP->Payload,['w'=>500,'h'=>500]);
            ob_start();
            $qr->output_image();
            $bin = ob_get_clean();
            $b64 = base64_encode($bin);

            return (object)[
                'link'=>$response->ResultSBP->Payload,
                'expired_at'=>$response->ResultSBP->StaleTime,
                'qr'=>"data:image/png;base64,$b64"
            ];
        }
        pl($request,$response);
        return null;
    }


    private function apiExecute( string $function, object $request, string $method='POST' ){
        $url = "https://api.uniteller.ru/sbp/1/$function/";
        $sign_body='';
        foreach($request as $field=>$val){
            $sign_body.=md5($val);
        }
        $request->Signature = strtoupper(md5(
            $sign_body.md5( getenv('unitellerSBP.password') )
        ));
        $curl = curl_init(); 
        switch( $method ){
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if( $request ){
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($request));
                    $headers[]="Content-type: application/x-www-form-urlencoded";
                }
                break;
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($curl);

        p($request,0);
        p($result,0);

        //pl(curl_getinfo($curl));
        if( curl_error($curl) ){
            log_message("error","$url API Execute error: ".curl_error($curl));
            die(curl_error($curl));
        }
        curl_close($curl);
        return simplexml_load_string($result);
    }



    // public function orderStatusReport($request){
    //     $order_id=$request->getVar('order_id');
    //     $upoint_id=$request->getVar('upoint_id');
    //     if( $upoint_id!=getenv('unitellerSBP.Shop_IDP') ){
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


    public function pay( object $order_all, ?int $paying_user_id=null ){
        return false;
    }


    public function recurrentPay( string $card_remote_id, object $order_all, ?int $paying_user_id=null ){
        $OrderModel=model('OrderModel');
        // $orderData=$OrderModel->itemDataGet($order_all->order_id);

        // $is_allowed=$orderData->payment_by_card??0;
        // if( !$is_allowed && in_array($order_all->user_role,['delivery','admin']) ){
        //     $is_allowed=1;//payment_by_cash (courier paying in place of customer)
        // }
        // if( !$is_allowed ){
        //     return 'forbidden';
        // }
        $orderDataUpdate=(object)[];

        if( !$paying_user_id ){
            $paying_user_id=$order_all->customer->user_id;
        }

        $request=(object)[
            'Shop_IDP'=>getenv('unitellerSBP.Shop_IDP'),
            'Order_IDP'=>getenv('unitellerSBP.orderPreffix').$order_all->order_id,
            'Subtotal_P'=>number_format($order_all->order_sum_total,2,'.',''),
            'Parent_Order_IDP'=>$card_remote_id,
        ];




        $request->Subtotal_P='1.00';
        $request->Parent_Order_IDP='loc_order14';


        $sign_body=
                md5($request->Shop_IDP)
        ."&".md5($request->Order_IDP)
        ."&".md5($request->Subtotal_P)
        ."&".md5($request->Parent_Order_IDP)
        ."&".md5( getenv('unitellerSBP.password') );
        $request->Signature = strtoupper(md5($sign_body));
        $queryString=http_build_query($request);
        $response=$this->apiExecute('recurrent', $queryString);

        if( !$response || isset($response['ErrorCode']) ){
            pl('UNITELLER SBP: PAY',$request,$response);
            return null;
        }




        pl($request,$response);
        return (object)[
            'order_id'=>$response['body'][0],
            'status'=>$response['body'][1],
            'total'=>$response['body'][2],
            'approvalCode'=>$response['body'][3],
            'billNumber'=>$response['body'][4]
        ];


        if($response->errorCode??null){
            pl(['Acquirer:pay Auth',$function,$request,$response]);
            if( $response->errorCode=='PmoDecline' ){
                //return $this->getSlugByDescription( $response->errorDescription );
            }
            return 'error';
        }
        // $orderDataUpdate->payment_card_fixate_id=$orderDataUpdate->payment_card_acq_order_id;
        // $orderDataUpdate->payment_card_fixate_sum=$order_all->order_sum_total;
        $orderDataUpdate->payment_by_card=1;
        
        $OrderModel->itemDataUpdate($order_all->order_id,$orderDataUpdate);
        $payment_data=(object)[
            'status'=>'authorized',
            'total'=>$order_all->order_sum_total,
            'billNumber'=>$orderDataUpdate->payment_card_acq_order_id,
        ];
        return $OrderModel->itemStageAdd( $order_all->order_id, 'customer_payed_card', $payment_data, false );
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
            'Shop_IDP' => getenv('unitellerSBP.Shop_IDP'),
            'Order_IDP' => getenv('unitellerSBP.orderPreffix').'REG'.$card_id,
            'Subtotal_P' => 0,
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
                md5( getenv('unitellerSBP.password') )
            )
        );
        $queryString = http_build_query($p);
        return getenv('unitellerSBP.gateway').'pay?'.$queryString;
    }

    public function cardRegisterActivate(){
        $UserCardModel=model('UserCardModel');
        $disabledCard=$UserCardModel->itemDisabledGet();
        if( !($disabledCard->card_id??0) ){
            return 'nodisabledcard';
        }
        $request=[
            'Shop_ID'=>getenv('unitellerSBP.Shop_IDP'),
            'Login'=>getenv('unitellerSBP.login'),
            'Password'=>getenv('unitellerSBP.password'),
            'Format'=>'1',
            'ShopOrderNumber'=>getenv('unitellerSBP.orderPreffix').'REG'.$disabledCard->card_id,
            'S_FIELDS'=>'OrderNumber;Status;Total;BillNumber;Card_IDP;CardType;CardNumber'
        ];
        $context  = stream_context_create([
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'method'  => 'POST',
                'content' => http_build_query($request)
                ]
        ]);
        $result = file_get_contents(getenv('unitellerSBP.gateway').'results/', true, $context);
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

    public function cardRegisteredSync( $user_id ){
        $request=(object)[
            'Shop_IDP'=>getenv('unitellerSBP.Shop_IDP'),
            'Login'=>getenv('unitellerSBP.login'),
            'Password'=>getenv('unitellerSBP.password'),
            'Customer_IDP'=>$user_id,
        ];
        $queryString=http_build_query($request);
        $response=$this->gatewayExecute('tokens', $queryString, true);

        if($response->ErrorCode??null){
            pl(['Acquirer:cardRegisteredSync',$response]);
            return null;
        }
        $UserCardModel=model('UserCardModel');
        $UserCardModel->listDelete($user_id);
        
        if( empty($response) ){
            return 'ok';
        }

        $last_card_id=null;
        foreach($response as $token){
            if( empty($token['Token_IDP']) ){
                continue;
            } 
            $cof=(object)[
                'card_type'=>$token['TokenType'],
                'card_mask'=>$token['BankName'],
                'card_acquirer'=>'AcquirerUnitellerSBP',
                'card_remote_id'=>$token['Token_IDP'],
                'is_disabled'=> ($token['TokenStatus']==1?0:1)
            ];
            $last_card_id=$UserCardModel->itemCreate($cof);
        }
        return $UserCardModel->itemUpdate((object)[
            'card_id'=>$last_card_id,
            'is_main'=>1
        ]);
    }






//     public function payold( object $order_all, ?int $card_id=null){
//         return false;
//         $request=(object)[
//             'Shop_IDP'=>getenv('unitellerSBP.Shop_IDP'),
//             'Order_IDP'=>getenv('unitellerSBP.orderPreffix').$order_all->order_id,
//             'Subtotal_P'=>number_format($order_all->order_sum_total,2,'.',''),
//             'Parent_Order_IDP'=>getenv('unitellerSBP.orderPreffix').'REG'.$card_id,
// //            'Parent_Order_IDP'=>'loc_1077',
//         ];
//         $request->Signature = strtoupper(
//             md5(
//                 md5($request->Shop_IDP) . "&" .
//                 md5($request->Order_IDP) . "&" .
//                 md5($request->Subtotal_P) . "&" .
//                 md5($request->Parent_Order_IDP) . "&" .
//                 md5( getenv('unitellerSBP.password') )
//             )
//         );
//         $context  = stream_context_create([
//             'http' => [
//                 'header'  => "Content-type: application/x-www-form-urlencoded",
//                 'method'  => 'POST',
//                 'content' => http_build_query($request)
//                 ]
//         ]);
//         $result = file_get_contents(getenv('unitellerSBP.gateway').'recurrent/', false, $context);
//         $rows=str_getcsv($result,"\n");
//         $response=str_getcsv($rows[1],";");
//         //error_log("\n\n#{$order_all->order_id}".date(" H:i:s")."\n".json_encode([getenv('unitellerSBP.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3,  WRITEPATH."uniteller-".date('Y-m-d').".log");
//         if(!$result || str_contains($result,'Error_Code') || !$response){
//             log_message('error','RESPONSE pay UNITELLER REQUEST:'.json_encode($request).' RESPONSE:'.$result);
//             return null;
//         }
//         return (object)[
//             'order_id'=>$response[0],
//             'status'=>$response[1],
//             'total'=>$response[2],
//             'approvalCode'=>$response[3],
//             'billNumber'=>$response[4]
//         ];
//     }



    public $refundPartialIsNeeded=true;

    // public function refundOld($billNumber,$sum,$order_id=null){
    //     $request=[
    //         'Billnumber'=>$billNumber,
    //         'Subtotal_P'=>(float)$sum,
    //         'Shop_ID'=>getenv('unitellerSBP.Shop_IDP'),
    //         'Login'=>getenv('unitellerSBP.login'),
    //         'Password'=>getenv('unitellerSBP.password'),
    //         'Format'=>'1',
    //         'S_FIELDS'=>'OrderNumber;Status;Total;ApprovalCode;BillNumber'
    //     ];
    //     $context  = stream_context_create([
    //         'http' => [
    //             'header'  => "Content-type: application/x-www-form-urlencoded",
    //             'method'  => 'POST',
    //             'content' => http_build_query($request)
    //             ]
    //     ]);
    //     $result = file_get_contents(getenv('unitellerSBP.gateway').'unblock/', false, $context);
    //     $rows=str_getcsv($result,"\n");
    //     $response=str_getcsv($rows[1],";");
    //     //error_log("\n\nBN#{$billNumber} ".date(" H:i:s")."\n".json_encode([getenv('unitellerSBP.gateway').'results/'.http_build_query($request),$request,$result],JSON_PRETTY_PRINT), 3, WRITEPATH."uniteller-".date('Y-m-d').".log");
    //     if(!$result || str_contains($result,'ErrorCode') || !$response){
    //         log_message('error','RESPONSE refund UNITELLER REQUEST:'.json_encode($request).' RESPONSE:'.$result);
    //         return null;
    //     }
    //     return (object)[
    //         'order_id'=>$response[0],
    //         'status'=>$response[1],
    //         'total'=>$response[2],
    //         'approvalCode'=>$response[3],
    //         'billNumber'=>$response[4]
    //     ];
    // }

    // public function cardListGet( int $user_id ){
    //     $request=[
    //         'Shop_IDP'=>getenv('unitellerSBP.Shop_IDP'),
    //         'Login'=>getenv('unitellerSBP.login'),
    //         'Password'=>getenv('unitellerSBP.password'),
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
    //     $result = file_get_contents(getenv('unitellerSBP.gateway').'cardv4/', false, $context);
    //     return $result;
    // }
}