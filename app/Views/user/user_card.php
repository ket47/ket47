<div  style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
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

            <div>Аватар</div>
            <div>
                <label for="user_avatar_man">
                    <img src="/img/avatar/man.png" style="width:48px;height: auto"/>
                </label>
                <input type="radio" id="user_avatar_man" value="man" name="user_avatar_name.<?=$user->user_id?>" <?=$user->user_avatar_name=='man'?'checked':''?>/>
                &nbsp;&nbsp;&nbsp;
                <label for="user_avatar_woman">
                    <img src="/img/avatar/woman.png" style="width:48px;height: auto"/>
                </label>
                <input type="radio" id="user_avatar_woman"  value="woman" name="user_avatar_name.<?=$user->user_id?>" <?=$user->user_avatar_name=='woman'?'checked':''?>/>
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
                <input type="date" readonly="readonly" name="updated_at.<?=$user->user_id?>.date" value="<?php $date_time=explode(' ',$user->updated_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="updated_at.<?=$user->user_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Удален</div>
            <div>
                <input type="date" readonly="readonly" name="deleted_at.<?=$user->user_id?>.date" value="<?php $date_time=explode(' ',$user->deleted_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="deleted_at.<?=$user->user_id?>.time" value="<?php echo $date_time[1]??''?>"/>
                <button type="button" onclick="ItemList.deleteItem(<?=$user->user_id?>)">Удалить</button>
                <button type="button" onclick="ItemList.undeleteItem(<?=$user->user_id?>)">Восстановить</button>
            </div>
            
            <div>Группы</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
                <?php foreach($user_group_list as $user_group ):?>
                <div>
                    <input type="checkbox" 
                           value="<?=$user_group->group_id?>"
                           name="group_id.<?=$user->user_id?>.<?=$user_group->group_id?>"
                           <?=in_array($user_group->group_id,explode(',',$user->member_of_groups->group_ids))?'checked':''?>
                           onclick="return (<?='admin'==$user_group->group_type?1:0?> && !confirm('Вы уверены? У пользователя изменятся права админа!'))?false:true;"
                           />
                    <?=$user_group->group_name?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>