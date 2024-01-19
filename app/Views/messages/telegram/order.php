📄<b>Заказ #<?=$order->order_id?></b> от <?=date('d.m.y H:i',strtotime($order->created_at))?> из <?=($order->store->store_name??null)?> 

<b>Статус: </b>[<?=mb_strtoupper($order->stage_current_name, "utf-8")?>]
◻◻◻◻◻◻◻◻◻◻◻◻◻
<?php if($order->info->supplier_name??null):?>
🏢 <b>Продавец: </b><i><?=$order->info->supplier_name?></i> <?=$order->info->supplier_phone?>
<a href="https://yandex.ru/maps/?pt=<?=$order->info->supplier_location_longitude?>,<?=$order->info->supplier_location_latitude?>&z=19&l=map,trf`" target="_new">
<?=$order->info->supplier_location_address?> <?=$order->info->supplier_location_comment?>
</a>
<?php endif;?>

<?php if($order->info->customer_phone??null):?>
👨 <b>Клиент: </b><?=$order->info->customer_name?> 
<?php if( !($order->is_shipment??0) ): ?>
<a href="https://yandex.ru/maps/?pt=<?=$order->info->customer_location_longitude?>,<?=$order->info->customer_location_latitude?>&z=19&l=map,trf`" target="_new"><?=$order->info->customer_location_address?></a> 
<?=$order->info->customer_location_comment?> 
<?php endif; ?>
<?=$order->info->customer_phone?> 
<?php endif;?>

<?php if($order->locationStart??null):?>
📍 <b>Откуда: </b><a href="https://yandex.ru/maps/?pt=<?=$order->locationStart->location_longitude?>,<?=$order->locationStart->location_latitude?>&z=19&l=map,trf`" target="_new"><?=$order->locationStart->location_address?></a> 
<?=$order->locationStart->location_comment?> 
<?php if($order->locationStart->location_phone??0): ?>
+<?=$order->locationStart->location_phone?> 
<?php endif; ?>
<?php endif;?>

<?php if($order->locationFinish??null):?>
🏁 <b>Куда: </b><a href="https://yandex.ru/maps/?pt=<?=$order->locationFinish->location_longitude?>,<?=$order->locationFinish->location_latitude?>&z=19&l=map,trf`" target="_new"><?=$order->locationFinish->location_address?></a> 
<?=$order->locationFinish->location_comment?> 
<?php if($order->locationFinish->location_phone??0): ?>
+<?=$order->locationFinish->location_phone?> 
<?php endif; ?>
<?php endif;?>
        
<?php foreach($order->entries as $rnum=>$entry):?>
<?=($rnum+1)?>) <u><?=$entry->entry_text?></u> <b><?=$entry->entry_quantity?></b><?=$entry->product_unit?> x <?=$entry->entry_price?>р
<?php endforeach;?>
👛 <b>Сумма заказа: </b> <?=$order->order_sum_total?>

<?php if($order->order_description??null): ?>
◻◻◻◻◻◻◻◻◻◻◻◻◻
💬 <b>Коментарий к заказу</b>
<?=$order->order_description?>


<?php endif;?>
<?php if($order->order_objection??null): ?>
◻◻◻◻◻◻◻◻◻◻◻◻◻
⛔ <b>Проблема с заказом</b>
<?=$order->order_objection?>


<?php endif;?>
◻◻◻◻◻◻◻◻◻◻◻◻◻