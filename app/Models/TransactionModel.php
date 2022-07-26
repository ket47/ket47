<?php
namespace App\Models;
use CodeIgniter\Model;

class TransactionModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'transaction_list';
    protected $primaryKey = 'trans_id';
    protected $allowedFields = [
        'trans_amount',
        'trans_data',
        'acc_debit_code',
        'acc_credit_code',
        'owner_id',
        'holder',
        'holder_id'
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    protected $validationRules    = [
        'trans_amount'    => 'required|greater_than[0]',
        'acc_debit_code'  => 'required',
        'acc_credit_code' => 'required',
        'holder'          => 'required',
        'holder_id'       => 'required'
    ];
    
    public function itemGet( $trans_id ){
        $this->permitWhere('r');
        $this->where('trans_id',$trans_id);
        return $this->get()->getRow();
    }

    public function itemFind( object $filter ){
        $this->permitWhere('r');
        if( $filter->trans_role??null ){
            $this->where('trans_role',$filter->trans_role);
        }
        if( $filter->trans_tags??null ){
            $this->where("MATCH (trans_tags) AGAINST ('{$filter->trans_tags}' IN BOOLEAN MODE)");
        }
        if( $filter->trans_holder??null ){
            $this->where('trans_holder',$filter->trans_holder);
        }
        if( $filter->trans_holder_id??null ){
            $this->where('trans_holder',$filter->trans_holder_id);
        }
        return $this->get()->getRow();
    }
    

    private function itemCreateTags($trans){
        $tags=$trans['trans_tags'];
        if($trans['trans_role']){
            list($debits,$credits)=explode('->',$trans['trans_role']);
            $tags.=str_replace('.',' #debit-','.'.$debits);
            $tags.=str_replace('.',' #credit-','.'.$credits);
        }
        if($trans['trans_holder']){
            $tags.=" #{$trans['trans_holder']}-{$trans['trans_holder_id']}";
        }
        return $tags;
    }

    public function itemCreate( $trans ){
        if( !$this->permit(null, 'w') ){
            return 'forbidden';
        }
        $trans['trans_tags']=$this->itemCreateTags($trans);
        $trans_id=$this->insert($trans,true);
        return $trans_id;
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
        $OrderModel=model('OrderModel');
        $order=$OrderModel->where('order_id',$order_id)->get()->getRow();

        $filter=(object)[
            'trans_tags'=>'#orderPrepayment',
            'trans_holder'=>'order',
            'trans_holder_id'=>$order_id
        ];
        $orderPrepaymentTrans=$this->itemFind($filter);
        $orderSumToClaim=$order->order_sum_total;
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



            $settlement['refund_commited']=true;

        } else {
            $settlement['refund_sum']=0;
            $settlement['refund_commited']=true;
        }




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