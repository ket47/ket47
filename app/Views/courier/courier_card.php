<?php 
    function dmyt( $iso ){
        if( !$iso ){
            return "";
        }
        $expl= explode('-', str_replace(' ', '-', $iso));
        return "$expl[2].$expl[1].$expl[0] ".($expl[3]??'');
    }
    //include APPPATH.'Views/home/header.php';
?>
<div  style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 2fr" class="card_form">
            <div>Транспорт</div>
            <div>
                <input type="text" name="courier_vehicle.<?=$courier->courier_id?>" value="<?=$courier->courier_vehicle?>"/>
            </div>
            <div>ИНН</div>
            <div>
                <input type="text" name="courier_tax_num.<?=$courier->courier_id?>" value="<?=$courier->courier_tax_num?>"/>
            </div>
            <div>Коммент</div>
            <div>
                <textarea name="courier_comment.<?=$courier->courier_id?>"><?=$courier->courier_comment?></textarea>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 2fr" class="card_form">


            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?=$courier->courier_id?>" <?=$courier->is_disabled?'checked':''?>/>
            </div>

            <div>Удаление</div>
            <div>
                <?php if($courier->deleted_at): ?>
                    <?=dmyt($courier->deleted_at)?>
                    <i class="fa fa-trash" style="color:red" onclick="ItemList.purgeItem(<?= $courier->courier_id ?>)" title="Окончательно удалить"></i>
                    <i class="fas fa-trash-restore" onclick="ItemList.undeleteItem(<?= $courier->courier_id ?>)" title="Восстановить"></i>
                <?php else: ?>
                    <i class="fa fa-trash" onclick="ItemList.deleteItem(<?= $courier->courier_id ?>)" title="Удалить"></i> удалить
                <?php endif; ?>
            </div>
            
            <div>Статус</div>
            <div>
                <select name="group_id.<?= $courier->courier_id ?>.<?= $courier->member_of_groups->group_ids ?>">
                    <option value="0">---</option>
                    <?php foreach ($courier_group_list as $courier_group):?>
                    <option value="<?= $courier_group->group_id ?>" <?= in_array($courier_group->group_id, explode(',', $courier->member_of_groups->group_ids)) ? 'selected' : '' ?>><?= $courier_group->group_name ?></option>
                    <?php endforeach;?>
                </select>
            </div>
        </div>
    </div>
    
    <div>
        <h3>Изображения </h3>
        <div class="image_list">
                <?php if (isset($courier->images)): foreach ($courier->images as $image): ?>
                    <div style="background-image: url(/image/get.php/<?= $image->image_hash ?>.160.90.webp);"
                         class=" <?= $image->is_disabled ? 'disabled' : '' ?> <?= $image->deleted_at ? 'deleted' : '' ?>">
                        <a href="javascript:ItemList.imageOrder(<?= $image->image_id ?>,'up')"><div class="fa fa-arrow-left" style="color:black"></div></a>
                        <?php if (sudo() && $image->is_disabled): ?>
                        <a href="javascript:ItemList.imageApprove(<?= $image->image_id ?>)"><div class="fa fa-check" style="color:green"></div></a>
                        <?php endif; ?>
                        <a href="javascript:ItemList.imageDelete(<?= $image->image_id ?>)"><div class="fa fa-trash" style="color:red"></div></a>
                        <a href="/image/get.php/<?= $image->image_hash ?>.1024.1024.webp" target="imagepreview"><div class="fa fa-eye" style="color:blue"></div></a>
                        <a href="javascript:ItemList.imageOrder(<?= $image->image_id ?>,'down')"><div class="fa fa-arrow-right" style="color:black"></div></a>
                        <br><br>
                        <?=$image->is_disabled ? 'Ждет одобрения' : '' ?>
                    </div>
            <?php endforeach; endif; ?>
            <div class="vcenter">
                <a href="javascript:ItemList.fileUploadInit(<?= $courier->courier_id ?>)">Загрузить <span class="fa fa-plus"></span></a>
            </div>
        </div>
    </div>

</div>