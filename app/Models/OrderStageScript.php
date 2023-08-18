<?php
namespace App\Models;

class OrderStageScript{
    public $OrderModel;
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
            'customer_confirmed'=>          [],
            'customer_action_confirm'=>     ['Перейти к оформлению'],
            'customer_action_add'=>         ['Добавить товар','medium','outline'],
            'customer_deleted'=>            ['Удалить','danger','clear'],
            ],
        'customer_deleted'=>                [],
        'customer_confirmed'=>[
            'customer_cart'=>               ['Изменить','light'],
            'customer_start'=>              [],
            'customer_action_checkout'=>    ['Продолжить'],
            ],
        'customer_start'=>[
            'supplier_start'=>              ['Начать подготовку'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger','clear'],
            'customer_rejected'=>           ['Отменить заказ','danger','clear']
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
            'supplier_action_take_photo'=>  ['Сфотографировать'],
            'delivery_no_courier'=>         [],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger','clear'],
            ],
        'supplier_corrected'=>[
            'supplier_start'=>              ['Сохранить изменения','success'],
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
            'delivery_action_rejected'=>    ['Отказаться от доставки','danger','clear'],
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
            'supplier_corrected'=>          ['Исправить заказ'],
            'admin_sanction_customer'=>     ['Оштрафовать клиента','danger'],
            'admin_sanction_supplier'=>     ['Оштрафовать продавца','danger'],
            'admin_sanction_courier'=>      ['Оштрафовать курьера','danger'],
            ],
        'admin_sanction_customer'=>[
            'system_reckon'=>               [],
            ],
        'admin_sanction_supplier'=>[
            'system_reckon'=>               [],
            ],
        'admin_sanction_courier'=>[
            'system_reckon'=>               [],
            ],
        'admin_recalculate'=>[
            'system_reckon'=>               [],
            ],
        'admin_delete'=>[
            ],
            

        'system_reckon'=>[
            'system_finish'=>               [],
            'admin_supervise'=>             ['Установить статус','danger','outline'],
            ],
        'system_finish'=>[
            'admin_recalculate'=>           ['Перепровести','danger','outline'],
            'admin_delete'=>                ['Удалить полностью','danger','outline'],
        ]
    ];

    
    ////////////////////////////////////////////////
    //ADMIN HANDLERS
    ////////////////////////////////////////////////
    public function onAdminSupervise( $order_id ){
        return 'ok';
    }
    public function onAdminSanctionCustomer( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>1,
            'sanction_courier_fee'=>0,
            'sanction_supplier_fee'=>0,
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    public function onAdminSanctionCourier( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>0,
            'sanction_courier_fee'=>1,
            'sanction_supplier_fee'=>0,
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    public function onAdminSanctionSupplier( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>0,
            'sanction_courier_fee'=>0,
            'sanction_supplier_fee'=>1,
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
    public function onAdminRecalculate($order_id){
        $order_data_update=(object)[
            'finalize_settle_supplier_done'=>0,
            'finalize_settle_courier_done'=>0,
            'finalize_settle_system_done'=>0,            
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);

        // $TransactionModel->where('trans_holder','order');
        // $TransactionModel->where('trans_holder_id',$order_id); 
        // $TransactionModel->delete(null,true);

        $TransactionModel=model('TransactionModel');
        $TransactionModel->queryDelete("order:$order_id");

        $result=$this->OrderModel->itemStageCreate($order_id, 'system_reckon', ['delay_sec'=>1]);
        return $result;
    }
    public function onAdminDelete($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $admin_email=(object)[
            'message_transport'=>'email',
            'message_reciever_id'=>-100,
            'message_subject'=>"ПОЛНОЕ УДАЛЕНИЕ ЗАКАЗА №{$order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_admin_delete_ADMIN_email.php',
            'context'=>$order_data
        ];
        $notification_task=[
            'task_name'=>"customer_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email]]]
                ]
        ];
        jobCreate($notification_task);

        $this->OrderModel->itemDelete($order_id);
        return 'ok';
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
        if( $data['delay_sec']??0 ){
            $finishing_task['task_next_start_time']=time()+$data['delay_sec'];//DO AFTER DELAY
        }
        jobCreate($finishing_task);
        $finishing_task['task_next_start_time']=time()+3*60;//SECOND TRY AFTER 3 MIN DO WE NEED IT???
        jobCreate($finishing_task);
        return 'ok';
    }
    public function onSystemFinish( $order_id ){
        /**
         * we should pause db transaction so API cals can be atomized
         */
        //pl(['onSystemFinish11111'],false);
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
            if( $this->isAwaitingPayment($order_id) ){
                return 'awaiting_payment';
            }
        }
        //$this->OrderModel->itemUnDelete($order_id); seems to be unnecessary
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'free');
        //$this->OrderModel->update($order_id,['order_sum_product'=>0]);in this case serious bug
        return 'ok';
    }

    private function isAwaitingPayment($order_id){
        $now=time();
        $await_payment_until=null;
        $user_id=session()->get('user_id');
        if( $user_id==-100 ){//System user autoreset system
            $orderData=$this->OrderModel->itemDataGet($order_id);
            $await_payment_until=$orderData->await_payment_until??null;
        }
        if( $await_payment_until && $await_payment_until>$now ){
            $stage_reset_task=[
                'task_name'=>"customer_confirmed Rollback #$order_id",
                'task_programm'=>[
                        ['method'=>'orderResetStage','arguments'=>['customer_confirmed','customer_cart',$order_id]]
                    ],
                'is_singlerun'=>1,
                'task_next_start_time'=>$await_payment_until
            ];
            jobCreate($stage_reset_task);
            return true;
        }
        return false;
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
        $order=$this->OrderModel->itemGet($order_id);
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
        ///////////////////////////////////////////////////
        //COPYING STORE OWNERS TO ORDER OWNERS
        ///////////////////////////////////////////////////
        $this->OrderModel->itemUpdateOwners($order_id);
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
        //LOCKING PROMOUTION
        ///////////////////////////////////////////////////
        $PromoModel=model('PromoModel');
        $PromoModel->itemOrderDisable($order_id,1);
        ///////////////////////////////////////////////////
        //CREATING STAGE NOTIFICATIONS
        ///////////////////////////////////////////////////
        $StoreModel->itemCacheClear();
        $store=$StoreModel->itemGet($order->order_store_id);
        $customer=$UserModel->itemGet($order->owner_id);
        $context=[
            'order'=>$order,
            'order_data'=>$order_data,
            'store'=>$store,
            'customer'=>$customer
        ];
        $store_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'message_data'=>(object)[
                'sound'=>'long.wav'
            ],
            'telegram_options'=>[
                'buttons'=>[['',"onOrderOpen-{$order_id}",'⚡ Открыть заказ']]
            ],
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




        $admin_sms=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>-100,
            'template'=>'messages/order/on_customer_start_ADMIN_sms.php',
            'context'=>$context
        ];
        $admin_email=(object)[
            'message_transport'=>'email',
            'message_reciever_id'=>-100,
            'message_subject'=>"Заказ №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_customer_start_ADMIN_email.php',
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
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_sms,$store_email,$cust_sms,$admin_sms,$admin_email]]]
                ]
        ];
        jobCreate($notification_task);

        $store_preparation_time=$store->store_time_preparation??30;
        $timer_supplier_overdue=time()+$store_preparation_time*60*1.2;//preparation time +20%
        $stage_reset_task=[
            'task_name'=>"Notify user that supplier overdue order #$order_id",
            'task_programm'=>[
                    ['model'=>'UserModel','method'=>'systemUserLogin'],
                    ['model'=>'OrderModel','method'=>'itemStageAdd','arguments'=>[$order_id,'supplier_overdue']],
                    ['model'=>'UserModel','method'=>'systemUserLogout'],
                ],
            'task_next_start_time'=>$timer_supplier_overdue
        ];
        jobCreate($stage_reset_task);
        return 'ok';
    }

    public function onCustomerRejected( $order_id ){
        $EntryModel=model('EntryModel');
        $StoreModel=model('StoreModel');
        $UserModel=model('UserModel');

        $EntryModel->listStockMove($order_id,'free');
        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);

        $order=$this->OrderModel->itemGet($order_id,'basic');
        $StoreModel->itemCacheClear();
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer
        ];
        $store_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$store->owner_id.',-100,'.$store->owner_ally_ids,
            'message_data'=>(object)[
                'sound'=>'medium.wav'
            ],
            'telegram_options'=>[
                'buttons'=>[['',"onOrderOpen-{$order_id}",'⚡ Открыть заказ']]
            ],
            'template'=>'messages/order/on_customer_rejected_STORE_sms.php',
            'context'=>$context
        ];
        $store_email=(object)[
            'message_transport'=>'email',
            //'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'message_reciever_email'=>$store->store_email,
            'message_subject'=>"Отмена заказа №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_customer_rejected_STORE_email.php',
            'context'=>$context
        ];
        $cour_sms=(object)[
            'message_reciever_id'=>$order->order_courier_admins,
            'message_transport'=>'message',
            'template'=>'messages/order/on_customer_rejected_COUR_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"customer_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_sms,$store_email,$cour_sms]]]
                ]
        ];

        $courier_freeing_task=[
            'task_name'=>"free the courier",
            'task_programm'=>[
                ['model'=>'UserModel','method'=>'systemUserLogin'],
                ['model'=>'OrderGroupMemberModel','method'=>'leaveGroupByType','arguments'=>[$order_id,'delivery_search']],
                ['model'=>'CourierModel','method'=>'itemUpdateStatus','arguments'=>[$order->order_courier_id,'ready']],
                ['model'=>'UserModel','method'=>'systemUserLogout'],
                ]
        ];

        jobCreate($courier_freeing_task);
        jobCreate($notification_task);
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
        $order=$this->OrderModel->itemGet($order_id,'basic');
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'message',
            'template'=>'messages/order/leave_comment.php',
            'context'=>[]
        ];
        $timeout_min=30;
        $next_start_time=time()+$timeout_min*60;
        $notification_task=[
            'task_name'=>"delivery_notfound Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$cust_sms]]]
                ],
            'task_next_start_time'=>$next_start_time
        ];
        jobCreate($notification_task);
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
        $store_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$store->owner_id.',-100,'.$store->owner_ally_ids,
            'message_data'=>(object)[
                'sound'=>'medium.wav'
            ],
            'template'=>'messages/order/on_supplier_rejected_CUST_sms.php',
            'context'=>$context
        ];
        $store_email=(object)[
            'message_reciever_id'=>($store->owner_id??0).',-100,'.($store->owner_ally_ids??0),
            'message_transport'=>'email',
            'message_subject'=>"Отмена Заказа №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_supplier_rejected_STORE_email.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'message',
            'message_data'=>(object)[
                'sound'=>'medium.wav'
            ],
            'template'=>'messages/order/on_supplier_rejected_CUST_sms.php',
            'context'=>$context
        ];
        $cour_sms=(object)[
            'message_reciever_id'=>$order->order_courier_admins,
            'message_transport'=>'message',
            'template'=>'messages/order/on_supplier_rejected_COUR_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_rejected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_email,$cust_sms,$cour_sms,$store_sms]]]
                ]
        ];


        $courier_freeing_task=[
            'task_name'=>"free the courier",
            'task_programm'=>[
                ['model'=>'UserModel','method'=>'systemUserLogin'],
                ['model'=>'OrderGroupMemberModel','method'=>'leaveGroupByType','arguments'=>[$order_id,'delivery_search']],
                ['model'=>'CourierModel','method'=>'itemUpdateStatus','arguments'=>[$order->order_courier_id,'ready']],
                ['model'=>'UserModel','method'=>'systemUserLogout'],
                ]
        ];

        jobCreate($courier_freeing_task);
        jobCreate($notification_task);
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
        
    public function onSupplierStart($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $info_for_supplier=[];
        if( isset($order_data->delivery_by_store) || isset($order_data->pickup_by_customer) ){
            $order=$this->OrderModel->itemGet($order_id);
            $LocationModel=model("LocationModel");
            $customerLocation=$LocationModel->itemGet($order->order_finish_location_id);
            $info_for_supplier=[
                'customer_location_address'=>$customerLocation->location_address,
                'customer_location_comment'=>$customerLocation->location_comment,
                'customer_location_latitude'=>$customerLocation->location_latitude,
                'customer_location_longitude'=>$customerLocation->location_longitude,
                'customer_phone'=>'+'.$order->customer->user_phone,
                'customer_name'=>$order->customer->user_name,
                'customer_email'=>$order->customer->user_email,
            ];
        }
        $info_for_supplier['tariff_info']=view('order/supplier_tariff_info.php',['order_data'=>$order_data]);
        if($order_data->pickup_by_customer??0){
            $info_for_supplier['pickup_by_customer']=$order_data->pickup_by_customer;
        }
        if($order_data->payment_card_fixate_sum??0){
            $info_for_supplier['payment_card_fixate_sum']=$order_data->payment_card_fixate_sum;
        }
        if(count($info_for_supplier)){
            $update=(object)[
                'info_for_supplier'=>json_encode($info_for_supplier)
            ];
            $this->OrderModel->itemDataUpdate($order_id,$update);
        }
        return 'ok';
    }
    
    public function onSupplierCorrected($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( isset($order_data->store_correction_allow) ){
            $order=$this->OrderModel->itemGet($order_id);
            $context=[
                'order'=>$order,
            ];
            $cust_sms=(object)[
                'message_transport'=>'message',
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
        return 'forbidden_bycustomer';
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
        return 'ok';
    }

    public function onSupplierOverdue( $order_id  ){
        $order=$this->OrderModel->itemGet($order_id,'basic');
        if( !in_array($order->stage_current,['customer_start','supplier_start','supplier_corrected']) ){
            return 'idle';
        }
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store
        ];
        $customer_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_supplier_overdue_CUSTOMER_sms.php',
            'context'=>$context
        ];
        $store_sms=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'template'=>'messages/order/on_supplier_overdue_STORE_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_overdue Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$customer_sms,$store_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    private function onSupplierFinishPrepTimeUpdate( $order_id, $store_id ){
        $StoreModel=model('StoreModel');
        $OrderGroupMemberModel=model('OrderGroupMemberModel');

        $OrderGroupMemberModel->where('group_type','customer_start');
        $stages=$OrderGroupMemberModel->memberOfGroupsListGet($order_id);
        if(!$stages){
            return 'idle';
        }
        $actual_prep_time=round( (time()-strtotime($stages[0]->created_at))/60 );
        $store_prep_time=$StoreModel->itemGet($store_id,'basic')->store_time_preparation??30;
        $delta=$actual_prep_time-$store_prep_time;
        $corrected_prep_time=$store_prep_time+round($delta/2);
        return $StoreModel->itemUpdate((object)[
            'store_id'=>$store_id,
            'store_time_preparation'=>$corrected_prep_time
        ]);
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

        $this->onSupplierFinishPrepTimeUpdate( $order_id, $order->order_store_id );

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
            'order'=>$order
        ];
        $CourierModel->listNotify($context);
        return 'ok';
    }

    public function onDeliveryFound( $order_id ){
        // $OrderGroupMemberModel=model('OrderGroupMemberModel');
        // $was_searching=$OrderGroupMemberModel->isMemberOf($order_id,'delivery_search');
        // if( !$was_searching ){
        //     return '';
        // }
        // $OrderGroupMemberModel=model('OrderGroupMemberModel');
        // $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');
        helper('phone_number');

        $order=$this->OrderModel->itemGet($order_id);
        $LocationModel=model("LocationModel");
        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($order->order_courier_id);
        $supplierLocation=$LocationModel->itemGet($order->order_start_location_id);
        $customerLocation=$LocationModel->itemGet($order->order_finish_location_id);
        $update=(object)[
            'info_for_courier'=>json_encode([
                'customer_location_address'=>$customerLocation->location_address??'',
                'customer_location_comment'=>$customerLocation->location_comment??'',
                'customer_location_latitude'=>$customerLocation->location_latitude??'',
                'customer_location_longitude'=>$customerLocation->location_longitude??'',
                'customer_phone'=>'+'.clearPhone($order->customer->user_phone),
                'customer_name'=>$order->customer->user_name,
                'customer_email'=>$order->customer->user_email,

                'supplier_location_address'=>$supplierLocation->location_address??'',
                'supplier_location_comment'=>$supplierLocation->location_comment??'',
                'supplier_location_latitude'=>$supplierLocation->location_latitude??'',
                'supplier_location_longitude'=>$supplierLocation->location_longitude??'',
                'supplier_name'=>$order->store->store_name,
                'supplier_phone'=>'+'.clearPhone($order->store->store_phone),
            ]),
            'info_for_customer'=>json_encode([
                'courier_name'=>$courier->courier_name,
                'courier_phone'=>$courier->user_phone,
                'courier_image_hash'=>$courier->images[0]->image_hash??''
            ]),
            'info_for_supplier'=>json_encode([
                'courier_name'=>$courier->courier_name,
                'courier_phone'=>'+'.clearPhone($courier->user_phone),
                'courier_image_hash'=>$courier->images[0]->image_hash??''
            ]),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);

        $StoreModel=model('StoreModel');
        $StoreModel->itemCacheClear();
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store
        ];
        $admin_sms=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'message',
            'template'=>'messages/order/on_delivery_found_ADMIN_sms.php',
            'context'=>$context
        ];
        $store_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'template'=>'messages/order/on_delivery_found_STORE_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"delivery_found Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_sms,$store_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }
    
    public function onDeliveryStart( $order_id ){
        $CourierModel=model('CourierModel');
        // if( !$CourierModel->isBusy() ){
        //     return 'wrong_courier_status';
        // }
        $order=$this->OrderModel->itemGet($order_id);
        if( !$order->images ){
            return 'photos_must_be_made';
        }
        return 'ok';
    }
    public function onDeliveryRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);

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

        $CourierModel->itemUpdateStatus($order->order_courier_id,'ready');
        return 'ok';
    }

    public function onDeliveryNoCourier( $order_id ){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $is_courier_found=$OrderGroupMemberModel->isMemberOf($order_id,'delivery_found');
        if($is_courier_found){
            return 'idle';
        }
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');

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
            'message_transport'=>'message',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_delivery_no_courier_CUST_sms.php',
            'context'=>$context
        ];
        $store_sms=(object)[
            'message_transport'=>'message',
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
        $order_basic=$this->OrderModel->itemGet($order_id,'basic');
        if($order_basic->order_courier_id){//if stage changed by admin skip this
            $CourierModel=model('CourierModel');
            $CourierModel->itemUpdateStatus($order_basic->order_courier_id,'ready');
        }

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