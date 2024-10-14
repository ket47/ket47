🛍️ Заказ из <?=$store->store_name?> №<?=$order->order_id?>. 

Покупатель <b><?=$customer->user_name??'-'?></b> +<?=$customer->user_phone??'-'?> 
<i><?=$customer->location_main->location_address??'-'?></i>
<?php if($order_data->delivery_by_courier??0):?>

🛵Доставка курьером
<?php endif; ?>
<?php if($order_data->delivery_by_store??0):?>

Доставка продавцом
<?php endif; ?>
<?php if($order_data->pickup_by_customer??0):?>

Самовывоз
<?php endif; ?>
<?php if($order_data->payment_by_cash??0):?>

💵 Оплата наличными
<?php endif; ?>