<?php if($user_list): ?>
<div class="segment">
    <div style="grid-template-columns: 30px 60px auto auto auto 30px;" class="item_table">
        <div style="display: contents" class="grid_header">
            <div>#</div>
            <div style="min-height: 30px;"></div>
            <div>Имя</div>
            <div>Телефон</div>
            <div>Маил</div>
            <div></div>
        </div>
        <?php foreach ($user_list as $i=>$user): ?>
            <div style="display: contents" class="<?= $user->is_disabled ? 'item_disabled' : '' ?> <?= $user->deleted_at ? 'item_deleted' : '' ?>">
                <div><?=$i+1?></div>
                <div style="height: 50px;">
                    <?php if($user->user_avatar_name): ?>
                    <img src="/img/avatar/<?=$user->user_avatar_name?>.png" style="height: 40px;max-width: 40px;" alt=" "/>
                    <?php endif; ?>
                </div>
                <div><?= $user->user_name ?> <?= $user->user_surname ?> <?= $user->user_middlename ?></div>
                <div><?= $user->user_phone ?></div>
                <div><?= $user->user_email ?></div>
                <div><i class="fa fa-pencil" data-id="<?=$user->user_id?>" data-action="edit"></i></div>
                <div style="grid-column: 1 / span 6;" class="item_card" id="itemCard<?=$user->user_id?>"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
Ничего не найдено
<?php endif; ?>