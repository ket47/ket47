<?php
namespace App\Models;

class OrderStageSupplierScript{
    public $OrderModel;
    public $stageMap=[
        ''=>[
            'customer_cart'=>               ['Создать'],
            ],
        'customer_cart'=>[
            'customer_action_confirm'=>     ['Перейти к оформлению'],
            'customer_action_add'=>         ['Добавить товар','medium','clear'],
            'customer_deleted'=>            ['Удалить','danger','clear'],
            'customer_confirmed'=>          [],
            ],
        'customer_deleted'=>                [],
        'customer_confirmed'=>[
            'customer_action_checkout'=>    ['Перейти к оформлению'],
            'customer_cart'=>               ['Изменить','light'],
            'customer_start'=>              [],
            ],
        'customer_start'=>[
            'system_schedule'=>             [],
            'system_start'=>                ["Запустить"],
            ],
        'customer_rejected'=>[
            'system_reckon'=>               []
            ],


        'system_schedule'=>[
            'supplier_start'=>              [],
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            ],
        'system_start'=>[
            'customer_start'=>              [],//for compatibility reasons
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'supplier_start'=>              ['Начать подготовку'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger','clear'],
            ],
            
        
        'supplier_rejected'=>[
            'system_reckon'=>               ['Завершить (авто)','medium','clear'],
            ],
        'supplier_reclaimed'=>[
            'admin_supervise'=>             ['Решить спор','danger'],
            ],
        'supplier_start'=>[
            'supplier_finish'=>             ['Завершить подготовку','success'],
            'supplier_corrected'=>          ['Изменить','medium','clear'],
            'supplier_action_take_photo'=>  ['Сфотографировать','medium','clear'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger','clear'],
            ],
        'supplier_corrected'=>[
            'supplier_start'=>              ['Сохранить изменения'],
            'supplier_action_add'=>         ['Добавить товар','medium','clear'],
            ],
        'supplier_finish'=>[
            'system_reckon'=>               [],
            ],


        'admin_supervise'=>[
            'system_reckon'=>               ['Завершить','success'],
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
        'admin_deposit_accept'=>[
            'system_reckon'=>               [],
        ],
            

        'system_reckon'=>[
            'system_finish'=>               ['Завершить','success'],
            'admin_supervise'=>             ['Установить статус','danger','outline'],
            ],
        'system_finish'=>[
            'admin_recalculate'=>           ['Перепровести','danger','outline'],
            'admin_delete'=>                ['Удалить полностью','danger','outline'],
        ]
    ];

    
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
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'free');
        return 'ok';
    }

    private function isAwaitingPayment($order_id){
        $user_id=session()->get('user_id');
        /**
         * If user wants to return to cart. allow
         */
        if( $user_id!=-100 ){
            return false;
        }
        $now=time();
        $orderData=$this->OrderModel->itemDataGet($order_id);
        $await_payment_until=$orderData->await_payment_until??null;
        /**
         * If order is waiting for payment now then reset to cart after timeout
         */
        if( $await_payment_until && $await_payment_until>$now ){
            $stage_reset_task=[
                'task_name'=>"customer_confirmed Rollback #$order_id",
                'task_programm'=>[
                        ['method'=>'orderResetStage','arguments'=>['customer_confirmed','customer_cart',$order_id]]
                    ],
                'is_singlerun'=>1,
                'task_next_start_time'=>$await_payment_until+1,
                'task_priority'=>'low'
            ];
            jobCreate($stage_reset_task);
            return true;
        }
        return false;
    }

    /**
     * offCustomerConfirmed
     * 
     * before exit customer confirmed stage check if card_payment is done
     * if so reject passing to cart
     * 
     */
    public function offCustomerConfirmed( $order_id, $stage_next ){
        if( $stage_next=='customer_cart' ){
            /**
             * Checking if payment is done. Add stage customer_payed_card
             */
            $order_data=$this->OrderModel->itemDataGet($order_id);
            if( empty($order_data->payment_by_card) ){
                return 'ok';
            }
            if($order_data->payment_card_acq_rncb??0){
                $Acquirer=new \App\Libraries\AcquirerRncb();
            } else {
                $Acquirer=\Config\Services::acquirer();
            }
            $result=$Acquirer->statusCheck( $order_id );
            if( $result!='order_not_payed' ){
                return 'already_payed';
            }
            /**
             * Payment HPP may still be open so reject reset to cart
             */
            if( $this->isAwaitingPayment($order_id) ){
                return 'awaiting_payment';
            }
        }
        return 'ok';
    }


    public function onCustomerConfirmed( $order_id ){
        //$order=$this->OrderModel->itemGet( $order_id, 'basic' );
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
        //AUTORESET SECTION
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
            'task_next_start_time'=>$next_start_time,
            'task_priority'=>'low'
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
        $this->OrderModel->itemStageCreate($order_id, 'customer_start');
        return 'ok';
    }
    
    /**
     * Older client apps can call customer_start multiple times. Here we are allowing it without responding with error
     */
    public function offSystemStart( $order_id, $stage_next ){
        if( $stage_next == 'customer_start' ){
            $order_data_update=(object)[
                'enable_turnaround_system_start'=>1
            ];
            $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        }
        return 'ok';
    }

    public function onCustomerStart( $order_id, $data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( empty($order_data->payment_card_fixate_sum) && empty($order_data->payment_by_cash_store) ){
            return 'payment_is_missing';
        }
        ///////////////////////////////////////////////////
        //FOR COMPATIBILITY WITH OLDER CLIENTS
        ///////////////////////////////////////////////////
        if( isset($order_data->enable_turnaround_system_start) ){
            return $this->OrderModel->itemStageCreate($order_id,'system_start',$order_data,'as_admin');
        }
        ///////////////////////////////////////////////////
        //COPYING STORE OWNERS TO ORDER OWNERS
        ///////////////////////////////////////////////////
        $this->OrderModel->itemUpdateOwners($order_id);
        ///////////////////////////////////////////////////
        //CREATING STAGE NOTIFICATIONS
        ///////////////////////////////////////////////////
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $order=$this->OrderModel->itemGet($order_id);
        $store=$StoreModel->itemGet($order->order_store_id);
        $customer=$UserModel->itemGet($order->owner_id);
        $context=[
            'order'=>$order,
            'order_data'=>$order_data,
            'store'=>$store,
            'customer'=>$customer
        ];
        $notifications=[];
        $notifications[]=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>-100,
            'template'=>'messages/order/on_customer_start_ADMIN_sms.php',
            'context'=>$context,
        ];
        $notification_task=[
            'task_name'=>"system_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
            ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);

        $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');
        helper('phone_number');
        $info_for_customer->supplier_name=$order->store->store_name;
        $info_for_customer->supplier_phone='+'.clearPhone($order->store->store_phone);
        $info_for_customer->tariff_info=view('order/customer_tariff_info.php',['order_data'=>$order_data,'order'=>$order]);

        $update=(object)[
            'info_for_customer'=>json_encode($info_for_customer),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);
        ///////////////////////////////////////////////////
        //JUMPING TO SCHEDULED
        ///////////////////////////////////////////////////
        if( $order_data->start_plan_mode=='scheduled' && $order_data->init_plan_scheduled>time() ){
            return $this->OrderModel->itemStageCreate($order_id,'system_scheduled',$order_data,'as_admin');
        }
        return $this->OrderModel->itemStageCreate($order_id,'system_start',$order_data,'as_admin');
    }

    public function onSystemSchedule( $order_id ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $set_on_queue_task=[
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['system_schedule','system_start',$order_id]]
                ],
            'task_next_start_time'=>$order_data->init_plan_scheduled
        ];
        jobCreate($set_on_queue_task);
        return 'ok';
    }

    public function onSystemStart( $order_id, $order_data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        ///////////////////////////////////////////////////
        //FOR COMPATIBILITY WITH OLDER CLIENTS
        ///////////////////////////////////////////////////
        if( isset($order_data->enable_turnaround_system_start) ){
            return 'ok';
        }
        $order=$this->OrderModel->itemGet($order_id);

        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
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
            'message_transport'=>'telegram,push',
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
            'message_reciever_email'=>$store->store_email,
            'message_subject'=>"Заказ №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_customer_start_STORE_email.php',
            'context'=>$context
        ];
        $timer_supplier_passive=time()+5*60;//5min
        $this->onSupplierCalled($order_id,(object)['start_at'=>$timer_supplier_passive,'attempts_left'=>3]);

        $cust_sms=(object)[
            'message_transport'=>'telegram,push',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_customer_start_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_sms,$store_email,$cust_sms]]]
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);

        $store_preparation_time=$store->store_time_preparation??30;
        $timer_supplier_overdue=time()+$store_preparation_time*60*1.2;//preparation time +20%
        $stage_reset_task=[
            'task_programm'=>[
                    ['model'=>'UserModel','method'=>'systemUserLogin'],
                    ['model'=>'OrderModel','method'=>'itemStageAdd','arguments'=>[$order_id,'supplier_overdue']],
                    ['model'=>'UserModel','method'=>'systemUserLogout'],
                ],
            'task_next_start_time'=>$timer_supplier_overdue,
            'task_priority'=>'low'
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
                'sound'=>'short.wav'
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
            'message_transport'=>'push,telegram',
            'message_data'=>(object)[
                'sound'=>'short.wav'
            ],
            'template'=>'messages/order/on_customer_rejected_COUR_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"customer_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_sms,$store_email,$cour_sms]]]
                ],
            'task_priority'=>'low'
        ];

        jobCreate($notification_task);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
            ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
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

        
        $store_block_finish_at=time()+24*60*60;//24 hours
        $StoreModel->itemCacheClear();
        $order=$this->OrderModel->itemGet($order_id,'basic');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer,
            'store_block_finish_at'=>date('d.m.Y H:i:s',$store_block_finish_at)
        ];
        $store_sms=(object)[
            'message_transport'=>'push,telegram',
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
                'sound'=>'short.wav'
            ],
            'template'=>'messages/order/on_supplier_rejected_CUST_sms.php',
            'context'=>$context
        ];

        $notification_task=[
            'task_name'=>"supplier_rejected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_email,$cust_sms,$store_sms]]]
                ],
            'task_priority'=>'low'
        ];



        $store_unblocking_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>$store->owner_id.',-100,'.$store->owner_ally_ids,
            'message_data'=>(object)[
                'sound'=>'short.wav'
            ],
            'message_text'=>"Время блокировки {$store->store_name} истекло",
        ];
        $store_unblocking_task=[
            'task_name'=>"free the courier",
            'task_programm'=>[
                ['model'=>'UserModel','method'=>'systemUserLogin'],
                ['model'=>'StoreModel','method'=>'itemDisable','arguments'=>[$order->order_store_id,0]],
                ['model'=>'UserModel','method'=>'systemUserLogout'],
                ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_unblocking_sms]]],
            ],
            'task_priority'=>'low',
            'task_next_start_time'=>$store_block_finish_at
        ];

        jobCreate($store_unblocking_task);
        jobCreate($notification_task);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
            ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
    }
        
    public function onSupplierStart($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $info_for_supplier=(object)json_decode($order_data->info_for_supplier??'[]');


        $order=$this->OrderModel->itemGet($order_id);
        $LocationModel=model("LocationModel");
        $customerLocation=$LocationModel->itemGet($order->order_finish_location_id);

        helper('phone_number');
        $info_for_supplier->customer_location_address=$customerLocation->location_address;
        $info_for_supplier->customer_location_comment=$customerLocation->location_comment;
        $info_for_supplier->customer_location_latitude=$customerLocation->location_latitude;
        $info_for_supplier->customer_location_longitude=$customerLocation->location_longitude;

        $info_for_supplier->customer_phone='+'.clearPhone($order->customer->user_phone);
        $info_for_supplier->customer_name=$order->customer->user_name;
        $info_for_supplier->customer_email=$order->customer->user_email;
        $info_for_supplier->tariff_info=view('order/supplier_tariff_info.php',['order_data'=>$order_data]);

        if($order_data->pickup_by_customer??0){
            $info_for_supplier->pickup_by_customer=$order_data->pickup_by_customer;
        }
        if($order_data->payment_card_fixate_sum??0){
            $info_for_supplier->payment_card_fixate_sum=$order_data->payment_card_fixate_sum;
        }
        $update=(object)[
            'info_for_supplier'=>json_encode($info_for_supplier)
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);
        return 'ok';
    }
    
    public function onSupplierCorrected($order_id){
        //$order_data=$this->OrderModel->itemDataGet($order_id);
        // if( !($order_data->store_correction_allow??0) ){
        //     return 'forbidden_bycustomer';
        // }
        $order=$this->OrderModel->itemGet($order_id,'all');
        $context=[
            'order'=>$order,
        ];
        $cust_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>"{$order->owner_id}",
            'message_data'=>(object)[
                'sound'=>'short.wav'
            ],
            'template'=>'messages/order/on_supplier_corrected_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_corrected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$cust_sms]]]
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
        return 'ok';
    }
    
    public function offSupplierCorrected($order_id){
        $order=$this->OrderModel->itemGet($order_id,'basic');
        if( $order->order_sum_total<1 ){
            return 'order_sum_zero';
        }
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( isset($order_data->payment_card_fixate_sum) && $order->order_sum_total>$order_data->payment_card_fixate_sum ){
            return 'order_sum_exceeded';
        }
        return 'ok';
    }
    
    
    public function onSupplierReclaimed($order_id){
        return 'ok';
    }

    public function onSupplierCalled( $order_id, $data ){
        $order=$this->OrderModel->itemGet($order_id,'basic');
        if( !$data || !in_array($order->stage_current??'',['system_start']) ){
            return 'idle';
        }

        $schedule_only=true;
        if( $data->start_at<=time() ){
            $schedule_only=false;
        }
        if( $data->attempts_left && $data->attempts_left>1 ){
            $data->attempts_left--;
            $data->start_at=time()+5*60;//5min
            $stage_reset_task=[
                'task_programm'=>[
                        ['model'=>'UserModel','method'=>'systemUserLogin'],
                        ['model'=>'OrderModel','method'=>'itemStageAdd','arguments'=>[$order_id,'supplier_called',$data]],
                        ['model'=>'UserModel','method'=>'systemUserLogout'],
                    ],
                'task_next_start_time'=>$data->start_at
            ];
            jobCreate($stage_reset_task);
        }
        if( $schedule_only ){
            return 'scheduled';
        }
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $store_voice=(object)[
            'message_transport'=>'voice',
            'template'=>'messages/order/on_supplier_passive_STORE_voice.php',
            'context'=>[],
        ];

        helper('phone_number');
        $store_phone_cleared= clearPhone($store->store_phone);
        if( $store_phone_cleared ){
            $store_voice->message_reciever_phone=$store_phone_cleared;
        } else {
            $store_voice->message_reciever_id=$store->owner_id;//.','.$store->owner_ally_ids,
        }
        $copy=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'telegram',
            'message_text'=>"{$store->store_name} прозвон о заказе",
            'telegram_options'=>[
                'opts'=>[
                    'disable_notification'=>1,
                ]
            ],
        ];
        $notification_task=[
            'task_name'=>"supplier_passive Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_voice,$copy]]]
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    public function onSupplierOverdue( $order_id  ){
        $order=$this->OrderModel->itemGet($order_id,'basic');
        if( !in_array($order->stage_current??'',['customer_start','supplier_start','supplier_corrected']) ){
            return 'idle';
        }
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store
        ];
        $customer_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_supplier_overdue_CUSTOMER_sms.php',
            'context'=>$context
        ];
        $store_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'template'=>'messages/order/on_supplier_overdue_STORE_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_overdue Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$customer_sms,$store_sms]]]
                ],
            'task_priority'=>'low'
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
        if( !($order->order_sum_product>0) || !($order->order_sum_total>0) ){
            return 'order_sum_zero';
        }
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'commited');

        $this->onSupplierFinishPrepTimeUpdate( $order_id, $order->order_store_id );
        // jobCreate([
        //     'task_programm'=>[
        //             ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
        //     ],
        //     'task_next_start_time'=>time()+1
        // ]);
        return $this->OrderModel->itemStageCreate($order_id,'system_reckon',null,'as_admin');
    }
    
    
    //////////////////////////////////////////////////////////////////////////
    //DELIVERY HANDLERS
    //////////////////////////////////////////////////////////////////////////

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
            'order_is_canceled'=>0
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
            'finalize_refund_done'=>0,
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
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);

        $this->OrderModel->itemDelete($order_id);
        return 'ok';
    }
    /**
     * Courier brings cash to admin
     */
    public function onAdminDepositAccept( $order_id ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( !($order_data->payment_by_cash??null) ){
            return 'deposit_inapplicable';
        }
        $order_data_update=(object)[
            'payment_by_cash_accepted'=>1          
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
                ]
        ]);
        return 'ok';        
    }
    ////////////////////////////////////////////////
    //SYSTEM HANDLERS
    ////////////////////////////////////////////////
    public function onSystemPostpay( $order_id, $data=null ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( $order_data->payment_card_fixate_id??null ){
            return 'already_payed';
        }
    }
    public function onSystemReckon( $order_id, $data ){
        if( $data['delay_sec']??0 ){
            sleep($data['delay_sec']);//DO AFTER DELAY
        }
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_finish', $data]]
            ],
            'task_next_start_time'=>time()+1
        ]);
        $retry_delay_min=7;
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_finish', $data]]
            ],
            'task_next_start_time'=>time()+$retry_delay_min*60
        ]);
        // $result=$this->OrderModel->itemStageCreate($order_id,'system_finish',null,'as_admin');
        // if( $result!='ok' ){
        //     $retry_delay_min=7;
        //     jobCreate([
        //         'task_programm'=>[
        //                 ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_finish', $data]]
        //         ],
        //         'task_next_start_time'=>time()+$retry_delay_min*60
        //     ]);
        // }
        return 'ok';
    }
    public function onSystemFinish( $order_id ){
        /**
         * we should pause db transaction so API cals can be atomized
         */
        //$this->OrderModel->transComplete();
        $OrderTransactionModel=model('OrderTransactionModel');
        $result=$OrderTransactionModel->orderFinalize($order_id)?'ok':'fail';
        //$this->OrderModel->transBegin();
        return $result;
    }
}