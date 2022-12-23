
<?php if(count($ownedStoreList)>0):?>
🏢<b>Продавец <?=$user->user_name?></b>
<?php foreach($ownedStoreList as $store): ?>
<i><?=$store->store_name?></i> 
<?php if($store->is_working==1): ?>
<pre>Статус     </pre><b><u>ЗАПУЩЕН 💡</u></b>  
<pre>Расписание </pre><b><u><?=$store->is_opened?"Открыт до {$store->store_time_closes} 🔵":"Закрыт до {$store->store_time_opens} 🔴"?></u></b> 
<?php else: ?>
<pre>Статус     </pre><b><u>ПРИОСТАНОВЛЕН 💤</u></b>
<?php endif;?>
<?php endforeach; ?>
<pre>Заказов    </pre><b><u><?=$incomingOrderCount?$incomingOrderCount:'НЕТ'?></u></b>
<?php endif;?>