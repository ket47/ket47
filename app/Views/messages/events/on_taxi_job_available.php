🔥 <b><?=$job->job_name?></b> 
<?= $courier->courier_name ?>, есть новое задание <i><?= round($job->job_data->distance*0.001,1) ?>-<?= round($job->job_data->distance*0.0015,1) ?>км</i> 
📍 <b>Забрать </b> <a href='https://yandex.ru/maps/?pt=<?=$job->start_longitude?>,<?=$job->start_latitude?>&z=19&l=map,trf'><?=$job->start_address?></a>
🏁 <b>Привезти </b> <?php if($job->job_data->customer_heart_count>0):?>❤️(<?= $job->job_data->customer_heart_count ?>)<?php endif; ?> <a href='https://yandex.ru/maps/?pt=<?=$job->finish_longitude?>,<?=$job->finish_latitude?>&z=19&l=map,trf'><?=$job->finish_address?></a>

💵 <b>Ваш заработок до</b> <b><a href="https://tezkel.com/order"><?= round($job->courier_gain_total) ?>₽</a></b>


Вы получаете уведомления:
<?php if($courier->courier_parttime_notify=='silent'): ?>
🔇 без звука
<?php elseif($courier->courier_parttime_notify=='push'): ?>
🔊 со звуком
<?php elseif($courier->courier_parttime_notify=='ringtone'): ?>
🔔 рингтон
<?php endif; ?>