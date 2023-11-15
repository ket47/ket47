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

        $finalized=
               $this->orderFinalizeRefund( $order_basic,$order_data)
            && $this->orderFinalizeConfirm($order_basic,$order_data)
            && $this->orderFinalizeInvoice($order_basic,$order_data)
            && $this->orderFinalizeSettle( $order_basic,$order_data);

        if( !$finalized ){//?????????????
            $orderIsCanceled=isset($order_data->order_is_canceled)?1:0;
            $refund=$this->orderFinalizeRefund( $order_basic,$order_data);
            $confirm=$this->orderFinalizeConfirm($order_basic,$order_data);
            $invoice=$this->orderFinalizeInvoice($order_basic,$order_data);
            $settle=$this->orderFinalizeSettle( $order_basic,$order_data);
            pl("order #$order_id Finalization FAILED orderIsCanceled:$orderIsCanceled || refund:$refund && confirm:$confirm && invoice:$invoice && settle:$settle",false);
        }
        return $finalized;
    }

    private $acquirerStatusCache;
    private function acquirerStatusGet( $order_id ){
        if( !isset($this->acquirerStatusCache[$order_id]) ){
            $Acquirer=\Config\Services::acquirer();
            $this->acquirerStatusCache[$order_id]=$Acquirer->statusGet($order_id);
        }
        return $this->acquirerStatusCache[$order_id];
    }

    private function orderFinalizeRefund($order_basic,$order_data){//Made refund of excess money
        $skip=
                ($order_data->finalize_refund_done??0)
            ||  !($order_data->payment_card_fixate_id??0);
        if( $skip ){
            return true;
        }

        $Acquirer=\Config\Services::acquirer();
        $paymentStatus=$Acquirer->statusGet($order_basic->order_id);
        $fixationBalance=(float)($paymentStatus->total??0);
        $fixationId=($order_data->payment_card_fixate_id??0);

        $refundSum=$fixationBalance-$order_basic->order_sum_total;
        if( ($order_data->order_is_canceled??0) || ($order_data->sanction_courier_fee??0) || ($order_data->sanction_supplier_fee??0)  ){
            $refundSum=$fixationBalance;
        }
        if( $refundSum<0 ){
            log_message('error',"Payment refunding failed for order #{$order_basic->order_id}. Fixation amount is smaller $fixationBalance<={$order_basic->order_sum_total} ");
            return false;
        }

        $order_data_update=(object)[
            'finalize_refund_done'=>1
        ];
        if($refundSum!=0){
            $acquirer_data=$Acquirer->refund($fixationId,$refundSum);
            if( !$acquirer_data ){//connection error need to repeat
                return false;
            }
            $order_data_update->payment_card_refund_id=$acquirer_data->billNumber;
            $order_data_update->payment_card_refund_sum=$refundSum;
            $order_data_update->payment_card_refund_date=date('Y.m.d H:i:s');
        }

        $OrderModel=model('OrderModel');
        $OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return true;
    }

    private function orderFinalizeConfirm($order_basic,$order_data){//Claim payment for order
        $skip=
                ($order_data->finalize_confirm_done??0)
            ||  ($order_data->order_is_canceled??0)
            ||  ($order_data->sanction_courier_fee??0) 
            ||  ($order_data->sanction_supplier_fee??0)
            ||  !($order_data->payment_card_fixate_id??0);
        if( $skip ){
            return true;
        }

        $Acquirer=\Config\Services::acquirer();
        $paymentStatus=$Acquirer->statusGet($order_basic->order_id);

        $fixationBalance=(float)($paymentStatus->total??0);
        $fixationId=($order_data->payment_card_fixate_id??0);
        $confirmSum=$order_basic->order_sum_total;

        if( $fixationBalance<$confirmSum || $confirmSum<0 ){
            log_message('error',"Payment confirmation failed for order #{$order_basic->order_id}. Fixation balance is smaller $fixationBalance<=$confirmSum ");
            return false;
        }

        if($paymentStatus->needConfirm=='0'){//already confirmation done
            $confirmSum=0;
        }

        $order_data_update=(object)[
            'finalize_confirm_done'=>1
        ];
        if($confirmSum!=0){
            $acquirer_data=$Acquirer->confirm($fixationId,$confirmSum);
            if( !$acquirer_data ){//connection error need to repeat
                return false;
            }
            $order_data_update->payment_card_confirm_id=$acquirer_data->billNumber;
            $order_data_update->payment_card_confirm_sum=$confirmSum;
            $order_data_update->payment_card_confirm_date=date('Y.m.d H:i:s');
        }

        $OrderModel=model('OrderModel');
        $OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return true;
    }

    private function orderFinalizeInvoice($order_basic,$order_data){//Create tax invoice
        $skip=
                ($order_data->finalize_invoice_done??0)
            ||  ($order_data->order_is_canceled??0)
            ||  ($order_data->sanction_courier_fee??0) 
            ||  ($order_data->sanction_supplier_fee??0)
            ||  !($order_data->payment_by_card??0);
        if( $skip ){
            return true;
        }

        $order_all=model('OrderModel')->itemGet($order_basic->order_id);
        $invoiceSum=$order_all->order_sum_total;

        $order_data_update=(object)[
            'finalize_invoice_done'=>1
        ];
        if($invoiceSum!==0){
            $order_all->store=model('StoreModel')->itemGet($order_all->order_store_id,'basic');
            $order_all->print_delivery_as_agent=$order_data->delivery_by_store??0;
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
            $order_data_update->invoice_id=$cashier_data->Registration->FiscalData->CheckNumber;
            $order_data_update->invoice_sum=$invoiceSum;
            $order_data_update->invoice_date=$cashier_data->Registration->FiscalData->Date;
            $order_data_update->invoice_link=$cashier_data->Registration->Link??'';
        }

        $OrderModel=model('OrderModel');
        $OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return true;
    }

    private function orderFinalizeSettle($order_basic,$order_data){//Calculate profits, interests etc
        if( isset($order_data->order_is_canceled) ){
            return $this->orderFinalizeCancel($order_basic,$order_data);
        }
        /**
         * update data because it may change in previous stages
         * eg refund,confirm sum
         */
        $order_data=model('OrderModel')->itemDataGet($order_basic->order_id,false);
        return 
           $this->orderFinalizeSettleCustomer($order_basic,$order_data)
        && $this->orderFinalizeSettleSupplier($order_basic,$order_data)
        && $this->orderFinalizeSettleCourier($order_basic,$order_data)
        && $this->orderFinalizeSettleSystem($order_basic,$order_data);
    }

    private function orderFinalizeCancel($order_basic,$order_data){
        $PromoModel=model('PromoModel');
        $PromoModel->itemOrderDisable($order_basic->order_id,0);
        return true;
    }

    private function orderFinalizeSettleCustomer($order_basic,$order_data){
        $PromoModel=model('PromoModel');
        $PromoModel->itemOrderUse($order_basic->order_id);
        return true;
    }
    
    private function orderFinalizeSettleSupplier($order_basic,$order_data){
        $skip=
                ($order_data->finalize_settle_supplier_done??0)
            ||  ($order_data->order_is_canceled??0);
        if( $skip ){
            return true;
        }
        $productSum=$order_basic->order_sum_product;

        $context=[
            'order_basic'=>$order_basic,
            'order_data'=>$order_data
        ];

        if($order_data->payment_by_card??0){//if only marketplace don't do this transaction
            $invoiceDescription=view('transactions/supplier_invoice',$context);
            $invoiceTrans=(object)[
                'trans_date'=>$order_basic->updated_at,
                'trans_amount'=>$productSum,
                'trans_role'=>'supplier->site',
                'tags'=>"order:{$order_basic->order_id}:invoice store:{$order_basic->order_store_id}",
                'trans_description'=>$invoiceDescription,
                'owner_id'=>0,//customer should not see
                'owner_ally_ids'=>$order_basic->order_store_admins,
                'is_disabled'=>0,
            ];
            if($invoiceTrans->trans_amount!=0){
                $result=$this->itemCreate($invoiceTrans);
                if( !$result ){
                    log_message('error',"Making #orderInvoice transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()));
                    return false;
                }
            }
        }

        if($order_data->sanction_supplier_fee??0){
            $sanctionDescription=view('transactions/supplier_sanction',$context);
            $sanctionTrans=(object)[
                'trans_date'=>$order_basic->updated_at,
                'trans_amount'=>$productSum,
                'trans_role'=>'site.sanction->supplier',
                'tags'=>"order:{$order_basic->order_id}:sanction:store  store:{$order_basic->order_store_id}",
                'trans_description'=>$sanctionDescription,
                'owner_id'=>0,//customer should not see
                'owner_ally_ids'=>$order_basic->order_store_admins,
                'is_disabled'=>0,
                'trans_holder'=>'order',
                'trans_holder_id'=>$order_basic->order_id
            ];
            if($sanctionTrans->trans_amount!=0){
                $result=$this->itemCreate($sanctionTrans);
                if( !$result ){
                    log_message('error',"Making #sanctionTrans transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()));
                    return false;
                }
            }
        }
        $paymentSum=$order_data->payment_card_confirm_sum??0;

        $paymentFee=$order_data->payment_fee??0;
        $paymentCost=$order_data->payment_cost??0;
        $orderFee=$order_data->order_fee??0;
        $orderCost=$order_data->order_cost??0;
        
        $commissionSum=$orderCost+$productSum*$orderFee/100+$paymentCost+$paymentSum*$paymentFee/100;    
        $commissionDescription=view('transactions/supplier_commission',$context);
        $commissionTrans=(object)[
            'trans_date'=>$order_basic->updated_at,
            'trans_amount'=>$commissionSum,
            'trans_role'=>'profit->supplier',
            'tags'=>"order:{$order_basic->order_id}:commission:store store:{$order_basic->order_store_id}",
            'trans_description'=>$commissionDescription,
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>$order_basic->order_store_admins,
            'is_disabled'=>0
        ];
        if($commissionTrans->trans_amount!=0){
            $result=$this->itemCreate($commissionTrans);
            if( !$result ){
                log_message('error',"Making #orderCommission transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()) );
                return false;
            }
        }


        $order_data_update=(object)[
            'finalize_settle_supplier_done'=>1
        ];

        $OrderModel=model('OrderModel');
        $OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return true;
    }

    private function orderFinalizeSettleCourier($order_basic,$order_data){
        $skip=
                ($order_data->finalize_settle_courier_done??0)
            ||  ($order_data->order_is_canceled??0)
            ||  !($order_data->delivery_by_courier??0);
        if( $skip ){
            return true;
        }

        if($order_data->sanction_courier_fee??0){
            $sanctionSum=$order_basic->order_sum_total;
            $context=[
                'order_basic'=>$order_basic,
                'order_data'=>$order_data
            ];
            $sanctionDescription=view('transactions/courier_sanction',$context);
    
            $sanctionTrans=(object)[
                'trans_date'=>$order_basic->updated_at,
                'trans_amount'=>$sanctionSum,
                'trans_role'=>'profit->courier',
                'tags'=>"order:{$order_basic->order_id}:sanction:courier courier:{$order_basic->order_courier_id}",
                'trans_description'=>$sanctionDescription,
                'owner_id'=>0,//customer should not see
                'owner_ally_ids'=>($order_basic->order_courier_admins??0),
                'is_disabled'=>0
            ];
            if($sanctionTrans->trans_amount!=0){
                $result=$this->itemCreate($sanctionTrans);
                if( !$result ){
                    log_message('error',"Making #courierSanction transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()));
                    return false;
                }
            }
        } else {
            $LocationModel=model('LocationModel');
            $courierCost        =(int) getenv('delivery.courier.cost')??0;
            $courierFee         =(int) getenv('delivery.courier.fee')??0;
            $distanceTreshold   =(int) getenv('delivery.courier.distanceTreshold')??0;
            $distanceFee        =(int) getenv('delivery.courier.distanceFee')??0;
            $productSum=$order_basic->order_sum_product;
            $distanceM=$LocationModel->distanceGet($order_basic->order_start_location_id, $order_basic->order_finish_location_id);
            $distanceKM=round($distanceM/1000*1.2-$distanceTreshold);//+20%
    
            $compensationSum=0;
            if( $distanceKM>0 ){
                $compensationSum=$distanceFee*$distanceKM;
            }
            $bonusSum=$courierCost+$compensationSum+$productSum*$courierFee/100;
            $context=[
                'order_basic'=>$order_basic,
                'order_data'=>$order_data,
                'costSum'=>$courierCost,
                'feeSum'=>$productSum*$courierFee/100,
                'compensationSum'=>$compensationSum,
                'distance_km'=>$distanceKM,
            ];
            $bonusDescription=view('transactions/courier_bonus',$context);
    
            $bonusTrans=(object)[
                'trans_date'=>$order_basic->updated_at,
                'trans_amount'=>$bonusSum,
                'trans_role'=>'courier->profit',
                'tags'=>"order:{$order_basic->order_id}:bonus:courier courier:{$order_basic->order_courier_id}",
                'trans_description'=>$bonusDescription,
                'owner_id'=>0,//customer should not see
                'owner_ally_ids'=>($order_basic->order_courier_admins??0),
                'is_disabled'=>0
            ];
            if($bonusTrans->trans_amount!=0){
                $result=$this->itemCreate($bonusTrans);
                if( !$result ){
                    log_message('error',"Making #courierBonus transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()));
                    return false;
                }
            }
        }
        $order_data_update=(object)[
            'finalize_settle_courier_done'=>1
        ];

        $OrderModel=model('OrderModel');
        $OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return true;
    }

    private function orderFinalizeSettleSystem($order_basic,$order_data){
        $skip=
                ($order_data->finalize_settle_system_done??0)
            ||  ($order_data->order_is_canceled??0);
        if( $skip ){
            return true;
        }

        $deliveryCost=$order_data->delivery_cost??0;
        $deliveryFee=$order_data->delivery_fee??0;
        $productSum=$order_basic->order_sum_product;
        $promoSum=$order_basic->order_sum_promo??0;

        $commissionSum=$deliveryCost+$productSum*$deliveryFee/100-$promoSum;
        $context=[
            'order_basic'=>$order_basic,
            'order_data'=>$order_data
        ];
        $commissionDescription=view('transactions/system_commission',$context);
        $commissionTrans=(object)[
            'trans_date'=>$order_basic->updated_at,
            'trans_amount'=>$commissionSum,
            'trans_role'=>'profit->site',
            'tags'=>"order:{$order_basic->order_id}:commission:delivery store:{$order_basic->order_store_id} courier:{$order_basic->order_courier_id}",
            'trans_description'=>$commissionDescription,
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>0,
            'is_disabled'=>0
        ];
        if($commissionTrans->trans_amount!=0){
            $result= $this->itemCreate($commissionTrans);
            if( !$result ){
                log_message('error',"Making #commissionSum transaction failed. Order #{$order_basic->order_id} ".json_encode($this->errors()));
                return false;
            }
        }
        $order_data_update=(object)[
            'finalize_settle_system_done'=>1
        ];

        $OrderModel=model('OrderModel');
        $OrderModel->itemDataUpdate($order_basic->order_id,$order_data_update);
        return true;
    }
}