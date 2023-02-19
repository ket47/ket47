Премия за выполнение услуги доставки Заказ №<?= $order_basic->order_id?> 
<?php if($costSum):?>
    бонус выезд:<?=$costSum?>;
<?php endif;?>
<?php if($feeSum):?>
    бонус от суммы:<?=$feeSum?>;
<?php endif;?>
<?php if($compensationSum):?>
    Компенсация за <?=$distance_km?>км:<?=$compensationSum?>;
<?php endif;?>