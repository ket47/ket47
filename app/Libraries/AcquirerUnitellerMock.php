<?php
namespace App\Libraries;
class AcquirerUnitellerMock{
    public function linkGet($order_id){
        $OrderModel=model('OrderModel');
        $order_basic=$OrderModel->itemGet($order_id,'basic');
        if( !is_object($order_basic) ){
            return 'order_notfound';
        }
        if( !($order_basic->order_sum_product>0) || $order_basic->stage_current!='customer_confirmed' ){
            return 'order_notvalid';
        }
        $store_is_ready=model('StoreModel')->itemIsReady($order_basic->order_store_id);
        if( $store_is_ready!==1 ){
            return 'store_notready';
        }
        $customer=model('UserModel')->itemGet($order_basic->owner_id);
        if( !is_object($customer) ){//??? strange check...
            return 'user_notfound';
        }
        $order_data=$OrderModel->itemDataGet($order_id);
        if( ($order_data->payment_by_card??0)!=1 ){
            return 'card_payment_notallowed';
        }
        return getenv('uniteller.gateway').'pay?';
    }

    public function statusGet($order_id){
        $order=model('OrderModel')->itemGet($order_id,'basic');
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
}