<?php

namespace App\Models;
trait OrderStageTrait{
    protected $stageMap=[
        ''=>                        ['customer_created,customer_deleted','Создать,Удалить'],
        'customer_deleted'=>        ['customer_created',"Восстановить"],
        'customer_created'=>        ['customer_deleted,customer_confirmed',"Удалить,Подтвердить заказ"],
        'customer_confirmed'=>      ['other_payed_cloud,customer_created','Оплатить картой|cloudPaymentsInit,Отменить заказ'],
        'other_payed_cloud'=>       ['customer_start'],
        'customer_start'=>          ['supplier_start,supplier_reject',"Начать подготовку,Отказаться от заказа"],
        
        'supplier_start'=>          ['supplier_correction,supplier_finish'],
        'supplier_finish'=>         ['delivery_start'],
        'delivery_search'=>         ['delivery_start,delivery_no_courier'],
        'delivery_start'=>          ['delivery_finish,delivery_no_address,delivery_rejected'],
        'delivery_finish'=>         ['customer_accepted,customer_partly_accepted,customer_rejected'],
        
        'customer_partly_accepted'=>['supplier_reclaimed'],
        'customer_rejected'=>       ['supplier_reclaimed'],
        'delivery_no_address'=>     ['supplier_reclaimed'],
        'delivery_rejected'=>       ['supplier_reclaimed'],
        
        'supplier_reclaimed'=>      ['customer_refunded'],
        'customer_refunded'=>       ['customer_finish'],
        'customer_accepted'=>       ['customer_finish'],
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
        $handled=$this->itemStageHandle( $order_id, $stage, $data );
        
        if( $updated && $joined && $handled==='ok' ){
            $this->transComplete();
        }
        return $handled;
    }
    
    private function itemStageValidate($stage,$order,$group){
        $next_stages=explode(',', $this->stageMap[$order->stage_current??'']??'');
        if( !in_array($stage, $next_stages) || !$group->group_id??0 ){
            return 'invalid_next_stage';
        }
        if( $order->user_role!='admin' && strpos($stage, $order->user_role)!==0 ){
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
        return 'ok';
    }
    
    private function onCustomerPayedCloud( $order_id, $data ){
        if( !$data??0 || !$data->Amount??0 ){
            return 'forbidden';
        }
        $EntryModel=model('EntryModel');
        $TransactionModel=model('TransactionModel');
        
        $user_id=session()->get('user_id');
        $order_sum=$EntryModel->listSumGet( $order_id );
        $order=$this->itemGet($order_id);
        
        if($order_sum->order_sum_total!=$data->Amount){
            return 'wrong_amount';
        }
        $trans=[
            'trans_amount'=>$order_sum->order_sum_total,
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
        $creation_result=$this->db->affectedRows()?'ok':'idle';
        if( $creation_result=='ok' ){
            $this->itemStageCreate($order_id, 'customer_start');
        }
        return $creation_result;
    }
    
    private function onCustomerStart( $order_id, $data ){
        $UserModel=model('UserModel');
        $StoreModel=model('StoreModel');
        $MessageModel=model('MessageModel');
        
        $order=$this->itemGet($order_id);
        $store=$StoreModel->itemGet($order->order_store_id,'basic');//should we notify only owner of store or also allys?
        $customer=$UserModel->itemGet($order->owner_id);
        $context=[
            'order'=>$order,
            'store'=>$store,
            'customer'=>$customer
        ];
        
        $store_sms=(object)[
            'message_reciever_id'=>$order->owner_id,
            'message_transport'=>'sms',
            'template'=>'messages/order/on_customer_start_STORE_sms.php',
            'context'=>$context
        ];
        $store_email=(object)[
            'message_reciever_id'=>$order->owner_id,
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
        $MessageModel->listSend([$store_sms,$store_email,$cust_sms]);
        return 'ok';
    }
    
    private function onSupplierStart(){
        
    }
    
    private function onSupplierCorrection(){
        
    }
    
    private function onSupplierFinish(){
        
    }
}