<b>Заказ #<?=$order->order_id?></b> от <?=date('d.m.y H:i',strtotime($order->created_at))?> из <?=($order->store->store_name??null)?> <b>[<?=mb_strtoupper($order->stage_current_name, "utf-8")?>]</b>
<?php if($order->info->supplier_name??null):?>
Продавец: <i><?=$order->info->supplier_name?></i> <?=$order->info->supplier_phone?>
<a href="https://yandex.ru/maps/?pt=<?=$order->info->supplier_location_longitude?>,<?=$order->info->supplier_location_latitude?>&z=19&l=map,trf`" target="_new">
<?=$order->info->supplier_location_address?> <?=$order->info->supplier_location_comment?>
</a>
<?php endif;?>

<?php if($order->info->customer_phone??null):?>
Покупатель: <i><?=$order->info->customer_name?></i> +<?=$order->info->customer_phone?>
<a href="https://yandex.ru/maps/?pt=<?=$order->info->customer_location_longitude?>,<?=$order->info->customer_location_latitude?>&z=19&l=map,trf`" target="_new">
<?=$order->info->customer_location_address?> <?=$order->info->customer_location_comment?></a>
<?php endif;?>

<?php foreach($order->entries as $rnum=>$entry):?>
<u><?=($rnum+1)?>) <?=$entry->entry_text?> <?=$entry->entry_quantity?><?=$entry->product_unit?> x <?=$entry->entry_price?></u>
<?php endforeach;?>
Сумма заказа:<?=$order->order_sum_total?>