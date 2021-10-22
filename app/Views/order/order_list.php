<?php if($order_list): ?>
<div class="segment">
    <div style="grid-template-columns: 30px 60px auto auto auto 30px;" class="item_table">
        <div>#</div>
        <div style="min-height: 30px;"></div>
        <div>Название</div>
        <div>Количество</div>
        <div>Цена</div>
        <div></div>
        <?php foreach ($order_list as $i=>$order): ?>
            <div style="display: contents" class="<?= $order->is_disabled ? 'item_disabled' : '' ?> <?= $order->deleted_at ? 'item_deleted' : '' ?>">
                <div><?=$i+1?></div>
                <div style="height: 50px;">
                    <img src="/image/get.php/<?= $order->image_hash ?>.50.50.webp" alt=" "/>
                </div>
                <div><?= $order->order_name ?></div>
                <div><?= $order->order_quantity ?></div>
                <div><?= $order->order_price ?></div>
                <div><i class="fa fa-pencil" data-id="<?=$order->order_id?>" data-action="edit"></i></div>
                <div style="grid-column: 1 / span 6;" class="item_card" id="itemCard<?=$order->order_id?>"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
Заказы не найдены
<?php endif; ?>