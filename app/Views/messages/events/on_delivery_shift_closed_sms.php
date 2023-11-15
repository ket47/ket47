🏁🏁🏁🏁🏁🏁🏁🏁🏁🏁
<b><?=$courier->courier_name?></b>, ваша смена закрыта. 

<pre>Начало  </pre><b><?=$shift->created_at?></b>
<pre>Конец   </pre><b><?=$shift->finished_at?></b>
<pre>Время   </pre><b><?=sprintf('%02d:%02d:%02d', ($total_duration??0)/3600,($total_duration??0)/60%60, ($total_duration??0)%60);?></b>
<?php if($statistics->heavy_bonus??0): ?>
<pre>Бонус   </pre><b><?=$statistics->heavy_bonus?></b> (<?=$statistics->heavy_count?>)
<?php endif; ?>

Задания не доступны. 
Новый статус <u>ОТБОЙ</u> 💤

Спасибо за работу!