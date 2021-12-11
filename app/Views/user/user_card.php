<?php 
    function dmyt( $iso ){
        if( !$iso ){
            return "";
        }
        $expl= explode('-', str_replace(' ', '-', $iso));
        return "$expl[2].$expl[1].$expl[0] ".($expl[3]??'');
    }
?>
<script>
    User={
        user_id:'<?=$user->user_id?>',
        address:{
            pick:function(coordsStart,location_type_id){
                App.loadWindow('Location/pickerModal',{coordsStart}).progress(function(status,data){
                    if(status==='selected'){
                        data.location_type_id=location_type_id;
                        data.loc_altitude=data.coordsSelected[0];
                        data.loc_longitude=data.coordsSelected[1];
                        $.post('User/locationSave',data).done(function(){
                            //set updated address to card
                        });
                    }
                });
            }
        }
    };
</script>




<div  style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 2fr" class="card_form">
            
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
            
            <div>Адреса</div>
            <div>
                <div>Домашний: --- <i class="fa fa-map-marker" aria-hidden="true" onclick="">Выбрать</i></div>
                <div>Рабочий: --- <i class="fa fa-map-marker" aria-hidden="true">Выбрать</i></div>
            </div>


        </div>
        <div style="display:grid;grid-template-columns:1fr 2fr" class="card_form">

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
                <?=dmyt($user->signed_in_at)?> 
            </div>

            <div>Выход</div>
            <div>
                <?=dmyt($user->signed_out_at)?> 
            </div>

            <div>Создан</div>
            <div>
                <?=dmyt($user->created_at)?> 
            </div>

            <div>Изменен</div>
            <div>
                <?=dmyt($user->updated_at)?> 
            </div>

                <div>Удаление</div>
                <div>
                    <?php if($user->deleted_at): ?>
                        <?=dmyt($user->deleted_at)?>
                        <i class="fa fa-trash" style="color:red" onclick="ItemList.purgeItem(<?= $user->user_id ?>)" title="Окончательно удалить"></i>
                        <i class="fas fa-trash-restore" onclick="ItemList.undeleteItem(<?= $user->user_id ?>)" title="Восстановить"></i>
                    <?php else: ?>
                        <i class="fa fa-trash" onclick="ItemList.deleteItem(<?= $user->user_id ?>)" title="Удалить"></i> удалить
                    <?php endif; ?>
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