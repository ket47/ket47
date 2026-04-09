🔥 Задание от <?=$job->job_name?>.

↗ Откуда: <?=$job->start_address?>

📍 Адрес: https://yandex.ru/maps/?pt=<?=$job->start_longitude?>,<?=$job->start_latitude?>&z=19&l=map,trf
<?php if(!empty($job->info->supplier_location_comment)): ?>
💬 Детали: <?=$job->info->supplier_location_comment?>

<?php endif ?>
📞 Телефон: <?=$job->info->supplier_phone?>


↘ Куда: <?=$job->finish_address?>

📍 Адрес: https://yandex.ru/maps/?pt=<?=$job->finish_longitude?>,<?=$job->finish_latitude?>&z=19&l=map,trf
<?php if(!empty($job->info->customer_location_comment)): ?>
💬 Детали: <?=$job->info->customer_location_comment?>

<?php endif ?>
📞 Телефон: <?=$job->info->customer_phone?>


<?php if(isset($job->delivery_gain_base)): ?>
💵 Оплата: <?=$job->delivery_gain_base?>₽
<?php endif ?>
<?php if(isset($job->delivery_promised_tip)): ?>
🤑 Чаевые: <?=$job->delivery_promised_tip?>₽
<?php endif ?>



<?php if($job->payment_by_cash): ?>
⚠ Внимание: Оплата за наличные. Обязательно созвонитесь с клиентом перед началом заказа.
<?php endif ?>
