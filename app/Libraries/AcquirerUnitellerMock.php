<?php
namespace App\Libraries;
class AcquirerUnitellerMock{
    public function linkGet($order_id){
        return getenv('uniteller.gateway').'pay?';
    }

    public function statusGet($order_id){
        $order=model('OrderModel')->itemGet($order_id);
        $status='authorized';
        if(getenv('test.acquirerMockFailAuth')){
            $status='waiting';
        }
        return (object)[
            'order_id'=>$order_id,
            'status'=>$status,
            'total'=>$order->order_sum_total,
            'billNumber'=>rand(100000,999999),
            'approvalCode'=>000,
        ];
    }

    public function confirm(int $billNumber,$sum){
        $filter=(object)[
            'trans_holder'=>'order',
            'trans_tags'=>'#orderPaymentFixation',
        ];
        $TransactionModel=model('TransactionModel');
        $authTrans=$TransactionModel->where("JSON_EXTRACT(trans_data,\"$.billNumber\")=$billNumber")->itemFind($filter);

        $status='authorized';
        if(getenv('test.acquirerMockFailConfirm')){
            $status='waiting';
        }
        //'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        return (object)[
            'order_id'=>$authTrans->trans_holder_id,
            'status'=>$status,
            'total'=>$sum,
            'billNumber'=>$billNumber,
            'approvalCode'=>000,
        ];
    }

    public function refund(int $billNumber,$sum){
        $filter=(object)[
            'trans_holder'=>'order',
            'trans_tags'=>'#orderPaymentFixation',
        ];
        $TransactionModel=model('TransactionModel');
        $authTrans=$TransactionModel->where("JSON_EXTRACT(trans_data,\"$.billNumber\")=$billNumber")->itemFind($filter);
        $status='authorized';
        if(getenv('test.acquirerMockFailRefund')){
            $status='waiting';
        }
        //'OrderNumber;Status;Total;ApprovalCode;BillNumber'
        return (object)[
            'order_id'=>$authTrans->trans_holder_id,
            'status'=>$status,
            'total'=>$sum,
            'billNumber'=>$billNumber,
            'approvalCode'=>000,
        ];
    }
}