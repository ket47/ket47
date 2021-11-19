<?php if($store_list): ?>
<div class="segment">
    <div style="grid-template-columns: 30px 60px auto auto auto 30px;" class="item_table">
        <div style="display: contents" class="grid_header">
            <div>#</div>
            <div style="min-height: 30px;"></div>
            <div>Название</div>
            <div>Телефон</div>
            <div>Открыт</div>
            <div></div>
        </div>
        <?php foreach ($store_list as $i=>$store): ?>
            <div style="display: contents" class="<?= $store->is_disabled ? 'item_disabled' : '' ?> <?= $store->deleted_at ? 'item_deleted' : '' ?>">
                <div><?=$i+1?></div>
                <div style="height: 50px;">
                    <img src="/image/get.php/<?= $store->image_hash ?>.50.50.webp" alt=" "/>
                </div>
                <div data-field="store_name.<?=$store->store_id?>"><?= $store->store_name ?></div>
                <div data-field="store_phone.<?=$store->store_id?>"><?= $store->store_phone ?></div>
                <div data-field="is_opened.<?=$store->store_id?>" data-reload="1"><?= $store->is_opened?'Открыт':'Закрыт' ?></div>
                <div><i class="fa fa-pencil" data-id="<?=$store->store_id?>" data-action="edit"></i></div>
                <div style="grid-column: 1 / span 6;" class="item_card" id="itemCard<?=$store->store_id?>"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
Ничего не найдено
<?php endif; ?>