    <?=($user_list?'':'No results found')?>
    <?php foreach($user_list as $user):?>
    <h2><?=$user->user_surname?> <?=$user->user_name?></h2>
    <div class="segment <?=$user->deleted_at?'item_deleted':''?> <?=$user->is_disabled?'item_disabled':''?>" style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 2fr">
            
            <div>Имя</div>
            <div>
                <input type="text" name="user_name.<?=$user->user_id?>" value="<?=$user->user_name?>"/>
            </div>

            <div>Фамилия</div>
            <div>
                <input type="text" name="user_surname.<?=$user->user_id?>" value="<?=$user->user_surname?>"/>
            </div>

            <div>Отчество</div>
            <div>
                <input type="text" name="user_middlename.<?=$user->user_id?>" value="<?=$user->user_middlename?>"/>
            </div>

            <div>Телефон</div>
            <div>
                <input type="tel" name="user_phone.<?=$user->user_id?>" value="<?=$user->user_phone?>"/>
            </div>


            <div>Емаил</div>
            <div>
                <input type="email" name="user_email.<?=$user->user_id?>" value="<?=$user->user_email?>"/>
            </div>

            <div>Изображение</div>
            <div>
                <?php if($user->user_image??0):?>
                <img src="img/get.php/100_100_<?=$user->user_image?>.jpg">
                <?php endif;?>
                <button type="button">Upload</button>
                <button type="button">Delete</button>
            </div>


        </div>
        <div style="display:grid;grid-template-columns:1fr 2fr">

            <div>Телефон подтвержден</div>
            <div>
                <input type="checkbox" name="user_phone_verified.<?=$user->user_id?>" <?=$user->user_phone_verified?'checked':''?>/>
            </div>


            <div>Емаил подтвержден</div>
            <div>
                <input type="checkbox" name="user_email_verified.<?=$user->user_id?>" <?=$user->user_email_verified?'checked':''?>/>
            </div>

            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?=$user->user_id?>" <?=$user->is_disabled?'checked':''?>/>
            </div>

            <div>Вход</div>
            <div>
                <input type="date" readonly="readonly" name="signed_in_at.<?=$user->user_id?>.date" value="<?php $date_time=explode(' ',$user->signed_in_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="signed_in_at.<?=$user->user_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Выход</div>
            <div>
                <input type="date" readonly="readonly" name="signed_out_at.<?=$user->user_id?>.date" value="<?php $date_time=explode(' ',$user->signed_out_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="signed_out_at.<?=$user->user_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Создан</div>
            <div>
                <input type="date" readonly="readonly" name="created_at.<?=$user->user_id?>.date" value="<?php $date_time=explode(' ',$user->created_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="created_at.<?=$user->user_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Изменен</div>
            <div>
                <input type="date" readonly="readonly" name="modified_at.<?=$user->user_id?>.date" value="<?php $date_time=explode(' ',$user->modified_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="modified_at.<?=$user->user_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Удален</div>
            <div>
                <input type="date" readonly="readonly" name="deleted_at.<?=$user->user_id?>.date" value="<?php $date_time=explode(' ',$user->deleted_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="deleted_at.<?=$user->user_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>
            
            <div>Группы</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
                <?php foreach($user_group_list as $user_group ):?>
                <div>
                    <input type="checkbox" name="group_id.<?=$user->user_id?>.<?=$user_group->group_id?>" <?=in_array($user_group->group_id,explode(',',$user->member_of_groups->group_ids))?'checked':''?>/>
                    <?=$user_group->group_name?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>
    <div class="user_card_actions" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr 1fr">
        <div>
            <button type="button" onclick="ItemList.deleteItem(<?=$user->user_id?>)">Удалить пользователя</button>
        </div>
        <div>
            <button type="button" onclick="ItemList.undeleteItem(<?=$user->user_id?>)">Восстановить пользователя</button>
        </div>
    </div>
    <hr style="border:1px inset #ccc">
    <?php endforeach;?>
