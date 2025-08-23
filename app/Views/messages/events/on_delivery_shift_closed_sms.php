<?php
    if( !function_exists('r50') ){
        function r50( $num ){
            return round($num/50)*50;
        }  
    }

    $order_cost     =70;
    $hour_cost_rent =180;
    $hour_cost_own  =190;

    $heavy_bonus_moped  =$statistics->heavy_bonus??0;
    $heavy_bonus_car    =$heavy_bonus_moped/2;

    $speed_bonus=0;
    $look_bonus=0;
    if( isset($rating) ){
        foreach($rating as $score){
            $rating_bonus=0;
            $stars=$score->rating/0.2;
            if( $stars>=4.8 ){
                $rating_bonus=15;
            } else
            if( $stars>=4.5 ){
                $rating_bonus=10;
            } else
            if( $stars>=4.0 ){
                $rating_bonus=5;
            }
            if($score->tag_option=='speed'){
                $speed_bonus=$rating_bonus;
            } else {
                $look_bonus=$rating_bonus;
            }
        }
    }

    $order_count_all    =$statistics->order_count??0;
    $order_sum_all      =round(($order_cost+$speed_bonus+$look_bonus)*$order_count_all);
    $hour_sum_rent      =round($total_duration*($hour_cost_rent/3600));
    $hour_sum_own       =round($total_duration*($hour_cost_own/3600));

    $total_moped_rent   =r50($hour_sum_rent+$order_sum_all+$heavy_bonus_moped);
    $total_moped_own    =r50($hour_sum_own +$order_sum_all+$heavy_bonus_moped);
    $total_car_own      =r50($hour_sum_own +$order_sum_all+$heavy_bonus_car);
?>
🏁🏁🏁🏁🏁🏁🏁🏁🏁🏁
<b><?=trim($courier->courier_name)?></b>, ваша смена закрыта. 

<pre>Начало  <?=$shift->created_at?> 
Конец   <?=$shift->closed_at?> </pre>
<pre>Время   ⏱️<?=sprintf('%02d:%02d:%02d', floor(($total_duration??0)/3600),round(($total_duration??0)/60)%60, round(($total_duration??0))%60);?> 
Заказы  📃<?=$order_count_all?>шт 
Униформа   ❤️ +<?=$look_bonus?>₽ 
Быстрота   🚀 +<?=$speed_bonus?>₽ 
</pre>

🏍️Мопед личный <b><?=$total_moped_own?>₽</b>
<pre>⏱️<?=$hour_sum_own?>₽ 📃<?=$order_sum_all??0?>₽ ⛈️<?=$heavy_bonus_moped?>₽</pre> 
🛵Мопед аренда <b><?=$total_moped_rent?>₽</b>
<pre>⏱️<?=$hour_sum_rent?>₽ 📃<?=$order_sum_all??0?>₽ ⛈️<?=$heavy_bonus_moped?>₽</pre> 
🚗Авто личное <b><?=$total_car_own?>₽</b>
<pre>⏱️<?=$hour_sum_own?>₽ 📃<?=$order_sum_all??0?>₽ ⛈️<?=$heavy_bonus_car?>₽</pre>

Задания не доступны. 
Новый статус <u>ОТБОЙ</u> 💤

Спасибо за работу!  