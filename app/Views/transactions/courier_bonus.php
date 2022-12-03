Премия за выполнение услуги доставки Заказ #<?= $order_basic->order_id?> 
<?php if($costSum):?>
    Фикс:<?=$costSum?>
<?php endif;?>
<?php if($feeSum):?>
    %:<?=$feeSum?>
<?php endif;?>
<?php if($compensationSum):?>
    Компенсация за <?=$distance_km?>км:<?=$compensationSum?>
<?php endif;?>