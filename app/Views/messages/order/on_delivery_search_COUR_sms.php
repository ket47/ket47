<b>Задание <u>#<?=$order->order_id?></u></b> 
Забрать из: <?=$order->store_name??$order->store->store_name?> 
<?php if($order->location_longitude??''): ?>
Адрес:<a href='https://yandex.ru/maps/?pt=<?=$order->location_longitude?>,<?=$order->location_latitude?>&z=19&l=map,trf'><?=$order->location_address?></a>
<?php endif; ?>