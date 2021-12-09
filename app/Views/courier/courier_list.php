<?php if($courier_list): ?>
<div class="segment">
    <div style="grid-template-columns: 30px 60px auto auto auto auto 30px;" class="item_table">
        <div style="display: contents" class="grid_header">
            <div>#</div>
            <div style="min-height: 30px;"></div>
            <div>Имя</div>
            <div>Телефон</div>
            <div>Статус</div>
            <div>Заказ №</div>
            <div></div>
        </div>
        <?php foreach ($courier_list as $i=>$courier): ?>
            <div style="display: contents" class="<?= $courier->is_disabled ? 'item_disabled' : '' ?> <?= $courier->deleted_at ? 'item_deleted' : '' ?>">
                <div><?=$i+1?></div>
                <div style="height: 50px;">
                    <?php if($courier->courier_photo_image_hash): ?>
                    <img src="/image/get.php/<?=$courier->courier_photo_image_hash?>.40.40.webp" style="height: 40px;max-width: 40px;" alt=" "/>
                    <?php else:?>
                    <img src="/img/avatar/<?=$courier->user_avatar_name??''?>.png" style="height: 40px;max-width: 40px;" alt=" "/>
                    <?php endif; ?>
                </div>
                <div><?= $courier->user_name ?></div>
                <div><?= $courier->user_phone ?></div>
                <div>
                    <?php if($courier->group_image_hash): ?>
                    <img src="/image/get.php/<?=$courier->group_image_hash?>.40.40.png" style="max-height: 40px;max-width: 40px;" alt=" "/>
                    <?php endif; ?>
                    <?= $courier->group_name ?>
                </div>
                <div><?=$courier->current_order_id?></div>
                <div><i class="fa fa-pencil" data-id="<?=$courier->courier_id?>" data-action="edit"></i></div>
                <div style="grid-column: 1 / span 7;" class="item_card" id="itemCard<?=$courier->courier_id?>"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
Ничего не найдено
<?php endif; ?>