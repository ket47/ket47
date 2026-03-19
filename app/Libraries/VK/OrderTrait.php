<?php
namespace App\Libraries\VK;
trait OrderTrait{
    private $orderButtons=[
        ['isUserSignedIn',  'onOrderListGet-1',   "\xF0\x9F\x93\x82 Активные заказы"],
        ['isUserSignedIn',  'onOrderListGet-2',   "\xF0\x9F\x93\x81 Завершенные заказы"],
    ];

    private $stageErrorMap=[
        'invalid_next_stage'=>"Не могу изменить статус",
        'forbidden_bycustomer'=>"Запрещено покупателем",
        'photos_must_be_made'=>"Пришлите фотографию заказа",
        'order_sum_zero'=>"Сумма заказа нулевая",
        'order_sum_exceeded'=>"Сумма заказа больше предоплаты",
        'address_not_set'=>"Нет адреса",
        'order_is_empty'=>"Заказ пуст",
        'wrong_courier_status'=>"Курьер не готов",
        'already_payed'=>"Уже оплачен",
    ];
    public function onStageCreate( $order_id, $stage ){
        if( str_contains($stage,'action_take_photo') ){
            return $this->sendText("Пришлите фото заказа");
        }
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemStageCreate($order_id, $stage);
        if( $result!='ok' ){
            return $this->api->setText($this->stageErrorMap[$result]??$result,'','order_message');
        }
        $this->orderSend($order_id);
    }
    public function onOrderOpen($order_id){
        //w('order_send'.date('i:s'));
        $this->orderSend($order_id);
        $orderOpenedMessageId=session()->get('order');
        $this->pinMessage($orderOpenedMessageId);
    }

    public function onOrderListGet( $list_type_id=1 ){
        $filter = [
            'order_group_type' => $list_type_id == 1 ? 'active_only' : 'system_finish'
        ];
        if($list_type_id == 2){
            $filter['limit'] = 5;
        }
        $OrderModel = model("OrderModel");
        $orders = $OrderModel->listGet($filter);
        if(empty($orders)){
            return $this->api->setText("Нет активных заказов.");
        }
        foreach($orders as $item){
            $buttons = [];
            $this->buildOrderItem($item);
            $this->api->messagesSend($this->client_id);
        }
        return true;
    }

    public function orderSend($order_id){
        $order = $this->orderGet($order_id);
        
        if(!is_object($order)){
            pl(['bot orderSend FAILED',$order,$_SESSION]);
        }
        $this->buildOrderItem($order);
        $this->api->messagesSend($this->client_id);
        return false;
    }

    private function buildOrderItem($item){
        $order = $this->orderGet($item->order_id);
        $buttons = [];
        foreach($order->stage_next as $stage => $conf){
            if( !($conf[0]??null) || str_contains($stage,'action') ){
                continue;
            }
            if( ($conf[1]??null)=='danger'){
                $conf[0]="\xE2\x9D\x8C ".$conf[0];
            } else
            if( ($conf[1]??null)=='success'){
                $conf[0]="\xE2\x9C\x85 ".$conf[0];
            }
            $buttons[] = $this->createButton($conf[0], "onStageCreate-$order->order_id,$stage");
            
        }

        $rows=array_chunk($buttons, 2);

        $rows[] = [ $this->createButton("Открыть в приложении", "https://tezkel.com/order/order-{$order->order_id}", true) ];

        $keyboard = [
            "one_time" => false,
            "inline" => true,
            "buttons" => $rows
        ];
        $text = View('messages/vk/orderItem',['order' => $order]);
        $this->api->setText($text);
        $this->api->setKeyboard($keyboard);
    }

    public function orderClose(){
        $orderOpenedMessageId=session()->get('order_opened_message_id');
        session()->remove('opened_order_id');
        session()->remove('order_opened_message_id');
    }
    private function orderGet($order_id){
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemGet($order_id);

        if( $result->is_shipment??0 || ($result->order_script??null)=='shipment' ){
            $order_data=$OrderModel->itemDataGet($order_id);
            $result->locationStart=$order_data->location_start??[];
            $result->locationFinish=$order_data->location_finish??[];
        }
        return $result;
    }
    
}