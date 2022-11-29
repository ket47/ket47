<?php
namespace App\Models;

class OrderTransactionModel extends TransactionModel{
    ///////////////////////////////////////////////////////////////////////
    //FINALIZATION OF ORDER TRANSACTIONS AND ACTIONS 
    ///////////////////////////////////////////////////////////////////////
    public function orderFinalize($order_id):bool{
        $OrderModel=model('OrderModel');
        $order_basic=$OrderModel->itemGet($order_id,'basic');
        if(!is_object($order_basic)){
            return false;//order not found or permission denied
        }

        $order_data=$OrderModel->itemDataGet($order_id);

        $orderIsCanceled=isset($order_data->order_is_canceled)?true:false;
        $refund=$this->orderFinalizeRefund( $order_basic,$order_data);
        $confirm=$this->orderFinalizeConfirm($order_basic,$order_data);
        $invoice=$this->orderFinalizeInvoice($order_basic,$order_data);
        $settle=$this->orderFinalizeSettle( $order_basic,$order_data);

        $finalized=$orderIsCanceled
            || $refund
            && $confirm
            && $invoice
            && $settle;

        if( !$finalized ){
            pl("order #$order_id Finalization FAILED $orderIsCanceled || $refund && $confirm && $invoice && $settle",false);
        }
        return $finalized;
    }

    private function orderFinalizeRefund($order_basic,$order_data){//Made refund of excess money
        if( empty($order_data->payment_by_card) ){//ok if not payed by card
            return true;
        }
        if( isset($order_data->payment_card_refund_sum) && $order_data->payment_card_refund_sum>0 ){
            return true;
        }

        $fixationId=$order_data->payment_card_fixate_id??0;
        $fixationSum=$order_data->payment_card_fixate_sum??0;
        if( !$fixationId ){
            log_message('error',"Payment Refunding failed for order #{$order_basic->order_id}. Fixation record is not found");
            return false;
        }
        $isCustomerFullyRefunded=model('OrderGroupMemberModel')->isMemberOf($order_basic->order_id,'customer_refunded');
        if( $isCustomerFullyRefunded ){
            $refundSum=$fixationSum;
        } else {
            $refundSum=$fixationSum-$order_basic->order_sum_total;
        }
        if( $refundSum<=0 ){
            return true;
        }

        $Acquirer=\Config\Services::acquirer();
        $acquirer_data=$Acquirer->refund($fixationId,$refundSum);
        if( !$acquirer_data ){//connection error need to repeat
            return false;
        }
        $order_data_update=(object)[
            'payment_card_refund_id'=>$acquirer_data->billNumber,
            'payment_card_refund_sum'=>$acquirer_data->total,
            'payment_card_refund_date'=>date('Y.m.d H:i:s'),
        ];
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return $result=='ok';
    }

    private function orderFinalizeConfirm($order_basic,$order_data){//Claim payment for order
        if( empty($order_data->payment_by_card) ){//ok if not payed by card
            return true;
        }
        if( isset($order_data->payment_card_confirm_sum) && $order_data->payment_card_confirm_sum==$order_basic->order_sum_total ){
            return true;
        }
        $fixationId=$order_data->payment_card_fixate_id??0;
        $fixationSum=$order_data->payment_card_fixate_sum??0;
        $confirmSum=$order_basic->order_sum_total;
        if( !$fixationId ){
            log_message('error',"Payment Confirming failed for order #{$order_basic->order_id}. Fixation record is not found");
            return false;
        }
        if( $fixationSum<=$confirmSum ){
            log_message('error',"Payment confirmation failed for order #{$order_basic->order_id}. Fixation amount is smaller");
            return false;
        }

        $Acquirer=\Config\Services::acquirer();
        $acquirer_data=$Acquirer->confirm($fixationId,$confirmSum);
        if( !$acquirer_data ){//connection error need to repeat
            return false;
        }
        $order_data_update=(object)[
            'payment_card_confirm_id'=>$acquirer_data->billNumber,
            'payment_card_confirm_sum'=>$acquirer_data->total,
            'payment_card_confirm_date'=>date('Y.m.d H:i:s'),
        ];
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return $result=='ok';
    }

    private function orderFinalizeInvoice($order_basic,$order_data){//Create tax invoice
        if( isset($order_data->invoice_sum) && $order_data->invoice_sum==$order_basic->order_sum_total ){
            return true;
        }
        if( !isset($order_data->payment_by_card) ){
            return true;
        }
        $order_all=model('OrderModel')->itemGet($order_basic->order_id);
        $invoiceSum=$order_all->order_sum_total;
        $Cashier=\Config\Services::cashier();
        $cashier_data=$Cashier->printAndGet($order_all);

        if( !$cashier_data ){//connection error need to repeat
            log_message('error',"Printing check failed. Connection error??? Order #{$order_basic->order_id}");
            return false;
        }
        if( $cashier_data->ResultCode!=0 || $cashier_data->Registration->ResultCode!=0 ){
            $error_text=($cashier_data->ErrorMessage??'').'-'.($cashier_data->Registration->ErrorMessage??'');
            log_message('error',"Printing check failed. Order #{$order_basic->order_id} {$error_text}");
            return false;
        }
        $order_data_update=(object)[
            'invoice_id'=>$cashier_data->Registration->FiscalData->CheckNumber,
            'invoice_sum'=>$invoiceSum,
            'invoice_date'=>$cashier_data->Registration->FiscalData->Date,
            'invoice_link'=>$cashier_data->Registration->Link,
        ];

        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return $result=='ok'||$result=='idle';
    }

    private function orderFinalizeSettle($order_basic){//Calculate profits, interests etc
        $OrderModel=model('OrderModel');
        $order_data=$OrderModel->itemDataGet($order_basic->order_id,false);

        return 
           $this->orderFinalizeSettleCustomer($order_basic,$order_data)
        && $this->orderFinalizeSettleSupplier($order_basic,$order_data)
        && $this->orderFinalizeSettleCourier($order_basic,$order_data)
        && $this->orderFinalizeSettleSystem($order_basic,$order_data);
    }

    private function orderFinalizeSettleCustomer($order_basic,$order_data){
        return true;
    }
    
    private function orderFinalizeSettleSupplier($order_basic,$order_data){
        $feeSum=($order_data->payment_fee??0)/100*$order_basic->order_sum_product;
        $commissionSum=$feeSum+($order_data->order_cost??0);
        if($commissionSum===0){
            return true;
        }
        $context=[
            'order_basic'=>$order_basic,
            'order_data'=>$order_data
        ];
        $commissionDescription=view('transactions/supplier_commission',$context);
        $invoiceDescription=view('transactions/supplier_invoice',$context);
        $commissionTrans=(object)[
            'trans_amount'=>$commissionSum,
            'trans_role'=>'capital.profit->supplier',
            'trans_tags'=>"#orderCommission #store{$order_basic->order_store_id}",
            'trans_description'=>$commissionDescription,
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>$order_basic->order_store_admins,
            'is_disabled'=>0,
            'trans_holder'=>'order',
            'trans_holder_id'=>$order_basic->order_id
        ];
        $result=$this->itemCreateOnce($commissionTrans);

        if( !$result ){
            log_message('error',"Making #orderCommission transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()) );
            return false;
        }
        $invoiceTrans=(object)[
            'trans_amount'=>$order_basic->order_sum_product,
            'trans_role'=>'supplier->transit',
            'trans_tags'=>"#orderInvoice #store{$order_basic->order_store_id}",
            'trans_description'=>$invoiceDescription,
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>$order_basic->order_store_admins,
            'is_disabled'=>0,
            'trans_holder'=>'order',
            'trans_holder_id'=>$order_basic->order_id
        ];
        $result=$this->itemCreateOnce($invoiceTrans);
        if( !$result ){
            log_message('error',"Making #orderInvoice transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()));
            return false;
        }
        return true;
    }

    private function orderFinalizeSettleCourier($order_basic,$order_data){
        if( !isset($order_data->delivery_by_courier) ){
            return true;
        }
        $LocationModel=model('LocationModel');

        $courier_cost=(int) getenv('delivery.courier.cost');
        $courier_fee=(int) getenv('delivery.courier.fee');
        $distance_treshold=(int) getenv('delivery.courier.distanceTreshold');
        $distance_fee=(int)getenv('delivery.courier.distanceFee');
        $distance_m=$LocationModel->distanceGet($order_basic->order_start_location_id, $order_basic->order_finish_location_id);
        $distance_km=round($distance_m/1000*1.3-$distance_treshold);//+30%
        if( $distance_km>0 ){
            $compensationSum=$distance_fee*$distance_km;
        } else {
            $compensationSum=0;
        }
        $costSum=$courier_cost;
        $feeSum=$courier_fee/100*$order_basic->order_sum_product;
        $bonusSum=$costSum+$compensationSum+$feeSum;
        if($bonusSum===0){
            return true;
        }
        $context=[
            'order_basic'=>$order_basic,
            'order_data'=>$order_data
        ];
        $bonusDescription=view('transactions/courier_bonus',$context);
        $bonusTrans=(object)[
            'trans_amount'=>$bonusSum,
            'trans_role'=>'courier->capital.profit',
            'trans_tags'=>'#courierBonus',
            'trans_description'=>$bonusDescription,
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>$order_basic->order_courier_admins??0,
            'is_disabled'=>0,
            'trans_holder'=>'order',
            'trans_holder_id'=>$order_basic->order_id
        ];
        $result=$this->itemCreateOnce($bonusTrans);
        if( !$result ){
            log_message('error',"Making #courierBonus transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()));
            return false;
        }
        return true;
    }

    private function orderFinalizeSettleSystem($order_basic,$order_data){
        $context=[
            'order_basic'=>$order_basic,
            'order_data'=>$order_data
        ];
        $commissionSum=$order_data->delivery_cost??0 + ($order_data->delivery_fee??0)*$order_basic->order_sum_product - $order_basic->order_sum_promo??0;
        if($commissionSum===0){
            return true;
        }
        $comissionDescription=view('transactions/system_comission',$context);
        $comissionTrans=(object)[
            'trans_amount'=>$commissionSum,
            'trans_role'=>'capital.profit->transit',
            'trans_tags'=>'#commissionSum',
            'trans_description'=>$comissionDescription,
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>0,
            'is_disabled'=>0,
            'trans_holder'=>'order',
            'trans_holder_id'=>$order_basic->order_id
        ];
        $result= $this->itemCreateOnce($comissionTrans);
        if( !$result ){
            log_message('error',"Making #courierBonus transaction failed. Order #{$order_basic->order_id}");
            return false;
        }
        return true;
    }
}