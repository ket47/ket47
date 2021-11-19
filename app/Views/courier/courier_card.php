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
                <textarea name="courier_comment.<?=$courier->courier_id?>"><?=$courier->courier_vehicle?></textarea>
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
</div>