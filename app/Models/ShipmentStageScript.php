<?php
namespace App\Models;

class ShipmentStageScript{
    public $OrderModel;
    public $stageMap=[
        'customer_deleted'=>                [],
        ''=>[
            'customer_draft'=>              ['Черновик'],
            ],
        'customer_draft'=>[
            'customer_action_checkout'=>    ['Перейти к оформлению'],
            'customer_deleted'=>            ['Удалить','danger','clear'],
            'customer_await'=>              [],
            'customer_schedule'=>           [],
            'customer_start'=>              [],
            ],
        'customer_await'=>                  [
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'customer_start'=>              [],
            'admin_action_customer_start'=> ['Запустить заказ','medium','clear'],
            ],
        'customer_schedule'=>                  [
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'customer_start'=>              [],
            'admin_action_customer_start'=> ['Запустить заказ','medium','clear'],
            ],
        'customer_rejected'=>[
            'system_reckon'=>               []
            ],
        'customer_start'=>[
            'delivery_start'=>              ['Начать доставку'],
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear'],
            // 'delivery_no_courier'=>         [],
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
            'supplier_reclaimed'=>          ['Принять возврат заказа'],
            'admin_supervise'=>             ['Решить спор','danger'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
            ],
        // 'delivery_no_courier'=>[
        //     'system_reckon'=>               [],
        //     'admin_action_courier_assign'=> ['Назначить курьера','medium','clear']
        // ],
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
    public function onAdminCustomerStart( $order_id ){
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
        return $this->customerBegin($order_id);
    }

    private function customerBegin($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( $order_data->plan_mode=='schedule' ){
            $ok=$this->OrderModel->itemStageCreate($order_id, 'customer_schedule');
            $customer_start_time=$order_data->plan_delivery_start-$order_data->time_start_arrival;
            $customer_start_task=[
                'task_name'=>"customer_start On scheduled #$order_id",
                'task_programm'=>[
                        ['method'=>'orderResetStage','arguments'=>['customer_schedule','customer_start',$order_id]]
                    ],
                'task_next_start_time'=>$customer_start_time
            ];
            jobCreate($customer_start_task);
            return $ok;
        }
        if( $order_data->plan_mode=='await' ){
            $ok=$this->OrderModel->itemStageCreate($order_id, 'customer_await');
            // $customer_start_time=$order_data->plan_delivery_start-$order_data->time_start_arrival;
            // $customer_start_task=[
            //     'task_name'=>"customer_start In queue #$order_id",
            //     'task_programm'=>[
            //             ['method'=>'orderResetStage','arguments'=>['customer_await','customer_start',$order_id]]
            //         ],
            //     'task_next_start_time'=>$customer_start_time
            // ];
            // jobCreate($customer_start_task);
            return $ok;
        }
        if( $order_data->plan_mode=='nodelay' ){
            return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
        }
        pl($order_data);
        return 'no_plan_mode';
    }

    public function onCustomerDeleted($order_id){
        return $this->OrderModel->itemDelete($order_id);
    }
    
    public function onCustomerDraft($order_id){
        return 'ok';
    }
    
    public function onCustomerAwait( $order_id, $data ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( !empty($order_data->payment_by_card) && empty($order_data->payment_card_fixate_sum) ){
            return 'payment_by_card_missing';
        }
        return 'ok';
    }
    
    public function onCustomerStart( $order_id, $data ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( !empty($order_data->payment_by_card) && empty($order_data->payment_card_fixate_sum) ){
            return 'payment_by_card_missing';
        }
        if( $order_data->plan_mode=='schedule' && !sudo() ){
            $customer_start_time=$order_data->plan_delivery_start-$order_data->time_start_arrival;
            if($customer_start_time>time()){
                return 'customer_start_premature';
            }
        }
        if( $order_data->plan_mode=='await' && !sudo() ){
            return 'customer_start_premature';
        }
        $order=$this->OrderModel->itemGet($order_id);
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
        if($order->order_store_id){
            $notifications[]=(object)[
                'message_transport'=>'telegram,push',
                'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
                'telegram_options'=>[
                    'buttons'=>[['',"onOrderOpen-{$order_id}",'⚡ Открыть заказ']]
                ],
                'template'=>'messages/order/on_shipping_customer_start_STORE_sms.php',
                'context'=>$context
            ];
        }
        $notifications[]=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>-100,
            'template'=>'messages/order/on_shipping_customer_start_ADMIN_sms.php',
            'context'=>$context
        ];
        // $notifications[]=(object)[
        //     'message_transport'=>'email',
        //     'message_reciever_id'=>-100,
        //     'message_subject'=>"Заказ №{$order->order_id} от ".getenv('app.title'),
        //     'template'=>'messages/order/on_shipping_customer_start_ADMIN_email.php',
        //     'context'=>$context
        // ];
        $notifications[]=(object)[
            'message_transport'=>'telegram,push',
            'message_reciever_id'=>$order->owner_id,
            'template'=>'messages/order/on_shipping_customer_start_CUST_sms.php',
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
        $LocationModel=model("LocationModel");
        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($order->order_courier_id);
        $supplierLocation=$LocationModel->itemGet($order->order_start_location_id);
        $recieverLocation=$LocationModel->itemGet($order->order_finish_location_id);
        $update=(object)[
            'info_for_courier'=>json_encode([
                'supplier_location_address'=>$supplierLocation->location_address??'',
                'supplier_location_comment'=>$supplierLocation->location_comment??'',
                'supplier_location_latitude'=>$supplierLocation->location_latitude??'',
                'supplier_location_longitude'=>$supplierLocation->location_longitude??'',

                'reciever_location_address'=>$recieverLocation->location_address??'',
                'reciever_location_comment'=>$recieverLocation->location_comment??'',
                'reciever_location_latitude'=>$recieverLocation->location_latitude??'',
                'reciever_location_longitude'=>$recieverLocation->location_longitude??'',

                'customer_phone'=>'+'.clearPhone($order->customer->user_phone),
                'customer_name'=>$order->customer->user_name,
                'customer_email'=>$order->customer->user_email,

                // 'supplier_name'=>$order->store->store_name,
                // 'supplier_phone'=>'+'.clearPhone($order->store->store_phone),
            ]),
            'info_for_customer'=>json_encode([
                'courier_name'=>$courier->courier_name,
                'courier_phone'=>'+'.clearPhone($courier->user_phone),
                'courier_image_hash'=>$courier->images[0]->image_hash??''
            ]),
            'info_for_supplier'=>json_encode([
                'courier_name'=>$courier->courier_name,
                'courier_phone'=>'+'.clearPhone($courier->user_phone),
                'courier_image_hash'=>$courier->images[0]->image_hash??''
            ]),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);

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
        //$CourierModel=model('CourierModel');
        // if( !$CourierModel->isBusy() ){
        //     return 'wrong_courier_status';
        // }
        // $order=$this->OrderModel->itemGet($order_id);
        // if( !$order->images ){
        //     return 'photos_must_be_made';
        // }
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
        return $this->OrderModel->itemStageCreate($order_id, 'system_reckon');
    }
}