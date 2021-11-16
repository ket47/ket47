<?php
namespace App\Models;
trait OrderStageTrait{
    protected $stageMap=[
        ''=>[
            'customer_created'=>    ['Создать'],
            'customer_deleted'=>    ['Удалить','negative'],
            ],
        'customer_deleted'=>[
            'customer_created'=>    ['Восстановить'],
            ],
        'customer_created'=>[
            'customer_confirmed'=>  ['Подтвердить заказ'],
            'customer_deleted'=>    ['Удалить','negative'],
            ],
        'customer_confirmed'=>[
            'action_cloud_pay'=>    ['Оплатить картой','positive'],
            'customer_created'=>    ['Отменить заказ'],
            'customer_payed_cloud'=>[],
            ],
        'customer_payed_cloud'=>[
            'customer_start'=>      [],
            ],
        'customer_start'=>[
            'supplier_start'=>      ['Начать подготовку'],
            'supplier_rejected'=>   ['Отказаться от заказа!','negative'],
            ],
        
        
        
        'supplier_rejected'=>[
            'customer_refunded'=>   []
            ],
        'supplier_reclaimed'=>[
            'customer_refunded'=>   []
            ],
        'supplier_start'=>[
            'supplier_corrected'=>  ['Изменить заказ'],
            'supplier_finish'=>     ['Закончить подготовку','positive'],
            ],
        'supplier_corrected'=>[
            'supplier_finish'=>     ['Закончить подготовку','positive'],
            'supplier_rejected'=>   ['Отказаться от заказа!','negative'],
            ],
        'supplier_finish'=>[
            'supplier_corrected'=>  ['Изменить заказ'],
            'delivery_no_courier'=> [],
            'delivery_start'=>      ['Начать доставку','positive'],
            'action_take_photo'=>   ['Сфотографировать']
            ],
        
        
        
        'delivery_start'=>[
            'delivery_finish'=>     ['Завершить','positive'],
            'action_take_photo'=>   ['Сфотографировать'],
            'action_call_customer'=>['Позвонить заказчику'],
            'delivery_rejected'=>   ['Отказаться!','negative']
            ],
        'delivery_rejected'=>[
            'supplier_reclaimed'=>  ['Принять возврат заказа']
            ],
        'delivery_finish'=>[
            'customer_finish'=>     [],
            'customer_disputed'=>   ['Открыть спор','secondary']
            ],
        
        
        
        'customer_disputed'=>[
            'customer_refunded'=>   [],
            'customer_finish'=>     ['Завершить заказ','positive'],
            'action_take_photo'=>   ['Сфотографировать заказ'],
            'action_objection'=>    ['Написать возражение'],
            ],
        'customer_refunded'=>       [
            'customer_finish'=>     [],
            ],
        'customer_finish'=>[
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
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        return $this->{$stageHandlerName}($order_id, $data);
    }
    
    ////////////////////////////////////////////////
    //ORDER STAGE HANDLING LISTENERS
    ////////////////////////////////////////////////
    
    private function onCustomerDeleted($order_id){
        return $this->itemDelete($order_id);
    }
    
    private function onCustomerCreated($order_id){
        $this->itemUnDelete($order_id);
        return 'ok';
    }
    
    private function onCustomerConfirmed( $order_id ){
        /*
         * auto rollback to customer_created after 30 min???
         */
        return 'ok';
    }
    
    private function onCustomerPayedCloud( $order_id, $data ){
        if( !$data??0 || !$data->Amount??0 ){
            return 'forbidden';
        }
        $TransactionModel=model('TransactionModel');
        
        $user_id=session()->get('user_id');
        $order=$this->itemGet($order_id);
        
        if($order->order_sum_total!=$data->Amount){
            return 'wrong_amount';
        }
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
        $order_started=$this->itemStageCreate($order_id, 'customer_start');
        if( $transaction_created=='ok' && $order_started=='ok' ){
            return 'ok';
        }
        return 'error';
    }
    
    private function onCustomerStart( $order_id, $data ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $MessageModel=model('MessageModel');
        
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
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'message_transport'=>'sms',
            'template'=>'messages/order/on_customer_start_STORE_sms.php',
            'context'=>$context
        ];
        $store_email=(object)[
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'message_transport'=>'email',
            'message_subject'=>"Заказ №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_customer_start_STORE_email.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'sms',
            'template'=>'messages/order/on_customer_start_CUST_sms.php',
            'context'=>$context
        ];
        $MessageModel->listSend([$store_sms,$store_email,$cust_sms],true);//[$store_sms,$store_email,$cust_sms]
        return 'ok';
    }
    
    private function onCustomerRefunded( $order_id ){
        return $this->itemStageCreate($order_id, 'customer_finish');
    }
    
    private function onCustomerFinish( $order_id ){
        return 'ok';
    }
    
    
    private function onDeliverySearch( $order_id ){
        /*
         * should we create joblist????
         */
        return 'ok';
    }
    
    private function onSupplierRejected( $order_id ){
        /*
         * cancel product reserves
         */
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $MessageModel=model('MessageModel');
        
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
            'message_reciever_id'=>$store->owner_id.','.$store->owner_ally_ids,
            'message_transport'=>'email',
            'message_subject'=>"Заказ №{$order->order_id} от ".getenv('app.title'),
            'template'=>'messages/order/on_supplier_rejected_STORE_email.php',
            'context'=>$context
        ];
        $cust_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'sms',
            'template'=>'messages/order/on_supplier_rejected_CUST_sms.php',
            'context'=>$context
        ];
        $MessageModel->listSend([$store_email,$cust_sms],true);//[$store_sms,$store_email,$cust_sms]
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
    
    private function onSupplierFinish(){
        return 'ok';
    }
    
    
    
    private function onDeliveryStart( $order_id ){
        $order=$this->itemGet($order_id);
        if( !$order->images ){
            return 'photos_must_be_made';
        }
        return 'ok';
    }
    private function onDeliveryRejected( $order_id ){
        return 'ok';
    }
}