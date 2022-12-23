<?php
namespace App\Libraries\Telegram;
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
            return $this->sendText($this->stageErrorMap[$result]??$result,'','order_message');
        }
        $this->orderSend();
    }

    public function onOrderOpen($order_id){
        //w('order_send'.date('i:s'));
        $opened_order_id=session()->get('opened_order_id');
        if( $order_id!=$opened_order_id ){
            $this->orderClose();
            session()->set('opened_order_id',$order_id);
        }
        $this->orderSend($order_id);
        $orderOpenedMessageId=session()->get('order');
        $result=$this->pinMessage($orderOpenedMessageId);
        if( !$result['ok'] ){
            pl($result['description'],false);
        }
    }
    public function onOrderListGet( $list_type_id=1 ){
        $list_type=($list_type_id==1?'active_only':'system_finish');
        $OrderModel=model("OrderModel");
        $orders=$OrderModel->listGet(['order_group_type'=>$list_type,'limit'=>5]);
        if( count($orders) ){
            foreach($orders as $i=>$order){
                $store_names[]=$order->store_name;
                $label=($i+1).") Заказ #{$order->order_id} от ".date('d.m.y H:i',strtotime($order->created_at))." [{$order->stage_current_name}]";
                $buttons[]=$this->Telegram->buildInlineKeyboardButton($label,'',"onOrderOpen-{$order->order_id}");
            }
        }
        $context=[
            'orders'=>$orders,
            'listType'=>$list_type,
            'storeNames'=>implode(',',array_unique($store_names))
        ];
        $html=View('messages/telegram/orderList',$context);
        $buttons[]=$this->Telegram->buildInlineKeyboardButton("Открыть в приложении","https://tezkel.com/order/order-list");
        $keyboard=array_chunk($buttons,1);
        $keyboard[]=[
            $this->Telegram->buildInlineKeyboardButton($this->orderButtons[0][2],"",$this->orderButtons[0][1]),
            $this->Telegram->buildInlineKeyboardButton($this->orderButtons[1][2],"",$this->orderButtons[1][1])
        ];
        $opts=[
            'disable_web_page_preview'=>1,
            'reply_markup'=>$this->Telegram->buildInlineKeyBoard($keyboard)
        ];
        $this->sendHTML($html,$opts,'order');
    }

    public function orderSend(){
        $order_id=session()->get('opened_order_id');
        $order=$this->orderGet($order_id);
        $order_html=View('messages/telegram/order',['order'=>$order]);
        $has_next_stages=false;
        foreach($order->stage_next as $stage=>$conf){
            if( !($conf[0]??null) || str_contains($stage,'action') ){
                continue;
            }
            if( ($conf[1]??null)=='danger'){
                $conf[0]="\xE2\x9D\x8C ".$conf[0];
            } else
            if( ($conf[1]??null)=='success'){
                $conf[0]="\xE2\x9C\x85 ".$conf[0];
            }
            $buttons[]=$this->Telegram->buildInlineKeyboardButton($conf[0],'',"onStageCreate-$order_id,$stage");
            $has_next_stages=true;
        }
        $buttons[]=$this->Telegram->buildInlineKeyboardButton("Открыть в приложении","https://tezkel.com/order/order-{$order_id}");
        $keyboard=array_chunk($buttons,1);
        $keyboard[]=[
            $this->Telegram->buildInlineKeyboardButton($this->orderButtons[0][2],"",$this->orderButtons[0][1]),
            $this->Telegram->buildInlineKeyboardButton($this->orderButtons[1][2],"",$this->orderButtons[1][1])
        ];
        $opts=[
            'reply_markup'=>$this->Telegram->buildInlineKeyBoard($keyboard),
            'disable_web_page_preview'=>1
        ];
        $this->sendHTML($order_html,$opts,'order');
        if( !$has_next_stages ){
            $this->orderClose();
        }
    }
    public function orderClose(){
        $orderOpenedMessageId=session()->get('order_opened_message_id');
        session()->remove('opened_order_id');
        session()->remove('order_opened_message_id');
        $this->unpinMessage($orderOpenedMessageId);
    }
    private function orderGet($order_id){
        $OrderModel=model('OrderModel');
        return $OrderModel->itemGet($order_id);
    }
    
    private function orderPhotoDownload($order_id,$img_url){
        $image_data = [
            'image_holder' => 'order',
            'image_holder_id' => $order_id
        ];
        $OrderModel = model('OrderModel');
        $image_hash = $OrderModel->imageCreate($image_data);
        if (!$image_hash) {
            return 'forbidden';
        }
        if ($image_hash === 'limit_exeeded') {
            return 'limit_exeeded';
        }
        copy($img_url,WRITEPATH . 'images/'. $image_hash . '.webp');
        return \Config\Services::image()
                        ->withFile(WRITEPATH . 'images/' . $image_hash . '.webp')
                        ->resize(1024, 1024, true, 'height')
                        ->convert(IMAGETYPE_WEBP)
                        ->save();
    }

}