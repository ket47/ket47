<b>Заказ #<?=$order->order_id?> от <?=date('d.m.y H:i',strtotime($order->created_at))?></b>
<b>[<?=mb_strtoupper($order->stage_current_name, "utf-8")?>]</b>
<?php if($order->info->supplier_name??null):?>
Продавец: <i><?=$order->info->supplier_name?></i>
<?php endif;?>
Сумма заказа:<?=$order->order_sum_total?>