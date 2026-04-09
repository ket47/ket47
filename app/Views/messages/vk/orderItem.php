=================================
📄 Заказ #<?=$order->order_id?> от <?=date('d.m.y H:i',strtotime($order->created_at))?> из <?=($order->store->store_name??null)?> 

▶ Статус: <?=$order->stage_current_name?>

<?php if($order->info->supplier_name??null):?>
-------------------------------------------------------------------
🏢 Продавец: <?=$order->info->supplier_name?>

📍 Откуда: <?=$order->info->supplier_location_address?> <?php if($order->info->supplier_location_comment):?>(<?=$order->info->supplier_location_comment?>)<?php endif;?>

📍 Адрес: https://yandex.ru/maps/?pt=<?=$order->info->supplier_location_longitude?>,<?=$order->info->supplier_location_latitude?>&z=19&l=map,trf
📞 Телефон: <?=$order->info->supplier_phone?>
<?php endif;?>

<?php if($order->info->customer_phone??null):?>
    -------------------------------------------------------------------
👨 Клиент: <?=$order->info->customer_name?> 
📍 Куда: <?=$order->info->customer_location_address?> <?php if($order->info->customer_location_comment):?>(<?=$order->info->customer_location_comment?>)<?php endif;?>

📍 Адрес: https://yandex.ru/maps/?pt=<?=$order->info->customer_location_longitude?>,<?=$order->info->customer_location_latitude?>&z=19&l=map,trf
📞 Телефон: <?=$order->info->customer_phone?>
<?php endif;?>

-------------------------------------------------------------------
👛 Сумма заказа:  <?=$order->order_sum_total?>₽
<?php if($order->order_description??null): ?>
-------------------------------------------------------------------
💬 Коментарий к заказу: <?=$order->order_description?>

<?php endif;?>

<?php if($order->info->tariff_info): ?>
-------------------------------------------------------------------
<?=strip_tags($order->info->tariff_info)?>
-------------------------------------------------------------------
<?php endif ?>


<?php if($order->order_objection??null): ?>
-------------------------------------------------------------------
⛔ Проблема с заказом: <?=$order->order_objection?>
<?php endif;?>
=================================