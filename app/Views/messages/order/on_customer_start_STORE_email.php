<h2>Заказ №{$order->order_id} от <?=getenv('app.title')?></h2>

<p>
    Добрый день.
</p>
<p>
    Вас приветствует служба доставки <?=getenv('app.title')?>. 
    Рады сообщить вам, что поступил заказ от клиента  для <?=$store->store_name?>. 
    Курьер направляется за заказом. Просьба подготовить его.
</p>
<?php if($order->order_description): ?>
<h3>Комментарий клиента</h3>
<p>
    <b><?=$order->order_description?></b>
</p>
<?php endif; ?>
<h3>Заказ</h3>
<table>
    <tr>
        <th></th>
        <th>Товар</th>
        <th style="text-align: right">Количество</th>
        <th style="text-align: right">Цена</th>
        <th style="text-align: right">Сумма</th>
        <th>Комментрий</th>
    </tr>
    <?php $active_count=0; foreach ($order->entries as $entry):?>
    <?php if($entry->deleted_at)continue; ?>
    <tr>
        <td><?=++$active_count?></td>
        <td><?=$entry->entry_text?></td>
        <td style="text-align: right"><?=$entry->entry_quantity?></td>
        <td style="text-align: right"><?=$entry->entry_price?></td>
        <td style="text-align: right"><?=$entry->entry_sum?></td>
        <td><?=$entry->entry_comment?></td>
    </tr>
    <?php endforeach;?>
    <tr>
        <td colspan="5">Сумма</td>
        <td><?=$order->order_sum_total?></td>
    </tr>
</table>
<p>
    С уважением, команда <?=getenv('app.title')?>.
</p>