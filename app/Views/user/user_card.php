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
        user_id:'<?=$user->user_id??0?>',
        address:{
            init:function(){
                $("#address_manager").on('click',function(e){
                    let $button=$(e.target);
                    let action=$button.parent().data('action') || $button.parent().parent().data('action');
                    let location_group_id=$button.parent().data('group_id') || $button.parent().parent().data('group_id');
                    if( action==='add_location' ){
                        User.address.pick([],location_group_id);
                    } else 
                    if( action==='delete_location' && confirm("Удалить адрес?") ){
                        let location_id=$button.parent().data('location_id');
                        $.post('/User/locationDelete',{location_id}).done(function(){
                            ItemList.reloadItem();
                        });
                    }
                });
            },
            pick:function(coordsStart,location_group_id){
                App.loadWindow('/Location/pickerModal',{coordsStart}).progress(function(status,data){
                    if(status==='selected'){
                        let request={};
                        request.location_holder_id=User.user_id;
                        request.location_group_id=location_group_id;
                        request.location_latitude=data.coordsSelected[0];
                        request.location_longitude=data.coordsSelected[1];
                        request.location_address=data.addressSelected;
                        $.post('/User/locationCreate',request).done(function(){
                            ItemList.reloadItem();
                        });
                    }
                });
            }
        }
    };
    User.address.init();
</script>

<style>
    #address_manager{
        display: grid;
        grid-template-columns: 32px auto 32px;
        width: 100%;
    }
    #address_manager .row>div{
        background-color: white;
        padding: 3px;
    }
    #address_manager .row>div:nth-child(2){
        border-bottom: 1px solid #ccc;
    }
    #address_manager .row:last-child>div{
        border-bottom: none !important;
    }
</style>


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
            
            
            
            
        <div id="address_manager" class="item_table">
            <?php foreach( $user->location_list as $location ): ?>
            <div style="display:contents;" class="row">
                <div><img src="/image/get.php/<?= $location->image_hash ?>.24.24.webp" style="width:24px;height:auto;"/></div>
                <div style="font-size:11px;line-height:1em;"><?=$location->location_address?></div>
                <div data-action="delete_location" data-location_id="<?=$location->location_id?>"><i class="fa fa-trash" aria-hidden="true" style="float:right"></i></div>
            </div>
            <?php endforeach; ?>
            <div style="grid-column: 1 / span 3;">Добавить адрес</div>

            <?php foreach( $location_group_list as $loc_group ): ?>
            <div style="display:contents;" class="row" data-action="add_location" data-group_id="<?=$loc_group->group_id?>" class="primary">
                <div><img src="/image/get.php/<?= $loc_group->image_hash ?>.24.24.webp" style="width:24px;height:auto;"/></div>
                <div style="font-size:11px;line-height:1em;">Добавить <?=$loc_group->group_name?> адрес</div>
                <div><i class="fa fa-plus" aria-hidden="true" style="float:right"></i></div>
            </div>
            <?php endforeach; ?>
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