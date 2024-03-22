<?php
    if( !function_exists('r50') ){
        function r50( $num ){
            return round($num/50)*50;
        }  
    }

    $order_cost     =70;
    $hour_cost_rent =160;
    $hour_cost_own  =190;

    $heavy_bonus_moped  =$statistics->heavy_bonus??0;
    $heavy_bonus_car    =0;
    $order_count_all    =$statistics->order_count??0;
    $order_sum_all      =r50($order_cost*$order_count_all);
    $hour_sum_rent      =r50($total_duration*($hour_cost_rent/3600));
    $hour_sum_own       =r50($total_duration*($hour_cost_own/3600));

    $total_moped_rent   =r50($hour_sum_rent+$order_sum_all+$heavy_bonus_moped);
    $total_moped_own    =r50($hour_sum_own +$order_sum_all+$heavy_bonus_moped);
    $total_car_own      =r50($hour_sum_own +$order_sum_all+$heavy_bonus_car);
?>
🏁🏁🏁🏁🏁🏁🏁🏁🏁🏁
<b><?=trim($courier->courier_name)?></b>, ваша смена закрыта. 

<pre>Начало  <?=$shift->created_at?> </pre>
<pre>Конец   <?=$shift->closed_at?> </pre>
<pre>Время   ⏱️<?=sprintf('%02d:%02d:%02d', ($total_duration??0)/3600,($total_duration??0)/60%60, ($total_duration??0)%60);?> 
Заказы  📃<?=$order_count_all?>шт </pre>

🏍️Мопед личный <b><?=$total_moped_own?>₽</b>
<pre>⏱️<?=$hour_sum_own?>₽ 📃<?=$order_sum_all??0?>₽ ⛈️<?=$heavy_bonus_moped?>₽</pre> 
🛵Мопед аренда <b><?=$total_moped_rent?>₽</b>
<pre>⏱️<?=$hour_sum_rent?>₽ 📃<?=$order_sum_all??0?>₽ ⛈️<?=$heavy_bonus_moped?>₽</pre> 
🚗Авто личное <b><?=$total_car_own?>₽</b>
<pre>⏱️<?=$hour_sum_own?>₽ 📃<?=$order_sum_all??0?>₽ ⛈️<?=$heavy_bonus_car?>₽</pre>

Задания не доступны. 
Новый статус <u>ОТБОЙ</u> 💤

Спасибо за работу!  