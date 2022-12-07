<?php
namespace App\Models;

class OrderStageScript{
    public $stageMap=[
        ''=>[
            // 'customer_deleted'=>            ['Удалить','danger'],
            'customer_cart'=>               ['Создать'],
            ],
        // 'customer_deleted'=>[
        //     'customer_purged'=>             ['Удалить','danger'],
        //     'customer_cart'=>               ['Восстановить'],
        //     ],
        'customer_cart'=>[
            'customer_purged'=>             ['Удалить','danger'],
            'customer_confirmed'=>          [],
            'customer_action_confirm'=>     ['Продолжить','success'],
            ],
        'customer_purged'=>                 [],
        'customer_confirmed'=>[
            'customer_cart'=>               ['Изменить'],
            'customer_start'=>              [],
            'customer_action_checkout'=>    ['Продолжить','success'],
            ],
        'customer_start'=>[
            'supplier_start'=>              ['Начать подготовку'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger'],
            'customer_rejected'=>           ['Отменить заказ','danger']
            ],
        'customer_rejected'=>[
            'system_reckon'=>               []
        ],
        
        'customer_disputed'=>[
            'customer_finish'=>             ['Отказаться от спора','success'],
            'customer_action_take_photo'=>  ['Сфотографировать заказ'],
            'admin_supervise'=>             ['Решить спор','danger'],
            ],
        'customer_finish'=>[
            'system_reckon'=>               [],
            ],
        
        
        'supplier_rejected'=>[
            'system_reckon'=>               [],
            ],
        'supplier_reclaimed'=>[
            'admin_supervise'=>             ['Решить спор','danger'],
            ],
        'supplier_start'=>[
            'supplier_finish'=>             ['Завершить подготовку','success'],
            'supplier_corrected'=>          ['Изменить'],
            'delivery_no_courier'=>         [],
            ],
        'supplier_corrected'=>[
            'supplier_finish'=>             ['Завершить подготовку','success'],
            'supplier_action_add'=>         ['Добавить товар'],
            'delivery_no_courier'=>         [],
            ],
        'supplier_finish'=>[
            'supplier_action_take_photo'=>  ['Сфотографировать'],
            'supplier_corrected'=>          ['Изменить'],
            'delivery_start'=>              ['Начать доставку','success'],
            'delivery_no_courier'=>         [],
            'system_reckon'=>               []
            ],
        
        
        
        'delivery_start'=>[
            'delivery_finish'=>             ['Завершить доставку','success'],
            'delivery_action_take_photo'=>  ['Сфотографировать'],
            'delivery_action_call_customer'=>['Позвонить клиенту'],
            'delivery_action_rejected'=>    ['Отказаться от доставки','danger'],
            'delivery_rejected'=>           [],
            ],
        
        'delivery_rejected'=>[
            'supplier_reclaimed'=>          ['Принять возврат заказа'],
            'admin_supervise'=>             ['Решить спор','danger'],
            ],
        'delivery_no_courier'=>[
            'system_reckon'=>               []  
        ],
        'delivery_finish'=>[
            'customer_disputed'=>           [],
            'customer_finish'=>             ['Завершить заказ','success'],
            'customer_action_objection'=>   ['Открыть спор','light'],
            ],


        'admin_supervise'=>[
            'system_finish'=>               ['Проблема решена','success'],
            'admin_sanction_customer'=>     ['Оштрафовать клиента','danger'],
            'admin_sanction_supplier'=>     ['Оштрафовать продавца','danger'],
            'admin_sanction_delivery'=>     ['Оштрафовать курьера','danger'],
            ],
        'admin_sanction_customer'=>[
            'system_reckon'=>               [],
            ],
        'admin_sanction_supplier'=>[
            'system_reckon'=>               [],
            ],
        'admin_sanction_delivery'=>[
            'system_reckon'=>               [],
            ],


        'system_reckon'=>[
            'system_finish'=>               [],
            'admin_supervise'=>             ['Установить статус','danger'],
            ],
    ];

    
    ////////////////////////////////////////////////
    //ADMIN HANDLERS
    ////////////////////////////////////////////////
    public function onAdminSupervise( $order_id ){
        return 'ok';
    }
    public function onAdminSanctionCustomer( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>1.00
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    public function onAdminSanctionCourier( $order_id ){
        $order_data_update=(object)[
            'sanction_courier_fee'=>1.00
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    public function onAdminSanctionSupplier( $order_id ){
        $order_data_update=(object)[
            'sanction_supplier_fee'=>1.00
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    ////////////////////////////////////////////////
    //SYSTEM HANDLERS
    ////////////////////////////////////////////////
    public function onSystemReckon( $order_id, $data ){
        $finishing_task=[
            'task_name'=>"Order reckoning #$order_id",
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_finish',$data]]
                ]
        ];
        jobCreate($finishing_task);

        $finishing_task['task_next_start_time']=time()+2*60;//SECOND TRY AFTER 2 MIN
        jobCreate($finishing_task);
        return 'ok';
    }
    public function onSystemFinish( $order_id ){
        /**
         * we should pause db transaction so API cals can be atomized
         */
        $this->OrderModel->transComplete();
        $OrderTransactionModel=model('OrderTransactionModel');
        $result=$OrderTransactionModel->orderFinalize($order_id)?'ok':'fail';
        $this->OrderModel->transBegin();
        return $result;

    }
    //////////////////////////////////////////////////////////////////////////
    //CUSTOMER HANDLERS
    //////////////////////////////////////////////////////////////////////////
    public function onCustomerPurged($order_id){
        return $this->OrderModel->itemPurge($order_id);
    }

    public function onCustomerDeleted($order_id){
        return $this->OrderModel->itemDelete($order_id);
    }
    
    public function onCustomerCart($order_id){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        if($OrderGroupMemberModel->isMemberOf($order_id,'customer_confirmed')){
            $Acquirer=\Config\Services::acquirer();
            $incomingStatus=$Acquirer->statusGet($order_id);
            if( in_array(strtolower($incomingStatus?->status),['authorized','paid']) ){
                return 'already_payed';//already payed so refuse to reset to cart
            }
        }
        //$this->OrderModel->itemUnDelete($order_id); seems to be unnecessary
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'free');
        //$this->OrderModel->update($order_id,['order_sum_product'=>0]);in this case serious bug
        return 'ok';
    }
    
    public function onCustomerConfirmed( $order_id ){
        $order=$this->OrderModel->itemGet( $order_id, 'basic' );
        ////////////////////////////////////////////////
        //STOCK RESERVE SECTION
        ////////////////////////////////////////////////
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'reserved');
        $order_sum_product=$EntryModel->listSumGet($order_id);
        if( !($order_sum_product>0) ){
            return 'order_is_empty';
        }
        $this->OrderModel->itemUpdate((object)[
            'order_id'=>$order_id,
            'order_sum_product'=>$order_sum_product
        ]);
        ////////////////////////////////////////////////
        //LOCATION FIXATION SECTION
        ////////////////////////////////////////////////
        $LocationModel=model('LocationModel');
        try{
            $order_update=[
                'order_start_location_id'=>$LocationModel->itemMainGet('store',$order->order_store_id)->location_id,
                'order_finish_location_id'=>$LocationModel->itemMainGet('user',$order->owner_id)->location_id
            ];
            $this->OrderModel->update($order_id,$order_update);
        } catch (\Exception $e){
            return 'address_not_set';
        }
        ////////////////////////////////////////////////
        //RESET SECTION
        ////////////////////////////////////////////////
        $PrefModel=model('PrefModel');
        $timeout_min=$PrefModel->itemGet('customer_confirmed_timeout_min','pref_value',0);
        $next_start_time=time()+$timeout_min*60;
        $stage_reset_task=[
            'task_name'=>"customer_confirmed Rollback #$order_id",
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['customer_confirmed','customer_cart',$order_id]]
                ],
            'is_singlerun'=>1,
            'task_next_start_time'=>$next_start_time
        ];
        jobCreate($stage_reset_task);
        return 'ok';
    }
    
    public function onCustomerPayedCard( $order_id, $acquirer_data ){
        if( !$acquirer_data ){//connection error need to repeat
            return 'connection_error';
        }
        if( !$acquirer_data->total??0 ){
            return 'forbidden';
        }
        if( $acquirer_data->status=='canceled' || $acquirer_data->status=='partly canceled' ){//already canceled
            return 'canceled';
        }
        if( $acquirer_data->status=='waiting' ){
            return 'waiting';
        }
        $order_data_update=(object)[
            'payment_card_fixate_id'=>$acquirer_data->billNumber,
            'payment_card_fixate_date'=>date('Y-m-d H:i:s'),
            'payment_card_fixate_sum'=>$acquirer_data->total
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
    }
    
    public function onCustomerStart( $order_id, $data ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $PrefModel=model('PrefModel');
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( !empty($order_data->payment_by_card) && empty($order_data->payment_card_fixate_sum) ){
            return 'payment_by_card_missing';
        }
        ///////////////////////////////////////////////////
        //STARTING DELIVERY SEARCH IF NEEDED
        ///////////////////////////////////////////////////
        if( !empty($order_data->delivery_by_courier) ){
            $this->OrderModel->itemStageAdd($order_id, 'delivery_search');
        }
        ///////////////////////////////////////////////////
        //CREATING STAGE RESET JOB
        ///////////////////////////////////////////////////
        $timeout_min=$PrefModel->itemGet('customer_start_timeout_min','pref_value',0);
        $next_start_time=time()+$timeout_min*60;
        $stage_reset_task=[
            'task_name'=>"customer_start Rollback #$order_id",
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['customer_start','supplier_rejected',$order_id]]
                ],
            'task_next_start_time'=>$next_start_time
        ];
        jobCreate($stage_reset_task);
        ///////////////////////////////////////////////////
        //CREATING STAGE NOTIFICATIONS
        ///////////////////////////////////////////////////
        $order=$this->OrderModel->itemGet($order_id);
        $StoreModel->itemCacheClear();
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id);
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer
        ];
        $store_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'template'=>'messages/order/on_customer_start_STORE_sms.php',
            'context'=>$context
        ];
        $store_email=(object)[
            'message_transport'=>'email',
            //'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'message_reciever_email'=>$store->store_email,
            'message_subject'=>"Заказ №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_customer_start_STORE_email.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_customer_start_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"customer_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_sms,$store_email,$cust_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    public function onCustomerRejected( $order_id ){
        $EntryModel=model('EntryModel');

        $EntryModel->listStockMove($order_id,'free');
        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
        
    public function onCustomerDisputed( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');
        
        $StoreModel->itemCacheClear();
        $order=$this->OrderModel->itemGet($order_id,'all');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $courier=$CourierModel->itemGet($order->order_courier_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer,
            'courier'=>$courier
        ];
        $admin_email=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'email',
            'message_subject'=>"Возражение по заказу №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_customer_disputed_ADMIN_email.php',
            'context'=>$context
        ];
        $store_email=(object)[
            //'message_reciever_id'=>($store->owner_id??0).','.($store->owner_ally_ids??0),
            'message_reciever_email'=>$store->store_email,
            'message_transport'=>'email',
            'message_subject'=>"Возражение по заказу №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_customer_disputed_STORE_email.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'message',
            'template'=>'messages/order/on_customer_disputed_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"customer_disputed Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$store_email,$cust_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    public function onCustomerFinish( $order_id ){
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    

    //////////////////////////////////////////////////////////////////////////
    //SUPPLIER HANDLERS
    //////////////////////////////////////////////////////////////////////////
    public function onSupplierRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $EntryModel=model('EntryModel');

        $EntryModel->listStockMove($order_id,'free');
        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);
        
        $StoreModel->itemCacheClear();
        $order=$this->OrderModel->itemGet($order_id,'basic');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer
        ];
        $store_email=(object)[
            'message_reciever_id'=>($store->owner_id??0).','.($store->owner_ally_ids??0),
            'message_transport'=>'email',
            'message_subject'=>"Отмена Заказа №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_supplier_rejected_STORE_email.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'message',
            'template'=>'messages/order/on_supplier_rejected_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_rejected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_email,$cust_sms]]]
                ]
        ];
        jobCreate($notification_task);
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');

        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
        
    public function onSupplierStart($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( isset($order_data->delivery_by_store) || isset($order_data->pickup_by_customer) ){
            $order=$this->OrderModel->itemGet($order_id);
            $LocationModel=model("LocationModel");
            $customerLocation=$LocationModel->itemGet($order->order_finish_location_id);
            $update=(object)[
                'info_for_supplier'=>json_encode([
                    'customer_location_address'=>$customerLocation->location_address,
                    'customer_location_comment'=>$customerLocation->location_comment,
                    'customer_location_latitude'=>$customerLocation->location_latitude,
                    'customer_location_longitude'=>$customerLocation->location_longitude,
                    'customer_phone'=>$order->customer->user_phone,
                    'customer_name'=>$order->customer->user_name,
                    'customer_email'=>$order->customer->user_email,
                ])
            ];
            $this->OrderModel->itemDataUpdate($order_id,$update);
        }
        return 'ok';
    }
    
    public function onSupplierCorrected($order_id){
        $order=$this->OrderModel->itemGet($order_id);
        $context=[
            'order'=>$order,
        ];
        $cust_sms=(object)[
            'message_transport'=>'push',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_supplier_corrected_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_corrected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$cust_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }
    
    public function onSupplierReclaimed($order_id){
        /*
         * Should we start reclamation???? 
         * Or admin should do it from cloud control panel???
         * Or money should stay as prepay at account???
         * 
         * Penalty to courier???
         * Penalty to customer???
         */
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    public function onSupplierFinish( $order_id ){
        $order=$this->OrderModel->itemGet($order_id,'basic');
        $order_data=$this->OrderModel->itemDataGet($order_id);

        if( isset($order_data->payment_by_card) ){
            if( empty($order_data->payment_card_fixate_sum) ){
                return 'order_sum_undefined';
            }
            if( $order_data->payment_card_fixate_sum < $order->order_sum_total ){
                return 'order_sum_exceeded';
            }
        }
        if( !($order->order_sum_product>0) ){
            return 'order_sum_zero';
        }
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'commited');

        if( !isset($order_data->delivery_by_courier) ){
            return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
        }
        ///////////////////////////////////////////////////
        //CREATING STAGE RESET JOB
        ///////////////////////////////////////////////////
        $PrefModel=model('PrefModel');
        $timeout_min=$PrefModel->itemGet('delivery_no_courier_timeout_min','pref_value',0);
        $next_start_time=time()+$timeout_min*60;
        $stage_reset_task=[
            'task_name'=>"check if Courier was not found #$order_id",
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['supplier_finish','delivery_no_courier',$order_id]]
                ],
            'task_next_start_time'=>$next_start_time
        ];
        helper('job');
        jobCreate($stage_reset_task);
        return 'ok';
    }
    
    
    //////////////////////////////////////////////////////////////////////////
    //DELIVERY HANDLERS
    //////////////////////////////////////////////////////////////////////////
    public function onDeliverySearch( $order_id ){
        $order=$this->OrderModel->itemGet($order_id);
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');
        $StoreModel->itemCacheClear();
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $context=[
            'store'=>$store,
        ];
        $CourierModel->listNotify($context);
        return 'ok';
    }
    
    public function onDeliveryStart( $order_id ){
        $CourierModel=model('CourierModel');
        if( !$CourierModel->isReadyCourier() ){
            return 'wrong_courier_status';
        }
        $order=$this->OrderModel->itemGet($order_id);
        if( !$order->images ){
            return 'photos_must_be_made';
        }
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');

        $order=$this->OrderModel->itemGet($order_id);
        $LocationModel=model("LocationModel");
        $customerLocation=$LocationModel->itemGet($order->order_finish_location_id);
        $update=(object)[
            'info_for_courier'=>json_encode([
                'customer_location_address'=>$customerLocation->location_address,
                'customer_location_comment'=>$customerLocation->location_comment,
                'customer_location_latitude'=>$customerLocation->location_latitude,
                'customer_location_longitude'=>$customerLocation->location_longitude,
                'customer_phone'=>$order->customer->user_phone,
                'customer_name'=>$order->customer->user_name,
                'customer_email'=>$order->customer->user_email,
            ])
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);

        return 'ok';
    }
    public function onDeliveryRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['is_canceled'=>1]);

        $StoreModel->itemCacheClear();
        $order=$this->OrderModel->itemGet($order_id,'all');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $courier=$CourierModel->itemGet($order->order_courier_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store,
            'courier'=>$courier,
            'customer'=>$customer
        ];
        $admin_email=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'email',
            'message_subject'=>"#{$order->order_id} ДОСТАВКА НЕ УДАЛАСЬ",
            'template'=>'messages/order/on_delivery_rejected_ADMIN_email.php',
            'context'=>$context
        ];
        $admin_sms=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'message',
            'template'=>'messages/order/on_delivery_rejected_ADMIN_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"delivery_rejected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$admin_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    public function onDeliveryNoCourier( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['is_canceled'=>1]);
        
        $StoreModel->itemCacheClear();
        $order=$this->OrderModel->itemGet($order_id,'basic');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer
        ];
        $admin_email=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'email',
            'message_subject'=>"#{$order->order_id} Курьер не найден",
            'template'=>'messages/order/on_delivery_nocourier_ADMIN_email.php',
            'context'=>$context
        ];
        $admin_sms=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'message',
            'template'=>'messages/order/on_delivery_nocourier_ADMIN_sms.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_transport'=>'push',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_delivery_no_courier_CUST_sms.php',
            'context'=>$context
        ];
        $store_sms=(object)[
            'message_transport'=>'push',
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'template'=>'messages/order/on_customer_start_STORE_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"delivery_notfound Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$admin_sms,$cust_sms,$store_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }

    public function onDeliveryFinish( $order_id ){
        //make transaction for commission of courier
        $PrefModel=model('PrefModel');
        ///////////////////////////////////////////////////
        //CREATING STAGE RESET JOB
        ///////////////////////////////////////////////////
        $timeout_min=(int)$PrefModel->itemGet('delivery_finish_timeout_min','pref_value',0);
        $next_start_time=time()+$timeout_min*60;
        $stage_reset_task=[
            'task_name'=>"system_reckon Fastforward #$order_id",
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['delivery_finish','customer_finish',$order_id]]
                ],
            'task_next_start_time'=>$next_start_time
        ];
        jobCreate($stage_reset_task);
        return 'ok';
    }
}