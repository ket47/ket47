📄<b>Заказ #<?=$order->order_id?></b> от <?=date('d.m.y H:i',strtotime($order->created_at))?> из <?=($order->store->store_name??null)?> <b>[<?=mb_strtoupper($order->stage_current_name, "utf-8")?>]</b>
◻◻◻◻◻◻◻◻◻◻◻◻◻
<?php if($order->info->supplier_name??null):?>
🏢Продавец: <i><?=$order->info->supplier_name?></i> <?=$order->info->supplier_phone?>
<a href="https://yandex.ru/maps/?pt=<?=$order->info->supplier_location_longitude?>,<?=$order->info->supplier_location_latitude?>&z=19&l=map,trf`" target="_new">
<?=$order->info->supplier_location_address?> <?=$order->info->supplier_location_comment?>
</a>
<?php endif;?>

<?php if($order->info->customer_phone??null):?>
⭐Покупатель: <i><?=$order->info->customer_name?></i> +<?=$order->info->customer_phone?>
<a href="https://yandex.ru/maps/?pt=<?=$order->info->customer_location_longitude?>,<?=$order->info->customer_location_latitude?>&z=19&l=map,trf`" target="_new">
<?=$order->info->customer_location_address?> <?=$order->info->customer_location_comment?></a>
<?php endif;?>

<?php if($order->locaionStart??null):?>
    
Откуда: 
<a href="https://yandex.ru/maps/?pt=<?=$order->locaionStart->location_longitude?>,<?=$order->locaionStart->location_latitude?>&z=19&l=map,trf`" target="_new">
<?=$order->locaionStart->location_address?> <?=$order->locaionStart->location_comment?></a>
+<?=$order->locaionStart->location_phone?>

<?php endif;?>

<?php if($order->locaionFinish??null):?>

Куда: 
<a href="https://yandex.ru/maps/?pt=<?=$order->locaionFinish->location_longitude?>,<?=$order->locaionFinish->location_latitude?>&z=19&l=map,trf`" target="_new">
<?=$order->locaionFinish->location_address?> <?=$order->locaionFinish->location_comment?></a>
+<?=$order->locaionFinish->location_phone?>

<?php endif;?>
        
<?php foreach($order->entries as $rnum=>$entry):?>
<?=($rnum+1)?>) <u><?=$entry->entry_text?></u> <b><?=$entry->entry_quantity?></b><?=$entry->product_unit?> x <?=$entry->entry_price?>р
<?php endforeach;?>
Сумма заказа:<?=$order->order_sum_total?>

<?php if($order->order_description??null): ?>
◻◻◻◻◻◻◻◻◻◻◻◻◻
💬<b>Коментарий к заказу</b>
<?=$order->order_description?>


<?php endif;?>
<?php if($order->order_objection??null): ?>
◻◻◻◻◻◻◻◻◻◻◻◻◻
⛔<b>Проблема с заказом</b>
<?=$order->order_objection?>


<?php endif;?>
◻◻◻◻◻◻◻◻◻◻◻◻◻