👑 <b>Админ, <?=$user->user_name?></b>
<?php if($delivery_heavy['delivery_heavy_level']>0): ?>
⛈️ Повышенная доставка №<?=$delivery_heavy['delivery_heavy_level']?>
<pre>
Повышение стоимости: +<?=$delivery_heavy['delivery_heavy_cost']?>

Бонус курьера:        <?=$delivery_heavy['delivery_heavy_bonus']?>
</pre>
<?php else: ?>
🌤️ Стоимость доставки нормальная
<?php endif; ?>