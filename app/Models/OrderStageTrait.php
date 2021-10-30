<?php

namespace App\Models;
trait OrderStageTrait{
    protected $stageMap=[
        ''=>                        ['customer_created,customer_deleted'],
        'customer_deleted'=>        ['customer_created'],
        'customer_created'=>        ['customer_deleted,customer_payed'],
        'customer_payed'=>          ['customer_confirmed'],
        'customer_confirmed'=>      ['supplier_start,supplier_reject'],
        
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
        'customer_refunded'=>       ['customer_closed'],
        'customer_accepted'=>       ['customer_closed'],
    ];
    public function itemStageCreate( $order_id, $stage ){
        $this->permitWhere('w');
        $order=$this->itemGet( $order_id, 'basic' );
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
        $handled=$this->itemStageHandle( $order_id, $stage );
        
        if( $updated && $joined && $handled==='ok' ){
            $this->transComplete();
        }
        return $handled;
    }
    
    private function itemStageHandle( $order_id, $stage ){
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        return $this->{$stageHandlerName}($order_id);
    }
    
    private function onCustomerDeleted($order_id){
        return $this->itemDelete($order_id);
    }
    
    private function onCustomerCreated($order_id){
        return $this->itemUnDelete($order_id);
    }
    
    private function onCustomerPayed(){
        /*
         * need to ckeck payment status maybe customer has positive balance
         */
    }
    
    private function onCustomerConfirmed(){
        
    }
    
    private function onSupplierStart(){
        
    }
    
    private function onSupplierCorrection(){
        
    }
    
    private function onSupplierFinish(){
        
    }
}