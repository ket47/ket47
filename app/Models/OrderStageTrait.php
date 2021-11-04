<?php

namespace App\Models;
trait OrderStageTrait{
    protected $stageMap=[
        ''=>                        ['customer_created,customer_deleted'],
        'customer_deleted'=>        ['customer_created'],
        'customer_created'=>        ['customer_deleted,customer_confirmed'],
        'customer_confirmed'=>      ['customer_payed'],
        'customer_payed'=>          ['customer_start'],
        'customer_start'=>          ['supplier_start,supplier_reject'],
        
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
        $next_stages=$this->stageMap[$order->stage_current??''][0]??'';
        if( !in_array($stage, explode(',', $next_stages)) ){
            return 'invalid_next_stage';
        }
        $OrderGroupModel=model('OrderGroupModel');
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $group=$OrderGroupModel->select('group_id')->itemGet(null,$stage);

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
    
    private function itemStageHandle( $order_id, $stage, $data ){
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        return $this->{$stageHandlerName}($order_id, $data);
    }
    
    private function onCustomerDeleted($order_id){
        return $this->itemDelete($order_id);
    }
    
    private function onCustomerCreated($order_id){
        return $this->itemUnDelete($order_id);
    }
    
    private function onCustomerConfirmed( $order_id ){
        return 'ok';
    }
    
    private function onCustomerPayed( $order_id, $data ){
        $EntryModel=model('EntryModel');
        $TransactionModel=model('TransactionModel');
        
        $user_id=session()->getVar('user_id');
        $order_sum=$EntryModel->listSumGet( $order_id );
        $order=$this->itemGet($order_id);
        
        if($order_sum!=$data->Amount){
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
        
    }
    
    private function onSupplierStart(){
        
    }
    
    private function onSupplierCorrection(){
        
    }
    
    private function onSupplierFinish(){
        
    }
}