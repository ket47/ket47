    <?=($store_list?'':'No results found')?>
    <?php foreach($store_list as $store):?>
    <h2><?=$store->store_name?></h2>
    <div class="segment <?=$store->deleted_at?'item_deleted':''?>  <?=$store->is_disabled?'item_disabled':''?>" style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 3fr">
            <div>Название</div>
            <div>
                <input type="text" name="store_name.<?=$store->store_id?>" value="<?=$store->store_name?>"/>
            </div>


            <div>Телефон</div>
            <div>
                <input type="tel" name="store_phone.<?=$store->store_id?>" value="<?=$store->store_phone?>"/>
            </div>


            <div>Емаил</div>
            <div>
                <input type="email" name="store_email.<?=$store->store_id?>" value="<?=$store->store_email?>"/>
            </div>


            <div>Комментарий</div>
            <div>
                <textarea name="store_description.<?=$store->store_id?>"><?=$store->store_description?></textarea>
            </div>

        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr">

            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?=$store->store_id?>" <?=$store->is_disabled?'checked':''?>/>
            </div>

            <div>Создан</div>
            <div>
                <input type="date" readonly="readonly" name="created_at.<?=$store->store_id?>.date" value="<?php $date_time=explode(' ',$store->created_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="created_at.<?=$store->store_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Изменен</div>
            <div>
                <input type="date" readonly="readonly" name="modified_at.<?=$store->store_id?>.date" value="<?php $date_time=explode(' ',$store->modified_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="modified_at.<?=$store->store_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Удален</div>
            <div>
                <input type="date" readonly="readonly" name="deleted_at.<?=$store->store_id?>.date" value="<?php $date_time=explode(' ',$store->deleted_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="deleted_at.<?=$store->store_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>
            
            <div>Группы</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
                <?php foreach($store_group_list as $store_group ):?>
                <div>
                    <input type="checkbox" name="store_group_id.<?=$store->store_id?>.<?=$store_group->store_group_id?>" <?=in_array($store_group->store_group_id,explode(',',$store->member_of_groups->store_group_ids))?'checked':''?>/>
                    <?=$store_group->store_group_name?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>
    <div class="store_card_actions" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr 1fr">
        <div>
            <button type="button" onclick="ItemList.deleteItem(<?=$store->store_id?>)">Удалить</button>
        </div>
        <div>
            <button type="button" onclick="ItemList.undeleteItem(<?=$store->store_id?>)">Восстановить</button>
        </div>
    </div>
    <hr>
    <?php endforeach;?>
