⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️
<b>🛵Курьер для покупателя не найден</b>
Покупатель <b><?=$customer->user_name?></b> пытался заказать доставку, но доступных курьеров не было

<?php if($store): ?>
Продавец <?=$store->store_name?> <?=str_pad($store->store_phone??0, 12, "+", STR_PAD_LEFT)?>
<?php endif; ?>
Покупатель <?=$customer->user_name?> <?=str_pad($customer->user_phone??0, 12, "+", STR_PAD_LEFT)?>

Время <?=date('d.m.Y H:i:s')?>