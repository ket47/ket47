<?php if(in_array($order->stage_current,['customer_await','customer_schedule'])): ?>
📦 Заказ на вызов курьера поставлен на ожидание. Старт в <?=date("H:i, d.m",$customer_start_time);?>. Начало доставки в <?=date("H:i, d.m",$order_data->plan_delivery_start);?>
<?php else: ?>
📦📦📦📦📦📦📦📦📦📦
<?=$reciever->user_name??'admin' ?>, вам поступил заказ на вызов курьера №<?=$order->order_id?> <?=$store->store_name??''?>. 

Заказчик <?=$customer->user_name??'-'?> +<?=$customer->user_phone??'-'?>
<?php endif; ?>