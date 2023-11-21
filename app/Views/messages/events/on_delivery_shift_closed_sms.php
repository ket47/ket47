<?php
    $order_cost=150;
    $hour_cost=170;
    $hour_cost_car=190;

    $order_count_all=$statistics->order_count??0;
    $order_count_heavy=$statistics->heavy_count??0;
    $order_count_standart=$order_count_all-$order_count_heavy;

    $order_sum_standart=(int)($order_cost*$order_count_all);
    $order_sum_all=(int)($order_sum_standart+$statistics->heavy_bonus??0);

    $shift_sum_standart=round($total_duration*($hour_cost/3600)/50)*50;
    $shift_sum_car=(int)($total_duration*($hour_cost_car/3600)/50)*50;
?>
🏁🏁🏁🏁🏁🏁🏁🏁🏁🏁
<b><?=$courier->courier_name?></b>, ваша смена закрыта. 

<pre>Начало  <?=$shift->created_at?></pre>
<pre>Конец   <?=$shift->closed_at?></pre>

<pre>
Время   <?=sprintf('%02d:%02d:%02d', ($total_duration??0)/3600,($total_duration??0)/60%60, ($total_duration??0)%60);?> 
Оплата  <?=$shift_sum_standart?>₽ (<?=$shift_sum_car?>₽) 
</pre>
<pre>Заказы  <?=$order_count_all?>шт 
Оплата  <?=$order_sum_all?>₽ 
</pre>

Задания не доступны. 
Новый статус <u>ОТБОЙ</u> 💤

Спасибо за работу!  