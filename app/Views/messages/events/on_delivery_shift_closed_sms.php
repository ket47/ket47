🛑🛑🛑🛑🛑🛑🛑🛑🛑🛑
<b><?=$courier->courier_name?></b>, ваша смена закрыта. 

<pre>Начало  </pre><b><?=$shift->created_at?></b>
<pre>Конец   </pre><b><?=$shift->finished_at?></b>
<pre>Время   </pre><b><?=sprintf('%02d:%02d:%02d', ($total_duration??0)/3600,($total_duration??0)/60%60, ($total_duration??0)%60);?></b>
<pre>Маршрут </pre><b><?=round(($total_distance??0)/1000)?>км</b>

Задания не доступны. 
Новый статус <u>ОТБОЙ</u> 💤