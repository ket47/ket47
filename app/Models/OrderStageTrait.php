<?php
namespace App\Models;
trait OrderStageTrait{
    protected $stageMap=[
        ''=>[
            'customer_deleted'=>            ['Удалить','danger'],
            'customer_cart'=>               ['Создать'],
            ],
        'customer_purged'=>                 [],
        'customer_deleted'=>[
            'customer_purged'=>             ['Удалить','danger'],
            'customer_cart'=>               ['Восстановить'],
            ],
        'customer_cart'=>[
            'customer_purged'=>             ['Удалить','danger'],
            'customer_confirmed'=>          [],
            'customer_action_confirm'=>     ['Продолжить','success'],
            ],
        'customer_confirmed'=>[
            'customer_cart'=>               ['Изменить'],
            'customer_payed_card'=>         [],
            'customer_action_checkout'=>    ['Продолжить','success'],
            ],
        'customer_payed_card'=>[
            'delivery_search'=>             [],
            ],
        'delivery_search'=>[
            'customer_start'=>              [],
        ],
        'customer_start'=>[
            'supplier_start'=>              ['Начать подготовку'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger'],
            'customer_refunded'=>           []//payment can be canceled by uniteller...
            ],
        
        
        
        'supplier_rejected'=>[
            //'customer_purged'=>             ['Удалить','danger'],
            'customer_refunded'=>           [],
            ],
        'supplier_reclaimed'=>[
            'customer_refunded'=>           [],
            ],
        'supplier_start'=>[
            'supplier_finish'=>             ['Закончить подготовку','success'],
            'supplier_corrected'=>          ['Изменить заказ'],
            'supplier_rejected'=>           [],
            ],
        'supplier_corrected'=>[
            'supplier_finish'=>             ['Закончить подготовку','success'],
            //'supplier_action_add'=>         ['Добавить товар'],
            'supplier_rejected'=>           ['Отказаться от заказа!','danger'],
            ],
        'supplier_finish'=>[
            'supplier_action_take_photo'=>  ['Сфотографировать'],
            'supplier_corrected'=>          ['Изменить'],
            'delivery_start'=>              ['Начать доставку','success'],
            'delivery_no_courier'=>         [],
            ],
        
        
        
        'delivery_start'=>[
            'delivery_finish'=>             ['Завершить доставку','success'],
            'delivery_action_take_photo'=>  ['Сфотографировать'],
            'delivery_action_call_customer'=>['Позвонить клиенту'],
            'delivery_action_rejected'=>    ['Отказаться от доставки','danger'],
            'delivery_rejected'=>           [],
            ],
        
        'delivery_rejected'=>[
            'supplier_reclaimed'=>          ['Принять возврат заказа']
            ],
        'delivery_no_courier'=>[
            'customer_refunded'=>           []  
        ],
        'delivery_finish'=>[
            'customer_finish'=>             [],
            'customer_disputed'=>           [],
            'customer_action_objection'=>   ['Открыть спор','light'],
            ],

        
        
        
        'customer_disputed'=>[
            'customer_refunded'=>           [],
            'customer_finish'=>             ['Завершить заказ','success'],
            'customer_action_take_photo'=>  ['Сфотографировать заказ'],
            ],
        'customer_refunded'=>       [
            'customer_finish'=>             [],
            ],
        'customer_finish'=>[
            //'customer_deleted'=>    ['Удалить','danger'],
            ]
    ];
    
    public function itemStageCreate( $order_id, $stage, $data=null, $check_permission=true ){
        if( $check_permission ){
            $this->permitWhere('w');
        }
        $order=$this->itemGet( $order_id, 'basic' );
        if( !is_object($order) ){
            return $order;
        }
        if($order->stage_current==$stage){
            return 'ok';
        }
        $OrderGroupModel=model('OrderGroupModel');
        $group=$OrderGroupModel->select('group_id')->itemGet(null,$stage);
        $result=$this->itemStageValidate($stage,$order,$group);
        if( $result!=='ok' ){
            return $result;
        }
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $this->transStart();
        
        $this->allowedFields[]='order_group_id';
        $updated=$this->update($order_id,['order_group_id'=>$group->group_id]);
        $joined=$OrderGroupMemberModel->joinGroup($order_id,$group->group_id);
        $this->itemCacheClear();
        
        $handled=$this->itemStageHandle( $order_id, $stage, $data );
        if( $updated && $joined && $handled==='ok' ){
            $this->transComplete();
            
            $this->itemStageChangeNotify($order, $stage, $data);
        }
        return $handled;
    }
    
    private function itemStageValidate($stage,$order,$group){
        $next_stages=$this->stageMap[$order->stage_current??'']??[];
        if( !isset($next_stages[$stage]) || empty($group->group_id) ){
            return 'invalid_next_stage';
        }
        if( $order->user_role!='admin' && strpos($stage, $order->user_role)!==0 ){
            echo "$stage, $order->user_role";
            return 'invalid_stage_role';
        }
        return 'ok';
    }
    
    private function itemStageHandle( $order_id, $stage, $data ){
        helper('job');
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        return $this->{$stageHandlerName}($order_id, $data);
    }
    
    private function itemStageChangeNotify($order, $stage, $data){
        $recievers_id=$order->owner_id.','.$order->owner_ally_ids;
        $push=(object)[
            'message_transport'=>'push',
            'message_reciever_id'=>$recievers_id,
            'message_data'=>[
                'topic'=>'pushStageChanged',
                'order_id'=>$order->order_id,
                'stage'=>$stage,
                'title'=>'#'.$order->order_id,
                'body'=>''.$order->stage_current_name,
                
                //'title'=>view('messages/order/stage_changed_title',(array)$order),
                //'body'=>view('messages/order/stage_changed_body',(array)$order),
            ]
        ];
        $notification_task=[
            'task_name'=>"onStageChanged background push Notify #$order->order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$push]]]
                ]
        ];
        jobCreate($notification_task);
    }
    ////////////////////////////////////////////////
    //ORDER STAGE HANDLING LISTENERS
    ////////////////////////////////////////////////
    
    private function onCustomerPurged($order_id){
        return $this->itemPurge($order_id);
    }

    private function onCustomerDeleted($order_id){
        return $this->itemDelete($order_id);
    }
    
    private function onCustomerCart($order_id){
        $this->itemUnDelete($order_id);
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'free');
        return 'ok';
    }
    
    private function onCustomerConfirmed( $order_id ){
        $order=$this->itemGet( $order_id, 'basic' );

        $this->db->transStart();
        $EntryModel=model('EntryModel');
        $EntryModel->listStockMove($order_id,'reserved');
        $order_sum_product=$EntryModel->listSumGet($order_id);
        if( !($order_sum_product>0) ){
            return 'order_is_empty';
        }
        $this->db->transComplete();
        $LocationModel=model('LocationModel');
        
        try{
            $order_update=[
                'order_start_location_id'=>$LocationModel->itemMainGet('store',$order->order_store_id)->location_id,
                'order_finish_location_id'=>$LocationModel->itemMainGet('user',$order->owner_id)->location_id
            ];
            $this->update($order_id,$order_update);
        } catch (\Exception $e){
            return 'address_not_set';
        }

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
    
    private function onCustomerPayedCard( $order_id, $data ){
        if( !$data??0 || !$data->total??0 ){
            return 'forbidden';
        }
        $TransactionModel=model('TransactionModel');
        
        $user_id=session()->get('user_id');
        $order=$this->itemGet($order_id,'basic');
        
        $trans=[
            'trans_amount'=>$order->order_sum_total,
            'trans_data'=>json_encode($data),
            'acc_debit_code'=>'account',
            'acc_credit_code'=>'customer',
            'owner_id'=>$order->owner_id,
            'is_disabled'=>0,
            'holder'=>'order',
            'holder_id'=>$order_id,
            'updated_by'=>$user_id,
        ];

        $TransactionModel->itemCreate($trans);    
        $transaction_created=$this->db->affectedRows()?'ok':'idle';
        if($order->order_sum_total!=$data->total){//would be wrong to deny or not???????
            //return 'wrong_amount';
        }
        $order_started=$this->itemStageCreate($order_id, 'delivery_search');
        if( $transaction_created=='ok' && $order_started=='ok' ){
            return 'ok';
        }
        return 'error';
    }
    
    private function onCustomerStart( $order_id, $data ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $PrefModel=model('PrefModel');

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
        $order=$this->itemGet($order_id);
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
        ///////////////////////////////////////////////////
        //CREATING READY COURIERS NOTIFICATIONS
        ///////////////////////////////////////////////////
        $this->readyCouriersNotify( $context );
        return 'ok';
    }
    
    private function readyCouriersNotify( $context ){//SHOULD MOVE TO COURIER MODEL!!!!!
        $CourierModel=model('CourierModel');
        $ready_courier_list=$CourierModel->listGet(['status'=>'ready','limit'=>5,'order']);
        if( !$ready_courier_list ){
            return false;
        }
        $messages=[];
        foreach($ready_courier_list as $courier){
            $context['courier']=$courier;
            $message_text=view('messages/order/on_customer_start_COUR_sms',$context);
            $messages[]=(object)[
                        'message_reciever_id'=>$courier->user_id,
                        'message_transport'=>'message',
                        'message_text'=>$message_text
                    ];
        }
        $sms_job=[
            'task_name'=>"Courier Notify Order",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$messages]]
                ],
        ];
        jobCreate($sms_job);
        return true;
    }
    
    private function onCustomerDisputed( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $EntryModel=model('EntryModel');
        $CourierModel=model('CourierModel');

        $EntryModel->listStockMove($order_id,'free');
        helper('job');
        
        $StoreModel->itemCacheClear();
        $order=$this->itemGet($order_id,'all');
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
        ///////////////////////////////////////////////////
        //CREATING STAGE RESET JOB
        ///////////////////////////////////////////////////
        $PrefModel=model('PrefModel');
        $timeout_min=$PrefModel->itemGet('delivery_finish_timeout_min','pref_value',0);
        $next_start_time=time()+$timeout_min*60;
        $stage_reset_task=[
            'task_name'=>"customer_finish Fastforward #$order_id",
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['customer_disputed','customer_finish',$order_id]]
                ],
            'task_next_start_time'=>$next_start_time
        ];
        jobCreate($stage_reset_task);
        return 'ok';
    }
    
    private function onCustomerRefunded( $order_id, $data ){
        // if( !$data??0 || !$data->total??0 ){
        //     return 'forbidden';
        // }

        $order=$this->itemGet($order_id,'basic');
        
        //$TransactionModel=model('TransactionModel');
        
        //$user_id=session()->get('user_id');
        // $TransactionModel=model('TransactionModel');
        // $trans=[
        //     'trans_amount'=>$order->order_sum_total,
        //     'trans_data'=>json_encode($data),
        //     'acc_debit_code'=>'account',
        //     'acc_credit_code'=>'customer',
        //     'owner_id'=>$order->owner_id,
        //     'is_disabled'=>0,
        //     'holder'=>'order',
        //     'holder_id'=>$order_id,
        //     'updated_by'=>$user_id,
        // ];
        // $TransactionModel->itemCreate($trans);    
        //$transaction_created=$this->db->affectedRows()?'ok':'idle';

        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        helper('job');
        
        $StoreModel->itemCacheClear();
        $order=$this->itemGet($order_id,'all');
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
            'message_subject'=>"#{$order->order_id} ВОЗВРАТ СРЕДСТВ",
            'template'=>'messages/order/on_customer_refunded_ADMIN_email.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'message',
            'template'=>'messages/order/on_customer_refunded_CUST_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"supplier_rejected Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$cust_sms]]]
                ]
        ];
        jobCreate($notification_task);
        return $this->itemStageCreate($order_id, 'customer_finish');
    }
    
    private function onCustomerFinish( $order_id ){
        return 'ok';
    }
        
    private function onSupplierRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $EntryModel=model('EntryModel');

        $EntryModel->listStockMove($order_id,'free');
        helper('job');
        
        $StoreModel->itemCacheClear();
        $order=$this->itemGet($order_id,'basic');
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
        return $this->itemStageCreate($order_id, 'customer_refunded');
        return 'ok';
    }
        
    private function onSupplierStart(){
        return 'ok';
    }
    
    private function onSupplierCorrected(){
        return 'ok';
    }
    
    private function onSupplierReclaimed($order_id){
        /*
         * Should we start reclamation???? 
         * Or admin should do it from cloud control panel???
         * Or money should stay as prepay at account???
         * 
         * Penalty to courier???
         * Penalty to customer???
         */
        return $this->itemStageCreate($order_id, 'customer_refunded');
    }
    private function onSupplierFinish( $order_id ){
        $PrefModel=model('PrefModel');


        $EntryModel=model('EntryModel');
        $stock_move_result=$EntryModel->listStockMove($order_id,'commited');

        ///////////////////////////////////////////////////
        //CREATING STAGE RESET JOB
        ///////////////////////////////////////////////////
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
    
    
    private function onDeliverySearch( $order_id ){
        return $this->itemStageCreate($order_id, 'customer_start');
    }
    
    private function onDeliveryStart( $order_id ){
        $CourierModel=model('CourierModel');
        if( !$CourierModel->isReadyCourier() ){
            return 'wrong_courier_status';
        }
        $order=$this->itemGet($order_id);
        if( !$order->images ){
            return 'photos_must_be_made';
        }
        return 'ok';
    }
    private function onDeliveryRejected( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $CourierModel=model('CourierModel');
        helper('job');
        
        $StoreModel->itemCacheClear();
        $order=$this->itemGet($order_id,'all');
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

    private function onDeliveryNoCourier( $order_id ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        helper('job');
        
        $StoreModel->itemCacheClear();
        $order=$this->itemGet($order_id,'basic');
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
        $notification_task=[
            'task_name'=>"delivery_notfound Notify #$order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$admin_email,$admin_sms]]]
                ]
        ];
        jobCreate($notification_task);
        $this->itemStageCreate($order_id, 'customer_refunded');
        return 'ok';
    }

    private function onDeliveryFinish( $order_id ){
        //make transaction for commission of courier
        $PrefModel=model('PrefModel');
        ///////////////////////////////////////////////////
        //CREATING STAGE RESET JOB
        ///////////////////////////////////////////////////
        $timeout_min=$PrefModel->itemGet('delivery_finish_timeout_min','pref_value',0);
        $next_start_time=time()+$timeout_min*60;
        $stage_reset_task=[
            'task_name'=>"customer_finish Fastforward #$order_id",
            'task_programm'=>[
                    ['method'=>'orderResetStage','arguments'=>['delivery_finish','customer_finish',$order_id]]
                ],
            'task_next_start_time'=>$next_start_time
        ];
        helper('job');
        jobCreate($stage_reset_task);
        return 'ok';
    }
    
}