<?=$listType=='active_only'?'Активные заказы':'Завершенные заказы'?> 
из <?=$storeNames?> 
◻◻◻◻◻◻◻◻◻◻◻◻◻ 
<?php if( !count($orders) ):?>
Нет заказов 
<?php endif; ?>
◻◻◻◻◻◻◻◻◻◻◻◻◻ 