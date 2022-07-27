<?php
namespace App\Models;
use CodeIgniter\Model;

class OrderTransactionModel extends TransactionModel{

    public function orderPrepaymentGet($order_id){
        $OrderModel=model('OrderModel');
        $order=$OrderModel->where('order_id',$order_id)->get()->getRow();
        if(!$order){
            return 'order_not_found';
        }

        $filter=(object)[
            'trans_tags'=>'#orderPrepayment',
            'trans_holder'=>'order',
            'trans_holder_id'=>$order_id
        ];
        $orderPrepaymentTrans=$this->itemFind($filter);
        return [
            'order_owner_id'=>$order->owner_id??0,
            'order_sum_total'=>$order->order_sum_total??0,
            'prepayment_sum'=>$orderPrepaymentTrans?$orderPrepaymentTrans->trans_amount:0
        ];
    }

    public function orderCardPreauthCreate($order,$acquirer_data){
        $user_id=session()->get('user_id');
        $trans=[
            'trans_amount'=>$order->order_sum_total,
            'trans_data'=>json_encode($acquirer_data),
            'trans_role'=>'customer.card->money.acquirer.blocked',
            'trans_tags'=>'#orderPrepayment',
            'owner_id'=>$order->owner_id,
            'is_disabled'=>0,
            'holder'=>'order',
            'holder_id'=>$order->order_id,
            'updated_by'=>$user_id,
        ];
        $this->itemCreate($trans);
        return $this->db->affectedRows()?'ok':'idle';
    }



    public function orderSettlementGet($order_id){
        $prepayment=$this->orderPrepaymentGet($order_id);




        $orderSumToClaim=$prepayment->order_sum_total;
        $orderSumToRefund=$orderPrepaymentTrans->trans_amount-$order->order_sum_total;

        if($orderSumToRefund<0){
            return 'trans_amount_is_unsufficient';
        }

        $settlement=[];
        if($orderSumToRefund>0){
            $settlement['refund_sum']=$orderSumToRefund;
            $filter=(object)[
                'trans_tags'=>'#orderRefund',
                'trans_holder'=>'order',
                'trans_holder_id'=>$order_id
            ];
            $orderRefundTrans=$this->itemFind($filter);
            if($orderRefundTrans){
                $settlement['refund_commited']=true;
            }
            $settlement['refund_commited']=false;
        } else {
            $settlement['refund_sum']=0;
            $settlement['refund_commited']=true;
        }

        if($orderSumToClaim>0){
            $settlement['claim_sum']=$orderSumToClaim;
            $filter=(object)[
                'trans_tags'=>'#orderClaim',
                'trans_holder'=>'order',
                'trans_holder_id'=>$order_id
            ];
            $orderClaimTrans=$this->itemFind($filter);
            if($orderClaimTrans){
                $settlement['claim_commited']=true;
            }
            $settlement['claim_commited']=false;
        } else {
            $settlement['claim_sum']=0;
            $settlement['claim_commited']=true;
        }

        if($orderSumToClaim>0){
            $settlement['bill_sum']=$orderSumToClaim;
            $filter=(object)[
                'trans_tags'=>'#orderBill',
                'trans_holder'=>'order',
                'trans_holder_id'=>$order_id
            ];
            $orderBillTrans=$this->itemFind($filter);
            if($orderBillTrans){
                $settlement['bill_commited']=true;
            }
            $settlement['bill_commited']=false;
        } else {
            $settlement['bill_sum']=0;
            $settlement['bill_commited']=true;
        }
        return $settlement;
    }















    
    public function itemUpdate( $trans ){
        $this->permitWhere('w');
        $this->update($trans->trans_id,$trans);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( $trans_id ){
        $this->permitWhere('w');
        $this->delete($trans_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function allowEnable(){
        $this->allowedFields[]='is_disabled';
    }
    
    public function listGet( $filter ){
        $ledger=[
            'ibal'=>$this->listIbalGet($filter),
            'entries'=>$this->listEntriesGet($filter),
            'fbal'=>$this->listFbalGet($filter)
        ];
        return $ledger;
    }
    
    private function listIbalGet( $filter ){
        if( $filter['idate']??0 ){
            $this->where('created_at<',$filter['idate']);
        } else {
            return 0;
        }
        $this->permitWhere('r');
        if( $filter['acc_debit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['acc_credit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        $this->select("SUM(trans_amount) ibal");
        return $this->get()->getRow('ibal');
    }
    
    private function listFbalGet( $filter ){
        if( $filter['fdate']??0 ){
            $this->where('created_at<',$filter['fdate']);
        } else {
            return 0;
        }
        $this->permitWhere('r');
        if( $filter['acc_debit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['acc_credit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        $this->select("SUM(trans_amount) fbal");
        return $this->get()->getRow('fbal');        
    }
    
    private function listEntriesGet( $filter ){
        $this->filterMake($filter);
        if( $filter['acc_debit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['acc_credit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['idate']??0 ){
            $this->where('created_at>',$filter['idate']);
        }
        if( $filter['fdate']??0 ){
            $this->where('created_at<',$filter['fdate']);
        }
        return $this->get()->getResult();        
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}