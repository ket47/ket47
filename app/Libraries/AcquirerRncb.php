<?php
namespace App\Libraries;

class AcquirerRncb{
    public function apiExecute( string $function, array $request=null, string $method='POST' ){
        $url = getenv('rncb.gateway')."/$function";
        $auth=base64_encode(getenv('rncb.login').':'.getenv('rncb.password'));
        $headers=["Authorization: Basic {$auth}"];
        $curl = curl_init(); 
        switch( $method ){
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if( $request ){
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
                    $headers[]="Content-Type: application/json";
                }
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if( $request ){
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
                    $headers[]="Content-Type: application/json";
                }
                break;
            case 'GET':
                if( $request ){
                    $query=http_build_query($request);
                    $url .= "?$query";
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
        //pl(curl_getinfo($curl));
        if( curl_error($curl) ){
            log_message("ERROR","$url API Execute error: ".curl_error($curl));
            die(curl_error($curl));
        }
        curl_close($curl);
        return json_decode($result);
    }

    public function linkGet( object $order_all ){
        $orderTitle="Заказ #{$order_all->order_id}";
        $orderDescription="Оплата доставки tezkel.com";

        $OrderModel=model('OrderModel');
        $orderData=$OrderModel->itemDataGet($order_all->order_id);
        /**
         * If link was created within timeout then reuse it.
         * Otherwise create new order on acq
         */
        if( ($orderData->await_payment_until??0)<time() ){
            $request=[
                'order'=>[
                    //"ridByMerchant"=>$order_all->order_id,
                    "title"=>$orderTitle,
                    "description"=>$orderDescription,
                    "hppRedirectUrl"=>getenv('app.baseURL').'CardAcquirer/pageOk',
                    "typeRid"=>"Sale Order Type Preauth_CVV",
                    "amount"=>$order_all->order_sum_total,
                    "currency"=>"RUB",
                    "consumer"=>[
                        "ridByOwner"=>$order_all->customer->user_id,
                        "name"=>$order_all->customer->user_name
                    ],
                    "srcPersonName"=>$order_all->customer?->user_name,
                    "srcEmail"=>$order_all->customer?->user_email??"user{$order_all->customer->user_id}@tezkel.com",
                    "srcMobile" => $order_all->customer?->user_phone,
                ]
            ];
            $response=$this->apiExecute('order',$request);
            if($response->errorCode??null){
                pl(['Acquirer:linkGet',$order_all->order_id,$response]);
            }
            $orderData=(object)[
                "payment_card_acq_rncb"=>1,
                "payment_card_acq_order_id"=>$response->order->id,
                "payment_card_acq_url"=>"{$response->order->hppUrl}?at={$response->order->accessToken}"
                //"payment_card_fixate_id"=>$response->order->id,//notpayed yet
            ];
            $OrderModel->fieldUpdateAllow('order_data');
            $OrderModel->itemDataUpdate($order_all->order_id,$orderData);
        }
        return $orderData->payment_card_acq_url;
    }

    /**
     * Creates link for adding reccurent payment card
     */
    public function cardRegisteredLinkGet( $user_id ){
        // $UserCardModel=model('UserCardModel');
        // $card_id=$UserCardModel->itemCreate();
        // if( !$card_id || $card_id=='forbidden' ){
        //     return 'nocardid';
        // }
        $UserModel=model('UserModel');
        $customer=$UserModel->itemGet($user_id,'basic');

        $orderTitle="Привязка карты";
        $orderDescription="Служба доставки tezkel.com";
        $request=[
            'order'=>[
                "title"=>$orderTitle,
                "description"=>$orderDescription,
                "hppRedirectUrl"=>getenv('app.baseURL').'CardAcquirer/pageOk',
                "typeRid"=>"CheckTokenViaPurchase",
                "amount"=>1,
                "currency"=>"RUB",
                "aut"=>[
                    "purpose"=>"AddCard"
                ],
                "hppCofCapturePurposes"=>[
                    "Cit",
                    "Instalment",
                    "Recurring",
                    "PartialShipment",
                    "UnspecifiedMit",
                    "DelayedCharge"
                ],
                "consumer"=>[
                    "ridByOwner"=>$customer->user_id,
                    "name"=>$customer->user_name
                ],
                "srcPersonName"=>$customer?->user_name,
                "srcEmail"=>$customer?->user_email??"user{$customer->user_id}@tezkel.com",
                "srcMobile" => $customer?->user_phone,
            ]
        ];
        $response=$this->apiExecute('order',$request);
        
        if( $response->errorCode??null ){
            pl(['Acquirer:cardRegisteredLinkGet',$response]);
            return null;
        }
        return "{$response->order->hppUrl}?at={$response->order->accessToken}";
    }

    public function cardRegisteredSync( $user_id ){
        $function="consumer/{$user_id}?ownerKind=Merchant&tokenDetailLevel=1";
        $response=$this->apiExecute($function,null,'GET');
        
        if($response->errorCode??null){
            pl(['Acquirer:cardRegisteredSync',$response]);
            return null;
        }
        $UserCardModel=model('UserCardModel');
        $UserCardModel->listDelete($user_id);
        
        if( !($response->consumer->tokens??null) ){
            return 'ok';
        }
        $last_card_id=null;
        foreach($response->consumer->tokens as $token){
            $cof=(object)[
                'card_type'=>$token->card->brand??'-',
                'card_mask'=>$token->displayName??'-',
                'card_acquirer'=>'rncbCard',
                'card_remote_id'=>$token->id,
                'is_disabled'=>0
            ];
            $last_card_id=$UserCardModel->itemCreate($cof);
        }
        return $UserCardModel->itemUpdate((object)[
            'card_id'=>$last_card_id,
            'is_main'=>1
        ]);
    }

    public function cardRegisteredRemove( $user_id, $card_remote_id ){
        $function="consumer/{$user_id}?ownerKind=Merchant&tokenDetailLevel=1";
        $consumerCards=$this->apiExecute($function,null,'GET');
        $cardIsAbsent=1;
        foreach($consumerCards->consumer->tokens as $token){
            if( $token->id==$card_remote_id ){
                $token->status="Closed";
                $cardIsAbsent=0;
            }
        }
        if($cardIsAbsent){
            return 'notfound';
        }
        $response=$this->apiExecute('consumer',(array)$consumerCards,'PUT');
        pl(['Acquirer:cardRegisteredRemove',$consumerCards,$response]);
        return 'ok';
    }

    /**
     * Gets previously created payment order status
     */
    public function statusGet( int $order_id ){
        $OrderModel=model('OrderModel');
        $orderData=$OrderModel->itemDataGet($order_id);

        if( !($orderData->payment_card_fixate_id??null) ){
            return null;
        }
        $function="order/{$orderData->payment_card_fixate_id}";
        $response=$this->apiExecute($function,['orderDetailLevel'=>1],'GET');
        if($response->errorCode??null){
            pl(['Acquirer:statusGet',$response]);
            return null;
        }
        //pl(['Acquirer:statusGet',$response]);
        $statusDict=[
            'Preparing'=>'not authorized',
            'Authorized'=>'authorized',
            'PartPaid'=>'paid',
            'FullyPaid'=>'paid',
            'Rejected'=>'not authorized',
            'Expired'=>'Expired',
            'Closed'=>'Closed',
        ];

        $needConfirm=in_array($response->order->status,['Authorized'])?1:0;
        return (object)[
            'order_id'=>$order_id,
            'status'=>$statusDict[$response->order->status],
            'response'=>$response->order,
            'total'=>$response->order->amount,
            'billNumber'=>$response->order->id,
            'needConfirm'=>$needConfirm,
        ];
    }

    /**
     * Parses incoming webhook from bank
     */
    public function statusParse(){
        //
    }



    public function pay( object $order_all ){
        $OrderModel=model('OrderModel');
        $UserCardModel=model('UserCardModel');
        $orderData=$OrderModel->itemDataGet($order_all->order_id);

        $orderTitle="Заказ #{$order_all->order_id}";
        $orderDescription="Служба доставки tezkel.com ".($order_all->store->store_name??null);
        $CoF=$UserCardModel->where('card_acquirer','rncbCard')->itemMainGet($order_all->customer->user_id);
        if( !($CoF->card_remote_id??null) ){
            return "error_nocof";
        }
        if( !($orderData->payment_card_fixate_id??null) ){
            //Creating new cof order
            $request=[
                'order'=>[
                    //"ridByMerchant"=>$order_all->order_id,
                    "title"=>$orderTitle,
                    "description"=>$orderDescription,
                    "typeRid"=>"Sale Order Type Preauth",
                    "amount"=>$order_all->order_sum_total,
                    "currency"=>"RUB",
                    "srcPersonName"=>$order_all->customer?->user_name,
                    "srcEmail"=>$order_all->customer?->user_email??"user{$order_all->customer->user_id}@tezkel.com",
                    "srcMobile" => $order_all->customer?->user_phone,
                    "aut"=>[
                        "purpose"=>"Recurring"
                    ],
                ],
                "srcToken"=>[
                    "storedId"=>$CoF->card_remote_id
                ],
            ];
            $acqOrder=$this->apiExecute('order',$request);
            if($acqOrder->errorCode??null){
                pl(['Acquirer:pay orderCreate',$order_all->order_id,$acqOrder]);
                if( $acqOrder->errorCode=='PmoDecline' ){
                    return $this->getSlugByDescription( $acqOrder->errorDescription );
                }
                return 'error';
            }
            $orderData=(object)[
                "payment_card_acq_rncb"=>1,
                "payment_card_acq_order_id"=>$acqOrder->order->id,
                "payment_card_fixate_iscof"=>1,
            ];
        }

        //Auth money on card
        $request=[
            'tran'=>[
                "phase"=>"Auth",
                "amount"=>$order_all->order_sum_total,
                "conditions"=>[
                    "cofUsage"=>"Recurring"
                ],
            ],
        ];
        $function="order/{$orderData->payment_card_acq_order_id}/exec-tran";
        $response=$this->apiExecute($function,$request);
        if($response->errorCode??null){
            pl(['Acquirer:pay Auth',$function,$request,$response]);
            if( $response->errorCode=='PmoDecline' ){
                return $this->getSlugByDescription( $response->errorDescription );
            }
            return 'error';
        }
        $orderData->payment_card_fixate_id=$orderData->payment_card_acq_order_id;
        $orderData->payment_card_fixate_sum=$order_all->order_sum_total;
        
        $OrderModel->fieldUpdateAllow('order_data');
        $OrderModel->itemDataUpdate($order_all->order_id,$orderData);
        return 'ok';
    }

    private function getSlugByDescription( $error_description ){
        $description_parts=explode(' ',$error_description);
        $error_code=array_pop($description_parts);
        $error_card=[14,15,19,31,33,56,113];
        if( in_array($error_code,$error_card) ){
            return 'error_card';
        }
        $error_fund=[51,52,53,57,58,61,64,65,125,180,181,182,185];
        if( in_array($error_code,$error_fund) ){
            return 'error_fund';
        }
        $error_fraud=[4,7,22,34,35,36,37,38,41,43,59,63,66,67,70,117];
        if( in_array($error_code,$error_fraud) ){
            return 'error_fraud';
        }
        return 'error';
    }




    /**
     * Confirmes previously preauthorized bill/order
     */
    public function confirm( int $docId, float $sum ){
        if( !$sum ){
            /**
             * For this acquirer full refunds are not necessarry
             */
            return (object)[
                'billNumber'=>'not_aplicable'
            ];
        }
        $request=[
            "tran"=>[
                "phase"=>"Clearing",
                "amount"=>$sum
            ]
        ];
        $function="order/{$docId}/exec-tran";
        $response=$this->apiExecute($function,$request);
        if($response->errorCode??null){
            pl(['Acquirer:confirm',$docId,$response]);
            return null;
        }

        return (object)[
            'billNumber'=>$response->tran->match->tranActionId??null
        ];
    }

    /**
     * Refunds Full Sum previously preauthorized bill/order
     */
    public function refund( int $docId, float $sum, bool $isFull=false ){
        if( !$isFull ){
            /**
             * For this acquirer partial refunds are not necessarry
             */
            return (object)[
                'billNumber'=>'not_aplicable'
            ];
        }
        $request=[
            "tran"=>[
                "phase"=>"Auth",
                "voidKind"=>"Full",
                "amount"=>$sum
            ]
        ];
        $function="order/{$docId}/exec-tran";
        $response=$this->apiExecute($function,$request);
        if( ($response->errorCode??null) ){
            pl(['Error Acquirer:refund',$docId,$response]);
            return null;
        }

        return (object)[
            'billNumber'=>$response->tran->match->tranActionId??null
        ];
    }

}