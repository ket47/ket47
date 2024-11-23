<?php
namespace App\Libraries;
class AcquirerUnitellerMock{
    public function linkGet($order_all){
        return getenv('uniteller.gateway').'pay?';
    }

    public function cardRegisterLinkGet($user_id){
        return getenv('uniteller.gateway').'pay?';
    }

    public function cardRegisterActivate(){
        return 'ok';
    }
    public function statusGet($order_id_full,$mode=null){
        list($order_id)=explode('-',$order_id_full);
        $OrderModel=model('OrderModel');
        $order=$OrderModel->itemGet($order_id,'basic');
        $order_data=$OrderModel->itemDataGet($order_id);
        $balance=$order_data->payment_card_confirm_sum??$order_data->payment_card_fixate_sum??$order->order_sum_total;
        $status='authorized';
        if( $mode=='beforepayment' ){
            return false;
        }
        
        if(getenv('test.acquirerMockFailAuth')){
            $status='waiting';
        }
        return (object)[
            'order_id'=>$order_id_full,
            'status'=>$status,
            'total'=>$balance,
            'billNumber'=>rand(100000,999999),
            'approvalCode'=>000,
            'needConfirm'=>1
        ];
    }
    /**
     * Gets status of payment and if payed applies to order
     */
    public function statusCheck( int $order_id ){
        // $status=$this->statusGet($order_id);
        // $OrderModel=model('OrderModel');
        // $OrderModel->itemStageAdd( $order_id, 'customer_payed_card', $status, false );
        return 'idle';
    }

    public function confirm(int $billNumber,$sum){
        $OrderModel=model('OrderModel');
        $order_id=$OrderModel->where("JSON_EXTRACT(order_data,\"$.payment_card_fixate_id\")=$billNumber")->get()->getRow('order_id');

        $status='authorized';
        if(getenv('test.acquirerMockFailConfirm')){
            $status='waiting';
        }
        log_message('notice',"Acquirer mock Confirm request #$billNumber sum:$sum");
        //'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        return (object)[
            'order_id'=>$order_id,
            'status'=>$status,
            'total'=>$sum,
            'billNumber'=>$billNumber,
            'approvalCode'=>000,
        ];
    }

    public $refundPartialIsNeeded=true;
    
    public function refund(int $billNumber,$sum){
        $OrderModel=model('OrderModel');
        $order_id=$OrderModel->where("JSON_EXTRACT(order_data,\"$.payment_card_fixate_id\")=$billNumber")->get()->getRow('order_id');
        $status='authorized';
        if(getenv('test.acquirerMockFailRefund')){
            $status='waiting';
        }
        log_message('notice',"Acquirer mock Refund request #$billNumber sum:$sum");
        //'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        return (object)[
            'order_id'=>$order_id,
            'status'=>$status,
            'total'=>$sum,
            'billNumber'=>$billNumber,
            'approvalCode'=>000,
        ];
    }

    public function pay( object $order_all ){
        $OrderModel=model('OrderModel');
        $update=(object)[
            'payment_card_fixate_mock'=>'mock',
            'payment_card_fixate_id'=>rand(11111,99999),
            'payment_card_fixate_sum'=>$order_all->order_sum_total,
            'payment_by_card'=>1,
        ];
        $OrderModel->fieldUpdateAllow('order_data');
        $OrderModel->itemDataUpdate($order_all->order_id,$update);
        return 'ok';
    }
}