<?php
namespace App\Models;

class OrderStageShipmentScript{
    public $OrderModel;
    public $stageMap=[
        ''=>[
            'customer_cart'=>               ['Черновик'],
            ],
        'customer_deleted'=>                [],
        'customer_cart'=>[
            'customer_action_confirm'=>    ['Перейти к оформлению'],
            'customer_deleted'=>            ['Удалить','danger','clear'],
            'customer_confirmed'=>          [],
            ],
        'customer_rejected'=>[
            'system_reckon'=>               []
            ],
        'customer_confirmed'=>[
            'customer_action_confirm'=>     ['Перейти к оформлению'],
            'customer_cart'=>               ['Изменить','light'],
            'customer_action_take_photo'=>  ['Сфотографировать','medium','clear'],
            'customer_start'=>              [],
            ],
        'customer_start'=>[
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'customer_action_take_photo'=>  ['Сфотографировать','medium','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear'],
            'system_schedule'=>             [],
            'delivery_pickup'=>             [],
            ],
        
        
        'delivery_pickup'=>[
            'delivery_start'=>              ['Начать доставку'],
            'delivery_action_rejected'=>    ['Отказаться от доставки','danger','clear'],
            'delivery_rejected'=>           [],
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear'],
            ],
        'system_schedule'=>[
            'customer_rejected'=>           ['Отменить заказ','danger','clear'],
            'system_await'=>                [],
            'customer_start'=>              [],
            'admin_action_customer_start'=> ['Запустить заказ','medium','clear'],
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
            'admin_action_courier_assign'=> ['Назначить курьера','medium','clear'],
            'delivery_action_take_photo'=>  ['Сфотографировать','light'],
            ],
        'delivery_finish'=>[
            'system_reckon'=>               [],
            ],


        // 'admin_customer_start'=>[
        //     'customer_start'=>              []
        // ],
        'admin_supervise'=>[
            'delivery_finish'=>             ['Посылка доставлена','success'],//must set is_canceled to 0
            'admin_sanction_customer'=>     ['Оштрафовать клиента','danger'],
            'admin_sanction_courier'=>      ['Возврат ден. клиенту','danger'],
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
        $order_data_update=(object)[
            'order_is_canceled'=>0,//money should not be returned
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return 'ok';
    }
    public function onAdminCustomerStart( $order_id ){
        $order_data_update=(object)[
            'plan_mode'=>'inited'
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
    }
    public function onAdminSanctionCustomer( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>1,
            'sanction_courier_fee'=>0,
            'sanction_supplier_fee'=>0,
            'order_is_canceled'=>0,//money should not be returned
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
                ]
        ]);
        return 'ok';
    }
    public function onAdminSanctionCourier( $order_id ){
        $order_data_update=(object)[
            'sanction_customer_fee'=>0,
            'sanction_courier_fee'=>1,
            'sanction_supplier_fee'=>0,
            'order_is_canceled'=>1,//money should be returned
        ];
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
                ]
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
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon', ['delay_sec'=>1]]]
                ]
        ]);
        return 'ok';
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
    //SYSTEM HANDLERS ONLY UNDER ADMIN LEVEL USER
    ////////////////////////////////////////////////
    public function onSystemReckon( $order_id, $data ){
        if( $data['delay_sec']??0 ){
            sleep($data['delay_sec']);//DO AFTER DELAY
        }
        $result=$this->OrderModel->itemStageCreate($order_id, 'system_finish');
        if( $result!='ok' ){
            $retry_delay_min=7;
            jobCreate([
                'task_programm'=>[
                        ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_finish', $data]]
                ],
                'task_next_start_time'=>time()+$retry_delay_min*60
            ]);
        }
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

    public function onCustomerConfirmed( $order_id, $data ){
        if( $data ){
            /**
             * Here we are fixating data from shipmentDraft at first sync
             * 1)Allowing save delivery sum estimation from client!
             * But final delivery_sum will be written at checkoutDataSet
             * 2)Fixing selected location data
             */
            $this->OrderModel->fieldUpdateAllow('order_sum_delivery');
            $this->OrderModel->itemUpdate($data);
            $LocationModel=model('LocationModel');
            $order_data=(object)[];
            $order_data->location_start=$LocationModel->itemGet($data->order_start_location_id,'all');
            $order_data->location_finish=$LocationModel->itemGet($data->order_finish_location_id,'all');
            $this->OrderModel->itemDataCreate($order_id,$order_data);
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
            'payment_card_fixate_sum'=>$acquirer_data->total,
        ];
        if( $acquirer_data->payment_card_acquirer??null ){
            //updating aquirer handler to last used
            $order_data_update->payment_card_acquirer=$acquirer_data->payment_card_acquirer;
        }
        $this->OrderModel->itemDataUpdate($order_id,$order_data_update);
        return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
        //return $this->systemBegin($order_id);
    }

    public function onCustomerPayedCredit( $order_id, $data ){
        return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
        //return $this->systemBegin($order_id);
    }

    public function onCustomerStart($order_id){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        if( empty($order_data->payment_by_credit_store) && empty($order_data->payment_card_fixate_sum) ){//only prepayed orders are allowed
            return 'payment_is_missing';
        }
        ///////////////////////////////////////////////////
        //JUMPING TO SCHEDULED
        ///////////////////////////////////////////////////
        if( $order_data->start_plan_mode=='scheduled' && isset($order_data->init_plan_scheduled) && $order_data->init_plan_scheduled>time() ){
            return $this->OrderModel->itemStageCreate($order_id,'system_schedule',$order_data,'as_admin');
        }
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $order=$this->OrderModel->itemGet($order_id);
        ///////////////////////////////////////////////////
        //MARK AS SEARCHING FOR COURIER
        ///////////////////////////////////////////////////
        if( empty($order_data->delivery_job) ){
            return 'delivery_is_missing';
        }
        $this->OrderModel->itemStageAdd($order_id, 'delivery_search');
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'awaited', $order_data->delivery_job]]
        ]];
        jobCreate($deliveryJob);
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
        $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');
        $info_for_customer->tariff_info=view('order/customer_shipment_rules_info.php');

        $info_for_courier=(object)json_decode($order_data->info_for_courier??'[]');
        $info_for_courier->tariff_info=view('order/delivery_shipment_rules_info.php');

        $info_for_courier->customer_location_address=$customerLocation->location_address??'';
        $info_for_courier->customer_location_comment=$customerLocation->location_comment??'';
        $info_for_courier->customer_location_latitude=$customerLocation->location_latitude??'';
        $info_for_courier->customer_location_longitude=$customerLocation->location_longitude??'';
        $info_for_courier->customer_phone='+'.clearPhone($order->customer->user_phone);
        $info_for_courier->customer_name=$order->customer->user_name;
        $info_for_courier->customer_email=$order->customer->user_email;

        $update=(object)[
            'info_for_courier'=>json_encode($info_for_courier),
            'info_for_customer'=>json_encode($info_for_customer),
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);
        ///////////////////////////////////////////////////
        //COPYING STORE OWNERS TO ORDER OWNERS
        ///////////////////////////////////////////////////
        $this->OrderModel->itemUpdateOwners($order_id);
        /**
         * Starting search of courier only if needed and not assigned already
         */
        if( !empty($order_data->delivery_by_courier) && empty($order->order_courier_id) ){
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
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
        return 'ok';
    }

    // public function onSystemAwait( $order_id, $data ){
    //     $order_data=$this->OrderModel->itemDataGet($order_id);
    //     if( empty($order_data->payment_by_credit_store) && empty($order_data->payment_card_fixate_sum) ){
    //         return 'payment_is_missing';
    //     }
    //     /**
    //      * This is shipment order so only delivery_by_courier allowed
    //      * For marketplace orders delivery should not be checked
    //      */
    //     if( empty($order_data->delivery_job) ){
    //         return 'delivery_is_missing';
    //     }
    //     $this->OrderModel->itemStageAdd($order_id, 'delivery_search');
    //     $deliveryJob=['task_programm'=>[
    //         ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'awaited', $order_data->delivery_job]]
    //     ]];
    //     jobCreate($deliveryJob);

    //     ///////////////////////////////////////////////////
    //     //CREATING STAGE NOTIFICATIONS
    //     ///////////////////////////////////////////////////
    //     $UserModel=model('UserModel');
    //     $StoreModel=model('StoreModel');
    //     $order=$this->OrderModel->itemGet($order_id);
    //     $store=$StoreModel->itemGet($order->order_store_id);
    //     $customer=$UserModel->itemGet($order->owner_id);
    //     $context=[
    //         'order'=>$order,
    //         'order_data'=>$order_data,
    //         'store'=>$store,
    //         'customer'=>$customer
    //     ];
    //     $notifications=[];
    //     $notifications[]=(object)[
    //         'message_transport'=>'telegram',
    //         'message_reciever_id'=>-100,
    //         'template'=>'messages/order/on_ship_system_await_ADMIN_sms.php',
    //         'context'=>$context,
    //         'telegram_options'=>[
    //             'disable_notification'=>1,
    //         ],
    //     ];
    //     $notification_task=[
    //         'task_name'=>"shipping system_await Notify #$order_id",
    //         'task_programm'=>[
    //                 ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
    //         ],
    //         'task_priority'=>'low'
    //     ];
    //     jobCreate($notification_task);
    //     return 'ok';
    // }

    public function onSystemSchedule( $order_id ){
        $order_data=$this->OrderModel->itemDataGet($order_id);
        $this->OrderModel->itemDataUpdate($order_id,(object)['start_plan_mode'=>'awaited']);
        
        $DeliveryJobModel=model('DeliveryJobModel');
        $start_offset=$DeliveryJobModel->avgStartArrival;//in sec
        $init_plan=$order_data->start_plan-$start_offset;
        if( $init_plan<time() ){//should be placed in awaited queue already
            //it will be done automatically
            return $this->OrderModel->itemStageCreate($order_id, 'customer_start');
        }

        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'scheduled', $order_data->delivery_job]]
        ]];
        jobCreate($deliveryJob);

        $set_on_queue_task=[
            'task_name'=>"Delivery job On schedule #$order_id",
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['system_schedule','customer_start',$order_id]]
                ],
            'task_next_start_time'=>$init_plan,
            'task_priority'=>'low'
        ];
        jobCreate($set_on_queue_task);
        return 'ok';
    }
    
    // public function onCustomerStart2222( $order_id, $data ){
    //     $order_data=$this->OrderModel->itemDataGet($order_id);
    //     if( empty($order_data->payment_by_credit_store) && empty($order_data->payment_card_fixate_sum) ){//only prepayed orders are allowed
    //         return 'payment_is_missing';
    //     }

    //     if( $order_data->start_plan_mode!=='inited' ){
    //         return 'customer_start_premature';
    //     }
    //     $UserModel=model('UserModel');
    //     $StoreModel=model('StoreModel');
    //     $order=$this->OrderModel->itemGet($order_id);
    //     ////////////////////////////////////////////////
    //     //LOCATION FIXATION SECTION
    //     ////////////////////////////////////////////////
    //     $LocationModel=model('LocationModel');
    //     $supplierLocation=$LocationModel->itemGet($order->order_start_location_id);
    //     $customerLocation=$LocationModel->itemGet($order->order_finish_location_id);
    //     try{
    //         $order_update=[
    //             'order_start_location_id'=>$supplierLocation->location_id,
    //             'order_finish_location_id'=>$customerLocation->location_id
    //         ];
    //         $this->OrderModel->update($order_id,$order_update);
    //     } catch (\Exception $e){
    //         return 'address_not_set';
    //     }
    //     helper('phone_number');
    //     $info_for_customer=(object)json_decode($order_data->info_for_customer??'[]');
    //     $info_for_customer->tariff_info=view('order/customer_shipment_rules_info.php');

    //     $info_for_courier=(object)json_decode($order_data->info_for_courier??'[]');
    //     $info_for_courier->tariff_info=view('order/delivery_shipment_rules_info.php');

    //     $info_for_courier->customer_location_address=$customerLocation->location_address??'';
    //     $info_for_courier->customer_location_comment=$customerLocation->location_comment??'';
    //     $info_for_courier->customer_location_latitude=$customerLocation->location_latitude??'';
    //     $info_for_courier->customer_location_longitude=$customerLocation->location_longitude??'';
    //     $info_for_courier->customer_phone='+'.clearPhone($order->customer->user_phone);
    //     $info_for_courier->customer_name=$order->customer->user_name;
    //     $info_for_courier->customer_email=$order->customer->user_email;

    //     $update=(object)[
    //         'info_for_courier'=>json_encode($info_for_courier),
    //         'info_for_customer'=>json_encode($info_for_customer),
    //     ];
    //     $this->OrderModel->itemDataUpdate($order_id,$update);
    //     ///////////////////////////////////////////////////
    //     //COPYING STORE OWNERS TO ORDER OWNERS
    //     ///////////////////////////////////////////////////
    //     $this->OrderModel->itemUpdateOwners($order_id);
    //     /**
    //      * Starting search of courier only if needed and not assigned already
    //      */
    //     if( !empty($order_data->delivery_by_courier) && empty($order->order_courier_id) ){
    //         $this->OrderModel->itemStageAdd($order_id, 'delivery_search');
    //     }
    //     ///////////////////////////////////////////////////
    //     //CREATING STAGE NOTIFICATIONS
    //     ///////////////////////////////////////////////////
    //     $store=$StoreModel->itemGet($order->order_store_id);
    //     $customer=$UserModel->itemGet($order->owner_id);
    //     $context=[
    //         'order'=>$order,
    //         'order_data'=>$order_data,
    //         'store'=>$store,
    //         'customer'=>$customer
    //     ];
    //     $notifications=[];
    //     $notifications[]=(object)[
    //         'message_transport'=>'telegram',
    //         'message_reciever_id'=>-100,
    //         'template'=>'messages/order/on_ship_customer_start_ADMIN_sms.php',
    //         'context'=>$context
    //     ];
    //     $notifications[]=(object)[
    //         'message_transport'=>'telegram,push',
    //         'message_reciever_id'=>$order->owner_id,
    //         'template'=>'messages/order/on_customer_start_CUST_sms.php',
    //         'context'=>$context
    //     ];
    //     $notification_task=[
    //         'task_name'=>"shipping customer_start Notify #$order_id",
    //         'task_programm'=>[
    //                 ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$notifications]]
    //             ],
    //         'task_priority'=>'low'
    //     ];
    //     jobCreate($notification_task);
    //     return 'ok';
    // }

    public function onCustomerRejected( $order_id ){
        $UserModel=model('UserModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[$order_id, 'canceled']]
        ]];
        jobCreate($deliveryJob);

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
                ],
            'task_priority'=>'low'
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
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
                ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
    }
        
    public function onCustomerFinish( $order_id ){
        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
                ],
            'task_next_start_time'=>time()+1
        ]);
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
            'start_plan_mode'=>'inited'
        ];
        $this->OrderModel->itemDataUpdate($order_id,$update);

        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[ $order_id, 'assigned', (object)['courier_id'=>$order->order_courier_id]]]
        ]];
        jobCreate($deliveryJob);

        $context=[
            'order'=>$order,
            'store'=>$order->store,
            'courier'=>$courier,
        ];
        $admin_sms=(object)[
            'message_reciever_id'=>'-100',
            'message_transport'=>'telegram',
            'template'=>'messages/order/on_delivery_found_ADMIN_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"delivery_found Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_sms]]]
                ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
        return $this->OrderModel->itemStageCreate($order_id,'delivery_pickup');
    }

    public function onDeliveryPickup(){
        return 'ok';
    }
    
    public function onDeliveryStart( $order_id ){
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[ $order_id, 'started']]
        ]];
        jobCreate($deliveryJob);
        return 'ok';
    }

    public function onDeliveryRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');

        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>1]);
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[ $order_id, 'canceled']]
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
            'message_transport'=>'message',
            'template'=>'messages/order/on_delivery_rejected_ADMIN_sms.php',
            'context'=>$context
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
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[ $order_id, 'canceled']]
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

    public function onDeliveryFinish( $order_id ){
        $this->OrderModel->itemDataUpdate($order_id,(object)['order_is_canceled'=>0]);
        $deliveryJob=['task_programm'=>[
            ['model'=>'DeliveryJobModel','method'=>'itemStageSet','arguments'=>[ $order_id, 'finished']]
        ]];
        jobCreate($deliveryJob);

        jobCreate([
            'task_programm'=>[
                    ['method'=>'orderStageCreate','arguments'=>[$order_id,'system_reckon']]
                ],
            'task_next_start_time'=>time()+1
        ]);
        return 'ok';
    }
}