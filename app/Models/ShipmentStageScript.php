<?php
namespace App\Models;

class ShipmentStageScript{
    public $OrderModel;
    public $stageMap=[
        ''=>[
            'customer_cart'=>               ['Черновик'],
            ],
        'customer_deleted'=>                [],
        'customer_cart'=>[
            'customer_action_checkout'=>    ['Перейти к оформлению'],
            'customer_deleted'=>            ['Удалить','danger','clear'],
            'customer_confirmed'=>          [],
            ],
        'customer_rejected'=>[
            'system_reckon'=>               []
            ],
        'customer_confirmed'=>[
            'customer_action_checkout'=>    ['Перейти к оформлению'],
            'customer_cart'=>               ['Изменить','light'],
            'customer_start'=>              [],
            'system_await'=>                [],
            'system_schedule'=>             [],
            ],
        'customer_start'=>[
            'delivery_start'=>              ['Начать доставку'],
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear'],
            ],
        
        'system_await'=>[
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'customer_start'=>              [],
            'admin_action_customer_start'=> ['Запустить заказ','medium','clear'],
            ],
        'system_schedule'=>[
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'customer_start'=>              [],
            'admin_action_customer_start'=> ['Запустить заказ','medium','clear'],
            ],


        'delivery_found'=>[
            'delivery_start'=>              [],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        'delivery_start'=>[
            'delivery_finish'=>             ['Завершить доставку','success'],
            'delivery_action_take_photo'=>  ['Сфотографировать','medium','clear'],
            'delivery_action_rejected'=>    ['Отказаться от доставки','danger','clear'],
            'delivery_rejected'=>           [],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        'delivery_rejected'=>[
            'admin_supervise'=>             ['Решить спор','danger'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        'delivery_finish'=>[
            'system_reckon'=>               [],
            ],


        'admin_supervise'=>[
            'system_finish'=>               ['Проблема решена','success'],
            'admin_sanction_customer'=>     ['Оштрафовать клиента','danger'],
            'admin_sanction_courier'=>      ['Оштрафовать курьера','danger'],
            ],
        'admin_sanction_customer'=>[
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
    public function onAdminSystemStart( $order_id ){
        $order_data_update=(object)[
            'plan_mode'=>'start'
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
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
    // public function onAdminSanctionSupplier( $order_id ){
    //     $order_data_update=(object)[
    //         'sanction_customer_fee'=>0,
    //         'sanction_courier_fee'=>0,
    //         'sanction_supplier_fee'=>1,
    //     ];
    //     $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
    //     return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    // }
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
        $this->OrderModel->transComplete();
        $OrderTransactionModel=model('OrderTransactionModel');
        $result=$OrderTransactionModel->orderFinalize($order_id)?'ok':'fail';
        $this->OrderModel->transBegin();
        return $result;

    }
    //////////////////////////////////////////////////////////////////////////
    //CUSTOMER HANDLERS
    //////////////////////////////////////////////////////////////////////////
    public function onCustomerDeleted($order_id){
        return $this->OrderModel->itemDelete($order_id);
    }
    
    public function onCustomerCart($order_id){
        return 'ok';
    }

    public function onCustomerConfirmed( $order_id ){
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
            'payment_card_fixate_sum'=>$acquirer_data->total,
        ];
        pl($order_data_update);
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->systemBegin($order_id);
    }

    public function onCustomerPayedCredit( $order_id, $data ){
        return $this->systemBegin($order_id);
    }

    private function systemBegin($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( $order_data->start_plan_mode=='scheduled' ){
            return $this->OrderModel->itemStageCreate($order_id, 'system_schedule');
        }
        if( $order_data->start_plan_mode=='awaited' ){
            return $this->OrderModel->itemStageCreate($order_id, 'system_await');
        }
        if( $order_data->start_plan_mode=='inited' ){
            return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
        }
        return 'no_plan_mode';
    }

    public function onSystemAwait( $order_id, $data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        /**
         * only prepayed orders are allowed
         */
        if( empty($order_data->payment_by_credit_store) && empty($order_data->payment_card_fixate_sum) ){
            return 'payment_is_missing';
        }
        /**
         * This is shipment order so only delivery_by_courier allowed
         * For marketplace orders no delivery should be allowed
         */
        if( empty($order_data->delivery_by_courier) ){
            return 'delivery_is_missing';
        }
        ///////////////////////////////////////////////////
        //ADDING TO JOB LIST
        ///////////////////////////////////////////////////
        $job=(object)[
            'start_plan'=>$order_data->start_plan??0,
        ];
        model('DeliveryJobModel')->itemStageSet( $order_id, 'awaited', $job );

        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $order=$this->OrderModel->itemGet($order_id);
        ///////////////////////////////////////////////////
        //CREATING STAGE NOTIFICATIONS
        ///////////////////////////////////////////////////
        $store=$StoreModel->itemGet($order->order_store_id);
        $customer=$UserModel->itemGet($order->owner_id);
        $context=[
            'customer_start_time'=>$order_data->start_plan??0,
            'order'=>$order,
            'order_data'=>$order_data,
            'store'=>$store,
            'customer'=>$customer
        ];
        $notifications=[];
        $notifications[]=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>-100,
            'template'=>'messages/order/on_ship_customer_start_ADMIN_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"shipping system_await Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    public function onSystemSchedule( $order_id, $data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        /**
         * only prepayed orders are allowed
         */
        if( empty($order_data->payment_by_credit_store) && empty($order_data->payment_card_fixate_sum) ){
            return 'payment_is_missing';
        }
        /**
         * This is shipment order so only delivery_by_courier allowed
         * For marketplace orders no delivery should be allowed
         */
        if( empty($order_data->delivery_by_courier) ){
            return 'delivery_is_missing';
        }
        ///////////////////////////////////////////////////
        //ADDING TO JOB LIST
        ///////////////////////////////////////////////////
        $job=(object)[
            'job_name'=>'Вызов курьера',
            'start_plan'=>$order_data->start_plan??0,
            'start_prep_time'=>$order_data->finish_arrival,
            'finish_arrival'=>$order_data->finish_arrival,
            'start_longitude'=>$order_data->location_start->location_longitude,
            'start_latitude'=>$order_data->location_start->location_latitude,
            'finish_longitude'=>$order_data->location_finish->location_latitude,
            'finish_latitude'=>$order_data->location_finish->location_latitude,
        ];
        $DeliveryJobModel=model('DeliveryJobModel');
        $stage=$DeliveryJobModel->itemStageSet( $order_id, 'scheduled', $job );

        if( $stage!=='scheduled' ){
            return 'ok';
        }

        ///////////////////////////////////////////////////
        //CREATING STAGE NOTIFICATIONS
        ///////////////////////////////////////////////////
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $order=$this->OrderModel->itemGet($order_id);
        $store=$StoreModel->itemGet($order->order_store_id);
        $customer=$UserModel->itemGet($order->owner_id);
        $context=[
            'customer_start_time'=>$order_data->start_plan??0,
            'order'=>$order,
            'order_data'=>$order_data,
            'store'=>$store,
            'customer'=>$customer
        ];
        $notifications=[];
        $notifications[]=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>-100,
            'template'=>'messages/order/on_ship_customer_start_ADMIN_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"shipping system_await Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }
    
    public function onCustomerStart( $order_id, $data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( empty($order_data->payment_by_credit_store) && empty($order_data->payment_card_fixate_sum) ){//only prepayed orders are allowed
            return 'payment_is_missing';
        }
        if( !empty($order_data->delivery_by_courier) && !sudo() ){//initiation only from DeliveryJobManager
            if( empty($data->is_delivery_job_inited) ){
                return 'customer_start_premature';
            }
        }
        ///////////////////////////////////////////////////
        //STARTING TO COURIER SEEKING
        ///////////////////////////////////////////////////
        model('DeliveryJobModel')->itemStageSet( $order_id, 'inited' );

        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $order=$this->OrderModel->itemGet($order_id);
        ////////////////////////////////////////////////
        //LOCATION FIXATION SECTION
        ////////////////////////////////////////////////
        $LocationModel=model('LocationModel');
        $supplierLocation=$LocationModel->itemGet($order->order_start_location_id);
        $customerLocation=$LocationModel->itemGet($order->order_finish_location_id);
        try{
            $order_update=[
                'order_start_location_id'=>$supplierLocation->location_id,
                'order_finish_location_id'=>$customerLocation->location_id
            ];
            $this->OrderModel->update($order_id,$order_update);
        } catch (\Exception $e){
            return 'address_not_set';
        }
        helper('phone_number');
        $info_for_courier=(object)json_decode($order_data->info_for_courier??'[]');

        $info_for_courier->customer_location_address=$customerLocation->location_address??'';
        $info_for_courier->customer_location_comment=$customerLocation->location_comment??'';
        $info_for_courier->customer_location_latitude=$customerLocation->location_latitude??'';
        $info_for_courier->customer_location_longitude=$customerLocation->location_longitude??'';
        $info_for_courier->customer_phone='+'.clearPhone($order->customer->user_phone);
        $info_for_courier->customer_name=$order->customer->user_name;
        $info_for_courier->customer_email=$order->customer->user_email;

        // $info_for_courier->supplier_location_address=$supplierLocation->location_address??'';
        // $info_for_courier->supplier_location_comment=$supplierLocation->location_comment??'';
        // $info_for_courier->supplier_location_latitude=$supplierLocation->location_latitude??'';
        // $info_for_courier->supplier_location_longitude=$supplierLocation->location_longitude??'';
        // $info_for_courier->supplier_phone='+'.clearPhone($order->store->);
        // $info_for_courier->supplier_name=$order->store->store_name??'';
        // $info_for_courier->supplier_email=$order->store->store_email??'';

        $update=(object)[
            'info_for_courier'=>json_encode($info_for_courier),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);
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
        //CREATING STAGE NOTIFICATIONS
        ///////////////////////////////////////////////////
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
            'template'=>'messages/order/on_ship_customer_start_ADMIN_sms.php',
            'context'=>$context
        ];
        $notifications[]=(object)[
            'message_transport'=>'telegram,push',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_customer_start_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"shipping customer_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    public function onCustomerRejected( $order_id ){
        $UserModel=model('UserModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);
        model('DeliveryJobModel')->itemStageSet( $order_id, 'canceled' );

        $order=$this->OrderModel->itemGet($order_id,'basic');
        $customer=$UserModel->itemGet($order->owner_id,'basic');
        $context=[
            'order'=>$order,
            'customer'=>$customer
        ];
        $cour_sms=(object)[
            'message_reciever_id'=>$order->order_courier_admins,
            'message_transport'=>'telegram,push',
            'message_data'=>(object)[
                'sound'=>'medium.wav'
            ],
            'template'=>'messages/order/on_customer_rejected_COUR_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"customer_start Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$cour_sms]]]
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
        
    public function onCustomerFinish( $order_id ){
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
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
        helper('phone_number');

        $order=$this->OrderModel->itemGet($order_id);
        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($order->order_courier_id);

        helper('phone_number');
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $info_for_supplier=(object)json_decode($order_data->info_for_supplier??'[]');
        $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');

        $info_for_supplier->courier_name=$courier->courier_name;
        $info_for_supplier->courier_phone='+'.clearPhone($courier->user_phone);
        $info_for_supplier->courier_image_hash=$courier->images[0]->image_hash??'';

        $info_for_customer->courier_name=$courier->courier_name;
        $info_for_customer->courier_phone='+'.clearPhone($courier->user_phone);
        $info_for_customer->courier_image_hash=$courier->images[0]->image_hash??'';

        $update=(object)[
            'info_for_customer'=>json_encode($info_for_customer),
            'info_for_supplier'=>json_encode($info_for_supplier),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);

        model('DeliveryJobModel')->itemStageSet( $order_id, 'assigned', (object)['courier_id'=>$order->order_courier_id]);

        $context=[
            'order'=>$order,
        ];
        $admin_sms=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'telegram,push',
            'template'=>'messages/order/on_delivery_found_ADMIN_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"delivery_found Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return 'ok';
    }
    
    public function onDeliveryStart( $order_id ){
        model('DeliveryJobModel')->itemStageSet( $order_id, 'started' );
        return 'ok';
    }

    public function onDeliveryRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);
        model('DeliveryJobModel')->itemStageSet( $order_id, 'canceled' );

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
        model('DeliveryJobModel')->itemStageSet( $order_id, 'canceled' );

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
        model('DeliveryJobModel')->itemStageSet( $order_id, 'finished' );

        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
}