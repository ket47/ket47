🛵 Курьер: <?=$user->user_name?>

ТС: <?=$courier->courier_vehicle??'Не указан'?> 
ИНН: <?=$courier->courier_tax_num??'Не указан'?> 
Статус: <?= ($courier->status_type=='idle')?"Отбой 💤":($courier->status_type=='ready'?"Ожидает 🚦":($courier->status_type=='taxi'?"Такси 🚕":"Занят 🚴"))?>

Режим такси: <?= ($courier->courier_parttime_notify=='off')?"Выключен 🚫":"Включён ".(($courier->courier_parttime_notify=='silent')?"🔇":($courier->courier_parttime_notify=='push'?"🔊":"🔔"))?>

Заказов в доставке: <?=$job_count?$job_count:'Нет'?>

<?php if($courier->status_type=='idle'): ?>
ℹ Чтобы начать смену, нажмите "Отправить своё местоположение"
<?php endif; ?>