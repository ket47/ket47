    <?=($store_list?'':'No results found')?>
    <?php foreach($store_list as $store):?>
    <h2><?=$store->store_surname?> <?=$store->store_name?></h2>
    <div class="segment <?=$store->deleted_at?'store_card_deleted':''?>" style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 3fr">
            <div>Имя</div>
            <div>
                <input type="text" name="store_name.<?=$store->store_id?>" value="<?=$store->store_name?>"/>
            </div>

            <div>Фамилия</div>
            <div>
                <input type="text" name="store_surname.<?=$store->store_id?>" value="<?=$store->store_surname?>"/>
            </div>

            <div>Отчество</div>
            <div>
                <input type="text" name="store_middlename.<?=$store->store_id?>" value="<?=$store->store_middlename?>"/>
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
                <textarea name="store_comment.<?=$store->store_id?>"><?=$store->store_comment?></textarea>
            </div>

        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr">

            <div>Телефон подтвержден</div>
            <div>
                <input type="checkbox" name="store_phone_verified.<?=$store->store_id?>" <?=$store->store_phone_verified?'checked':''?>/>
            </div>


            <div>Емаил подтвержден</div>
            <div>
                <input type="checkbox" name="store_email_verified.<?=$store->store_id?>" <?=$store->store_email_verified?'checked':''?>/>
            </div>

            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?=$store->store_id?>" <?=$store->is_disabled?'checked':''?>/>
            </div>

            <div>Вход</div>
            <div>
                <input type="date" readonly="readonly" name="signed_in_at.<?=$store->store_id?>.date" value="<?php $date_time=explode(' ',$store->signed_in_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="signed_in_at.<?=$store->store_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Выход</div>
            <div>
                <input type="date" readonly="readonly" name="signed_out_at.<?=$store->store_id?>.date" value="<?php $date_time=explode(' ',$store->signed_out_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="signed_out_at.<?=$store->store_id?>.time" value="<?php echo $date_time[1]??''?>"/>
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
            <button type="button" onclick="StoreList.deleteStore(<?=$store->store_id?>)">Удалить пользователя</button>
        </div>
        <div>
            <button type="button" onclick="StoreList.undeleteStore(<?=$store->store_id?>)">Восстановить пользователя</button>
        </div>
    </div>
    <hr>
    <?php endforeach;?>
