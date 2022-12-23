
🏃<b>Курьер <?=$user->user_name?></b>
<pre>ТС         </pre><?=$courier->courier_vehicle??'не указан'?> 
<pre>ИНН        </pre><?=$courier->courier_tax_num??'не указан'?> 
<pre>Статус     </pre><b><u><?= ($courier->status_type=='idle')?"ОТБОЙ 💤":($courier->status_type=='ready'?"ГОТОВ 🚦":"ЗАНЯТ 🚴")?></u></b> 
<pre>Заданий    </pre><b><u><?=$job_count?$job_count:'НЕТ'?></u></b> 

<?php if($courier->status_type=='idle'): ?>
ℹ Чтобы начать смену транслируйте вашу геопозицию в чат
<?php endif; ?>