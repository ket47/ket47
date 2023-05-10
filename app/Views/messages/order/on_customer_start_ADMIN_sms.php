<?=getenv('app.title')?>: <?=$reciever->user_name??'admin' ?>, вам поступил заказ №<?=$order->order_id?> из <?=$store->store_name?>. 

Покупатель <?=$customer->user_name??'-'?> <?=$customer->user_phone??'-'?> <?=$customer->location_main->location_address??'-'?>

<?php if($order_data->delivery_by_courier):?>
🛵Доставка курьером
<?php endif; ?>