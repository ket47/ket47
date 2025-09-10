📦 Посылка <?=$store->store_name??''?> #<?=$order->order_id?>. 

Заказчик <b><?=$customer->user_name??'-'?></b> +<?=$customer->user_phone??'-'?> 
Нужно отвезти <b><?=$order->order_description?></b> 

📍 Откуда <i><?=$order_data->location_start->location_address??' '?></i><b><?=$order_data->location_start->location_comment??' '?></b> 
🏁 Куда <i><?=$order_data->location_finish->location_address??' '?></i><b><?=$order_data->location_finish->location_comment??' '?></b> 

<?php if($order_data->payment_by_credit??0):?>

💵 Оплата в кредит
<?php endif; ?>
<?=$user_agent->os??'--'?> <b><?=$user_agent->ver??'--'?></b>