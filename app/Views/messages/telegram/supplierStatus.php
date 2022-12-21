
<b>Продавец <?=$user->user_name?></b>
<?php foreach($ownedStoreList as $store): ?>
    - <?=$store->store_name?> <?php if($store->is_working==1): ?>
💡 [ЗАПУЩЕН] <?=$store->is_opened?"🔵Открыт до {$store->store_time_closes}":"🔴Закрыт до {$store->store_time_opens}"?>

<?php else: ?>
💤 [ПРИОСТАНОВЛЕН] (не готов к заказам)
<?php endif;?>
<?php endforeach; ?>
Активных заказов: <b><u><?=$incomingOrderCount?></u></b>