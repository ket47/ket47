<?php if($product_list): ?>
<div class="segment">
    <div style="grid-template-columns: 30px 60px auto auto auto 30px;" class="item_table">
        <div>#</div>
        <div style="min-height: 30px;"></div>
        <div>Название</div>
        <div>Количество</div>
        <div>Цена</div>
        <div></div>
        <?php foreach ($product_list as $i=>$product): ?>
            <div style="display: contents" class="<?= $product->is_disabled ? 'item_disabled' : '' ?> <?= $product->deleted_at ? 'item_deleted' : '' ?>">
                <div><?=$i+1?></div>
                <div style="height: 50px;">
                    <img src="/image/get.php/<?= $product->image_hash ?>.50.50.webp" alt=" "/>
                </div>
                <div><?= $product->product_name ?></div>
                <div><?= $product->product_quantity ?></div>
                <div><?= $product->product_price ?></div>
                <div><i class="fa fa-pencil" data-id="<?=$product->product_id?>" data-action="edit"></i></div>
                <div style="grid-column: 1 / span 6;" class="item_card" id="itemCard<?=$product->product_id?>"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
Товары не найдены
<?php endif; ?>