<?php
namespace App\Models;

class OrderStageDeliveryScript{
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
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'system_start'=>                ['Запустить','danger','clear'],
            'system_schedule'=>             ['Запланировать','danger','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','','clear'],
            ],
        'customer_rejected'=>[
            'system_reckon'=>               []
            ],
        'customer_disputed'=>[
            'customer_finish'=>             ['Принять','success'],
            'customer_action_take_photo'=>  ['Сфотографировать заказ','medium','outline'],
            'supplier_corrected'=>          ['Корректировать'],
            'admin_supervise'=>             ['Решить спор','danger'],
            ],
        'customer_finish'=>[
            'system_reckon'=>               [],
            'delivery_deposit_compensate'=> ['Внести оплату','success'],
            'delivery_action_take_photo'=>  ['Сфотографировать','medium','clear'],
            'admin_deposit_accept'=>        ['Принять наличные','danger'],
            'admin_supervise'=>             ['Решить спор','danger'],
            ],


        'system_schedule'=>[
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'customer_start'=>              ['В очередь','medium','clear'],//
            ],
        'system_start'=>[
            'supplier_start'=>              ['Начать подготовку'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger','clear'],
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','','clear'],
            ],
                    
        
        'supplier_rejected'=>[
            'system_reckon'=>               ['Завершить (авто)','medium','clear'],
            ],
        'supplier_reclaimed'=>[
            'admin_supervise'=>             ['Решить спор','danger'],
            ],
        'supplier_start'=>[
            'supplier_finish'=>             ['Завершить подготовку','success'],
            'supplier_action_take_photo'=>  ['Сфотографировать','light'],
            'supplier_corrected'=>          ['Корректировать','medium','clear'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger','clear'],
            'delivery_force_start'=>        ['Заказ готов к доставке','medium','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        'supplier_corrected'=>[
            'supplier_start'=>              ['Сохранить изменения'],
            'supplier_action_add'=>         ['Добавить товар','medium','clear'],
            'delivery_no_courier'=>         [],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        'supplier_finish'=>[
            'delivery_start'=>              ['Начать доставку'],
            'delivery_no_courier'=>         [],
            'delivery_finish'=>             [],//if dispute is ongoing then fastforward 
            'supplier_action_take_photo'=>  ['Сфотографировать','light'],
            'supplier_corrected'=>          ['Корректировать','medium','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        'delivery_force_start'=>            [
            'supplier_finish'=>             ['Завершить подготовку'],
            'supplier_corrected'=>          ['Корректировать','medium','clear'],
            ],
        'delivery_start'=>[
            'delivery_finish'=>             ['Завершить доставку','success'],
            'delivery_action_take_photo'=>  ['Сфотографировать','light'],
            'delivery_action_rejected'=>    ['Отказаться от доставки','danger','clear'],
            'delivery_rejected'=>           [],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        
        'delivery_rejected'=>[
            'supplier_reclaimed'=>          ['Принять возврат заказа'],
            'delivery_action_take_photo'=>  ['Сфотографировать','light'],
            'admin_supervise'=>             ['Решить спор','danger'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear'],
            ],
        'delivery_no_courier'=>[
            'system_reckon'=>               [],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
        ],
        'delivery_finish'=>[
            'customer_disputed'=>           [],
            'customer_finish'=>             ['Принять заказ','success'],
            'customer_action_objection'=>   ['Открыть спор','light'],
            ],
        'delivery_deposit_compensate'=>[
            'system_reckon'=>               [],
        ],


        'admin_supervise'=>[
            'customer_finish'=>             ['Принять заказ','success'],
            'supplier_corrected'=>          ['Исправить заказ'],
            'admin_sanction_customer'=>     ['Оштрафовать клиента','danger'],
            'admin_sanction_supplier'=>     ['Оштрафовать продавца','danger'],
            'admin_sanction_courier'=>      ['Оштрафовать курьера','danger'],
            ],
        'admin_sanction_customer'=>[
            'system_reckon'=>               ['Завершить','medium','clear'],
            ],
        'admin_sanction_supplier'=>[
            'system_reckon'=>               ['Завершить','medium','clear'],
            ],
        'admin_sanction_courier'=>[
            'system_reckon'=>               ['Завершить','medium','clear'],
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
            'system_finish'=>               [],
            'admin_supervise'=>             ['Установить статус','danger','clear'],
            ],
        'system_finish'=>[
            'admin_recalculate'=>           ['Перепровести','danger','clear'],
            'admin_delete'=>                ['Удалить полностью','danger','clear'],
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
     * Remind left cart to customer
     */
    private function offCustomerConfirmedRemind($order_id){
        $order=$this->OrderModel->itemGet($order_id,'basic');
        $notifications=[];
        $notifications[]=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/events/on_user_cart_left_sms.php',
            'context'=>[],
        ];
        $notification_task=[
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
            ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
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
                if( session()->get('user_id')==-100 ){//only if auto cart reset
                    $this->offCustomerConfirmedRemind($order_id);
                }
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
        if( $acquirer_data->payment_card_acquirer??null ){
            //updating aquirer handler to last used
            $order_data_update->payment_card_acquirer=$acquirer_data->payment_card_acquirer;
        }

        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( $order_data->payment_by_cash??null ){
            /**
             * Paynig deposit compensate
             */
            $order_data_update->payment_by_card=1;
            jobCreate([
                'task_programm'=>[
                        ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
                ],
                'task_next_start_time'=>time()+1
            ]);
            $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
            return 'ok';
        }
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        $this->OrderModel->itemStageCreate($order_id, 'customer_start');
        return 'ok';
    }

    private function onCustomerStartBonusLock($order_basic){
        $PromoModel=model('PromoModel');
        $promo=[
            'owner_id'=>$order_basic->owner_id,
            'promo_order_id'=>$order_basic->order_id,
            'promo_value'=>-$order_basic->order_sum_promo,
            'promo_name'=>'lock',
            'is_summable'=>1,
            'is_disabled'=>0,
            'expired_at'=>'2038-01-01 00:00:00'
        ];
        $PromoModel->insert($promo);
    }

    public function onCustomerStart( $order_id, $data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( empty($order_data->payment_card_fixate_sum) && empty($order_data->payment_by_cash) ){
            return 'payment_is_missing';
        }
        ///////////////////////////////////////////////////
        //COPYING INFO INTO ORDER
        ///////////////////////////////////////////////////
        $order=$this->OrderModel->itemGet($order_id);
        $this->onSystemStartInfoSet( $order, $order_data );
        ///////////////////////////////////////////////////
        //LOCKING PROMOTION
        ///////////////////////////////////////////////////
        $PromoModel=model('PromoModel');
        $PromoModel->itemOrderDisable($order_id,1);
        if( $order_data->bonus_mode=='spend' ){//should lock bonus balance untill order finish
            $this->onCustomerStartBonusLock($order);
            pl("PROMO LOCK {$order_data->bonus_mode}");
        }
        ///////////////////////////////////////////////////
        //JUMPING TO SCHEDULED
        ///////////////////////////////////////////////////
        if( $order_data->start_plan_mode=='scheduled' && $order_data->init_plan_scheduled>time() ){
            return $this->OrderModel->itemStageCreate($order_id,'system_schedule',$order_data,'as_admin');
        }
        ///////////////////////////////////////////////////
        //MARK AS SEARCHING FOR COURIER
        ///////////////////////////////////////////////////
        $this->OrderModel->itemStageAdd($order_id, 'delivery_search');
        ///////////////////////////////////////////////////
        //CREATING STAGE NOTIFICATIONS
        ///////////////////////////////////////////////////
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($order->order_store_id);
        $customer=$UserModel->itemGet($order->owner_id);

        $context=[
            'order'=>$order,
            'order_data'=>$order_data,
            'store'=>$store,
            'customer'=>$customer,
            'user_agent'=>session()->get('user_agent')
        ];
        $notifications=[];
        $notifications[]=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>-100,
            'template'=>'messages/order/on_customer_start_ADMIN_sms.php',
            'context'=>$context,
            'telegram_options'=>[
                'append_order_id'=>$order_id
            ]
        ];
        $notification_task=[
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
            ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);

        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'awaited', $order_data->delivery_job]]
        ]];
        jobCreate($deliveryJob);

        if( $order_data->start_plan_mode=='inited' ){
            return $this->OrderModel->itemStageCreate($order_id,'system_start',$order_data,'as_admin');
        }
        return 'ok';
    }

    public function onSystemSchedule( $order_id ){
        $this->OrderModel->itemDataUpdate($order_id,(object)['start_plan_mode'=>'awaited']);

        $order_data=$this->OrderModel->itemDataGet($order_id);
        $order_data->delivery_job->courier_id=null;
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'scheduled', $order_data->delivery_job]]
        ]];
        jobCreate($deliveryJob);
        if( isset($order_data->init_plan_scheduled) ){
            $set_on_queue_task=[
                'task_programm'=>[
                        ['method'=>'orderResetStage','arguments'=>['system_schedule','customer_start',$order_id]]
                    ],
                'task_next_start_time'=>$order_data->init_plan_scheduled
            ];
            jobCreate($set_on_queue_task);
        }
        return 'ok';
    }


    private function onSystemStartInfoSet( object $order, object $order_data ){
        if( $order_data->info_system_start_is_set??0 ){
            return true;
        }
        helper('phone_number');
        $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');
        $info_for_courier=(object)json_decode($order_data->info_for_courier??'[]');
        $info_for_supplier=(object)json_decode($order_data->info_for_supplier??'[]');

        $info_for_courier->customer_location_address=$order_data->location_finish->location_address??'';
        $info_for_courier->customer_location_comment=$order_data->location_finish->location_comment??'';
        $info_for_courier->customer_location_latitude=$order_data->location_finish->location_latitude??'';
        $info_for_courier->customer_location_longitude=$order_data->location_finish->location_longitude??'';
        $info_for_courier->customer_phone='+'.clearPhone($order->customer->user_phone);
        $info_for_courier->customer_name=$order->customer->user_name;
        $info_for_courier->customer_email=$order->customer->user_email;

        $info_for_courier->supplier_location_address=$order_data->location_start->location_address??'';
        $info_for_courier->supplier_location_comment=$order_data->location_start->location_comment??'';
        $info_for_courier->supplier_location_latitude=$order_data->location_start->location_latitude??'';
        $info_for_courier->supplier_location_longitude=$order_data->location_start->location_longitude??'';
        $info_for_courier->supplier_phone='+'.clearPhone($order->store->store_phone);
        $info_for_courier->supplier_name=$order->store->store_name??'';
        $info_for_courier->supplier_email=$order->store->store_email??'';

        if( $order_data->payment_by_cash??null ){
            $info_for_customer->tariff_info =view('order/customer_cashpayment_info.php',['order'=>$order,'order_data'=>$order_data]);
            $info_for_courier->tariff_info  =view('order/delivery_cashpayment_info.php',['order'=>$order,'order_data'=>$order_data]);
        }
        if( $order_data->finish_plan_scheduled??null ){
            $info_for_customer->tariff_info =view('order/customer_scheduled_info.php',['order'=>$order,'order_data'=>$order_data]).($info_for_customer->tariff_info??'');
            $info_for_courier->tariff_info  =view('order/delivery_scheduled_info.php',['order'=>$order,'order_data'=>$order_data]).($info_for_courier->tariff_info??'');
            $info_for_supplier->tariff_info =view('order/supplier_scheduled_info.php',['order'=>$order,'order_data'=>$order_data]).($info_for_supplier->tariff_info??'');
        }
        $update=(object)[
            'info_for_customer'=>json_encode($info_for_customer),
            'info_for_courier'=>json_encode($info_for_courier),
            'info_for_supplier'=>json_encode($info_for_supplier),
            'info_system_start_is_set'=>1,
        ];
        $this->OrderModel->itemDataUpdate($order->order_id,$update);
    }

    public function onSystemStart( $order_id, $order_data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        ///////////////////////////////////////////////////
        //COPYING STORE OWNERS TO ORDER OWNERS
        ///////////////////////////////////////////////////
        $this->OrderModel->itemUpdateOwners($order_id);
        // $update=(object)[
        //     'delivery_job'=>null
        // ];
        //$this->OrderModel->itemDataUpdate($order_id,$update);

        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $StoreModel->itemCacheClear();
        $order=$this->OrderModel->itemGet($order_id);
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
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'canceled']]
        ]];
        jobCreate($deliveryJob);

        $order=$this->OrderModel->itemGet($order_id,'basic');
        $StoreModel->itemCacheClear();
        $store=$StoreModel->itemGet($order->order_store_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer
        ];
        $messages[]=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>'-100',
            'template'=>'messages/order/on_customer_rejected_STORE_sms.php',
            'context'=>$context
        ];
        if( $order->order_store_admins ){
            $messages[]=(object)[
                'message_transport'=>'push,telegram',
                'message_reciever_id'=>$order->order_store_admins,
                'message_data'=>(object)[
                    'sound'=>'short.wav'
                ],
                'telegram_options'=>[
                    'buttons'=>[['',"onOrderOpen-{$order_id}",'⚡ Открыть заказ']]
                ],
                'template'=>'messages/order/on_customer_rejected_STORE_sms.php',
                'context'=>$context
            ];
            $messages[]=(object)[
                'message_transport'=>'email',
                //'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
                'message_reciever_email'=>$store->store_email,
                'message_subject'=>"Отмена заказа №{$order->order_id} от ".getenv('app.title'),
                'template'=>'messages/order/on_customer_rejected_STORE_email.php',
                'context'=>$context
            ];
        }
        if( $order->order_courier_admins ){
            $messages[]=(object)[
                'message_reciever_id'=>$order->order_courier_admins,
                'message_transport'=>'push,telegram',
                'message_data'=>(object)[
                    'sound'=>'short.wav'
                ],
                'template'=>'messages/order/on_customer_rejected_COUR_sms.php',
                'context'=>$context
            ];

            $courier_freeing_task=[
                'task_name'=>"free the courier",
                'task_programm'=>[
                    ['model'=>'UserModel','method'=>'systemUserLogin'],
                    ['model'=>'OrderGroupMemberModel','method'=>'leaveGroupByType','arguments'=>[$order_id,'delivery_search']],
                    ['model'=>'CourierModel','method'=>'itemUpdateStatus','arguments'=>[$order->order_courier_id,'ready']],
                    ['model'=>'UserModel','method'=>'systemUserLogout'],
                    ],
                'task_priority'=>'low'
            ];
            jobCreate($courier_freeing_task);
        }

        $notification_task=[
            'task_name'=>"customer_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$messages]]
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']],
            ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
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

        helper('phone_number');
        $order_data=$this->OrderModel->itemDataGet($order_id);

        $info_for_supplier=(object)json_decode($order_data->info_for_supplier??'[]');
        $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');

        $info_for_supplier->customer_name=$customer->user_name;
        $info_for_supplier->customer_phone='+'.clearPhone($customer->user_phone);
        $info_for_supplier->customer_email=$customer->user_email??null;

        $info_for_customer->supplier_name=$store->store_name;
        $info_for_customer->supplier_phone='+'.clearPhone($store->store_phone);
        $info_for_customer->supplier_email=$store->store_email;

        $update=(object)[
            'is_dispute_opened'=>1,
            'info_for_customer'=>json_encode($info_for_customer),
            'info_for_supplier'=>json_encode($info_for_supplier),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);


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
        $store_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>'-100,'.$store->owner_id.','.$store->owner_ally_ids,
            'message_data'=>(object)[
                'sound'=>'long.wav',
                'link'=>"/order/order-{$order_id}"
            ],
            'telegram_options'=>[
                'buttons'=>[['',"onOrderOpen-{$order_id}",'⚡ Открыть заказ']]
            ],
            'template'=>'messages/order/on_customer_disputed_STORE_sms.php',
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
            'message_transport'=>'push,telegram',
            'template'=>'messages/order/on_customer_disputed_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"customer_disputed Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$store_email,$store_sms,$cust_sms]]]
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    public function onCustomerFinish( $order_id ){
        $order=$this->OrderModel->itemGet($order_id,'basic');
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'push,telegram,email',
            'template'=>'messages/order/leave_comment.php',
            'context'=>[]
        ];
        $timeout_min=30;
        $next_start_time=time()+$timeout_min*60;
        $notification_task=[
            'task_name'=>"leave_comment",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$cust_sms]]]
                ],
            'task_next_start_time'=>$next_start_time,
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>0]);
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( $order_data->payment_by_cash??null ){
            return 'ok';
        }
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
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'canceled']]
        ]];
        jobCreate($deliveryJob);

        
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
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'message_data'=>(object)[
                'sound'=>'medium.wav'
            ],
            'template'=>'messages/order/on_supplier_rejected_CUST_sms.php',
            'context'=>$context
        ];
        $admin_sms=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>-100,
            'template'=>'messages/order/on_supplier_rejected_CUST_sms.php',
            'context'=>$context,
            'telegram_options'=>[
                'append_order_id'=>$order_id
            ]
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
        $cour_sms=(object)[
            'message_reciever_id'=>$order->order_courier_admins,
            'message_transport'=>'push,telegram',
            'message_data'=>(object)[
                'sound'=>'short.wav'
            ],
            'template'=>'messages/order/on_supplier_rejected_COUR_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_rejected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$store_email,$cust_sms,$cour_sms,$store_sms,$admin_sms]]]
                ],
            'task_priority'=>'low'
        ];


        $courier_freeing_n_store_blocking_task=[
            'task_name'=>"free the courier",
            'task_programm'=>[
                ['model'=>'UserModel','method'=>'systemUserLogin'],
                ['model'=>'OrderGroupMemberModel','method'=>'leaveGroupByType','arguments'=>[$order_id,'delivery_search']],
                ['model'=>'CourierModel','method'=>'itemUpdateStatus','arguments'=>[$order->order_courier_id,'ready']],
                ['model'=>'StoreModel','method'=>'itemDisable','arguments'=>[$order->order_store_id,1]],
                ['model'=>'UserModel','method'=>'systemUserLogout'],
                ]
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

        jobCreate($courier_freeing_n_store_blocking_task);
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
        return 'ok';
    }
    
    public function onSupplierCorrected($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        // if( !($order_data->store_correction_allow??0) ){
        //     return 'forbidden_bycustomer';
        // }
        helper('phone_number');

        $order=$this->OrderModel->itemGet($order_id,'all');

        $info_for_supplier=(object)json_decode($order_data->info_for_supplier??'[]');
        $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');

        $info_for_supplier->customer_name=$order->customer->user_name;
        $info_for_supplier->customer_phone='+'.clearPhone($order->customer->user_phone);

        $info_for_customer->supplier_name=$order->store->store_name;
        $info_for_customer->supplier_phone='+'.clearPhone($order->store->store_phone);

        if($order_data->payment_card_fixate_sum??0){
            $info_for_supplier->payment_card_fixate_sum=$order_data->payment_card_fixate_sum;
        }

        $update=(object)[
            'info_for_customer'=>json_encode($info_for_customer),
            'info_for_supplier'=>json_encode($info_for_supplier),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);
        $context=[
            'order'=>$order,
        ];
        $cust_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>"{$order->owner_id}",//-100
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
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $order_basic=$this->OrderModel->itemGet($order_id,'basic');
        if( $order_basic->order_sum_total<1 && $order_basic->order_sum_promo>=abs($order_basic->order_sum_total)+1 ){
            //if order_sum_total == -100 but order_sum_promo == 150 then make order_sum_promo = 49 so order_sum_total == order_sum_delivery + 1
            $order_update=(object)[
                'order_id'=>$order_id,
                'order_sum_promo'=>$order_basic->order_sum_product-1
            ];
            $this->OrderModel->fieldUpdateAllow('order_sum_promo');
            $this->OrderModel->itemUpdate($order_update);
            $order_basic->order_sum_total=$order_basic->order_sum_delivery+1;
        }
        if( $order_basic->order_sum_total<1 ){
            return 'order_sum_zero';
        }
        if( isset($order_data->payment_card_fixate_sum) && $order_basic->order_sum_total>$order_data->payment_card_fixate_sum ){
            return 'order_sum_exceeded';
        }
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
                ],
                'append_order_id'=>$order_id
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
        // $store_sms=(object)[
        //     'message_transport'=>'message',
        //     'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
        //     'template'=>'messages/order/on_supplier_overdue_STORE_sms.php',
        //     'context'=>$context
        // ];
        $notification_task=[
            'task_name'=>"supplier_overdue Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$customer_sms/**,$store_sms*/]]]
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

        if( $order_data->is_dispute_opened??0 ){
            jobCreate([
                'task_programm'=>[
                        ['method'=>'orderStageCreate','arguments'=>[$order_id,'delivery_finish']]
                ],
                'task_next_start_time'=>time()+1
            ]);
        }
        return 'ok';
    }
    
    
    //////////////////////////////////////////////////////////////////////////
    //DELIVERY HANDLERS
    //////////////////////////////////////////////////////////////////////////
    public function onDeliverySearch( $order_id ){
        return 'ok';
    }

    public function onDeliveryFound( $order_id, $courierData ){
        $order=$this->OrderModel->itemGet($order_id);
        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($courierData->order_courier_id);

        $courier_gain_mode=$courier->is_shift_open?'shift':'taxi';

        $job=(object)[
            'courier_id'=>$courierData->order_courier_id,
            'courier_name'=>$courier->courier_name??null,
            'courier_image_hash'=>$courier->images[0]->image_hash??null,
        ];
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'assigned', $job]]
        ]];
        jobCreate($deliveryJob);

        $order_data=$this->OrderModel->itemDataGet($order_id);
        $info_for_supplier=(object)json_decode($order_data->info_for_supplier??'[]');
        $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');

        helper('phone_number');
        $info_for_supplier->courier_name=$courier->courier_name;
        $info_for_supplier->courier_phone='+'.clearPhone($courier->user_phone);
        $info_for_supplier->courier_image_hash=$courier->images[0]->image_hash??'';
        $info_for_supplier->customer_name=$order->customer->user_name;
        $info_for_supplier->customer_phone='+'.clearPhone($order->customer->user_phone);

        $info_for_customer->courier_name=$courier->courier_name;
        $info_for_customer->courier_phone='+'.clearPhone($courier->user_phone);
        $info_for_customer->courier_image_hash=$courier->images[0]->image_hash??'';

        $update=(object)[
            'delivery_gain_mode'=>$courier_gain_mode,
            'delivery_rating_score'=>$courier->rating_score,
            'info_for_customer'=>json_encode($info_for_customer),
            'info_for_supplier'=>json_encode($info_for_supplier),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);


        $context=[
            'order'=>$order,
            'store'=>$order->store,
            'courier'=>$courier,
            'delivery_gain_mode'=>$courier_gain_mode??'',
        ];
        $admin_sms=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'telegram',
            'template'=>'messages/order/on_delivery_found_ADMIN_sms.php',
            'context'=>$context,
            'telegram_options'=>[
                'append_order_id'=>$order_id
            ]
        ];
        $notification_task=[
            'task_name'=>"delivery_found Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_sms]]]
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);

        if( $order->stage_current=='customer_start' || $order->stage_current=='system_schedule' ){
            return $this->OrderModel->itemStageCreate($order_id,'system_start',$order_data,'as_admin');
        }
        return 'ok';
    }
    
    public function onDeliveryStart( $order_id ){
        // $order=$this->OrderModel->itemGet($order_id);
        // if( !$order->images ){
        //     return 'photos_must_be_made';
        // }
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'started']]
        ]];
        jobCreate($deliveryJob);
        return 'ok';
    }
    public function onDeliveryForceStart( $order_id ){
        $courier_forcestart_task=[
            'task_name'=>"forcestart by courier",
            'task_programm'=>[
                ['model'=>'UserModel','method'=>'systemUserLogin'],
                ['model'=>'OrderModel','method'=>'itemStageCreate','arguments'=>[$order_id,'supplier_finish']],
                ['model'=>'UserModel','method'=>'systemUserLogout'],
                ]
        ];

        jobCreate($courier_forcestart_task);
        return 'ok';
    }
    public function onDeliveryRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'canceled']]
        ]];
        jobCreate($deliveryJob);

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
            'message_transport'=>'telegram',
            'template'=>'messages/order/on_delivery_rejected_ADMIN_sms.php',
            'context'=>$context,
            'telegram_options'=>[
                'append_order_id'=>$order_id
            ]
        ];
        $notification_task=[
            'task_name'=>"delivery_rejected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$admin_sms]]]
                ],
            'task_priority'=>'low'
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
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'canceled']]
        ]];
        jobCreate($deliveryJob);

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
            'message_transport'=>'telegram',
            'template'=>'messages/order/on_delivery_nocourier_ADMIN_sms.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_delivery_no_courier_CUST_sms.php',
            'context'=>$context
        ];
        $store_sms=(object)[
            'message_transport'=>'push,telegram',
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'template'=>'messages/order/on_customer_start_STORE_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"delivery_notfound Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$admin_sms,$cust_sms,$store_sms]]]
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

    private function itemDeliveryHeavyGet(){
        $PrefModel=model('PrefModel');
        $delivery_heavy_level=$PrefModel->itemGet('delivery_heavy_level','pref_value');
        if($delivery_heavy_level){
            $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level}",'pref_value');
            $delivery_heavy_bonus=$PrefModel->itemGet("delivery_heavy_bonus_{$delivery_heavy_level}",'pref_value');

            if( $delivery_heavy_cost && $delivery_heavy_bonus ){
                return (object)[
                    'cost'=>$delivery_heavy_cost,
                    'bonus'=>$delivery_heavy_bonus
                ];
            }
        }
        return (object)[
            'cost'=>0,
            'bonus'=>0
        ];
    }
    
    public function onDeliveryFinish( $order_id ){
        $order_basic=$this->OrderModel->itemGet($order_id,'basic');
        if($order_basic->order_courier_id){//if stage changed by admin skip this
            $CourierModel=model('CourierModel');
            $CourierModel->itemUpdateStatus($order_basic->order_courier_id,'ready');
        }

        ///////////////////////////////////////////////////
        //DELIVERY HEAVY BONUS CHECK (if bonus is bigger than on checkout then update)
        ///////////////////////////////////////////////////
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $delivery_heavy=$this->itemDeliveryHeavyGet();
        if($order_data->delivery_heavy_bonus<$delivery_heavy->bonus){
            $order_data_update=(object)[
                'delivery_heavy_bonus'=>$delivery_heavy->bonus
            ];
            $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        }
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'finished']]
        ]];
        jobCreate($deliveryJob);
        ///////////////////////////////////////////////////
        //CREATING STAGE RESET JOB
        ///////////////////////////////////////////////////
        $PrefModel=model('PrefModel');
        $timeout_min=(int)$PrefModel->itemGet('delivery_finish_timeout_min','pref_value',0);
        $next_start_time=time()+$timeout_min*60;
        $stage_reset_task=[
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['delivery_finish','customer_finish',$order_id]]
                ],
            'task_next_start_time'=>$next_start_time
        ];
        jobCreate($stage_reset_task);
        return 'ok';
    }
    /**
     * Courier pays in place of customer
     */
    public function onDeliveryDepositCompensate( $order_id ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( !($order_data->payment_by_cash??null) ){
            return 'deposit_inapplicable';
        }
        $paying_user_id=session()->get('user_id');
        $Acquirer=\Config\Services::acquirer();
        $order_all=$this->OrderModel->itemGet($order_id,'all');
        $result=$Acquirer->pay($order_all,$paying_user_id);
        if( $result!='ok' ){
            return "deposit_{$result}";
        }
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
            ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';        
    }
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
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
            ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
    }
    public function onAdminSanctionCourier( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>0,
            'sanction_courier_fee'=>1,
            'sanction_supplier_fee'=>0,
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
            ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
    }
    public function onAdminSanctionSupplier( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>0,
            'sanction_courier_fee'=>0,
            'sanction_supplier_fee'=>1,
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
            ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
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
        // if( $data['delay_sec']??0 ){
        //     sleep($data['delay_sec']);//DO AFTER DELAY
        // }
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