<?php 
    function dmyt( $iso ){
        if( !$iso ){
            return "";
        }
        $expl= explode('-', str_replace(' ', '-', $iso));
        return "$expl[2].$expl[1].$expl[0] ".($expl[3]??'');
    }
?>
<div  style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 2fr" class="card_form">
                <div>Название</div>
                <div class="form_value">
                    <?= $store->store_name ?>
                </div>

                <div>
                    <?php if (sudo()): ?>
                        <a href="javascript:ItemList.approve(<?= $store->store_id ?>,'store_name')"><div class="fa fa-check" style="color:green"></div></a>
                    <?php endif; ?>
                    Новое название 
                </div>
                <div>
                    <input type="text" name="store_name_new.<?= $store->store_id ?>" value="<?= $store->store_name_new ?>" minlength="3"/>
                </div>


                <div>Описание</div>
                <div class="form_value">
                    <?= $store->store_description ?>
                </div>

                <div>
                    <?php if (sudo()): ?>
                        <a href="javascript:ItemList.approve(<?= $store->store_id ?>,'store_description')"><div class="fa fa-check" style="color:green"></div></a>
                    <?php endif; ?>
                    Новое Описание
                </div>
                <div>
                    <textarea name="store_description_new.<?= $store->store_id ?>" minlength="10"><?= $store->store_description_new ?></textarea>
                </div>
                <div>Предпирятие</div>
                <div class="form_value">
                    <?= $store->store_company_name ?>
                </div>

                <div>
                    <?php if (sudo()): ?>
                        <a href="javascript:ItemList.approve(<?= $store->store_id ?>,'store_company_name')"><div class="fa fa-check" style="color:green"></div></a>
                    <?php endif; ?>
                    Новое Предпр.
                </div>
                <div>
                    <input type="text" name="store_company_name_new.<?= $store->store_id ?>" value="<?= $store->store_company_name_new ?>" minlength="3"/>
                </div>
                
                <div>ИНН</div>
                <div>
                    <input type="number" name="store_tax_num.<?= $store->store_id ?>" value="<?= $store->store_tax_num ?>"/>
                </div>
                
                 <div>Телефон</div>
                <div>
                    <input type="tel" name="store_phone.<?= $store->store_id ?>" value="<?= $store->store_phone ?>"/>
                </div>


                <div>Емаил</div>
                <div>
                    <input type="email" name="store_email.<?= $store->store_id ?>" value="<?= $store->store_email ?>"/>
                </div>
               
                <div>Мин заказ</div>
                <div>
                    <input type="number" name="store_minimal_order.<?= $store->store_id ?>" value="<?= $store->store_minimal_order ?>"/>
                </div>
                
                <div>Подготовка заказа</div>
                <div>
                    <input type="number" name="store_time_preparation.<?= $store->store_id ?>" value="<?= $store->store_time_preparation ?>"/>
                </div>

            </div>
            <div style="display:grid;grid-template-columns:1fr 2fr" class="card_form">
                
                <div>Время работы</div>
                <div>
                    <br>ПН 
                    <input type="number" name="store_time_opens_0.<?= $store->store_id ?>" value="<?= $store->store_time_opens_0 ?>" style="width:80px" step="1" min="00" max="23"/>
                    -
                    <input type="number" name="store_time_closes_0.<?= $store->store_id ?>" value="<?= $store->store_time_closes_0 ?>" style="width:80px" step="1" min="00" max="23"/>
                    <br>ВТ
                    <input type="number" name="store_time_opens_1.<?= $store->store_id ?>" value="<?= $store->store_time_opens_1 ?>" style="width:80px" step="1" min="00" max="23"/>
                    -
                    <input type="number" name="store_time_closes_1.<?= $store->store_id ?>" value="<?= $store->store_time_closes_1 ?>" style="width:80px" step="1" min="00" max="23"/>
                    <br>СР
                    <input type="number" name="store_time_opens_2.<?= $store->store_id ?>" value="<?= $store->store_time_opens_2 ?>" style="width:80px" step="1" min="00" max="23"/>
                    -
                    <input type="number" name="store_time_closes_2.<?= $store->store_id ?>" value="<?= $store->store_time_closes_2 ?>" style="width:80px" step="1" min="00" max="23"/>
                    <br>ЧТ 
                    <input type="number" name="store_time_opens_3.<?= $store->store_id ?>" value="<?= $store->store_time_opens_3 ?>" style="width:80px" step="1" min="00" max="23"/>
                    -
                    <input type="number" name="store_time_closes_3.<?= $store->store_id ?>" value="<?= $store->store_time_closes_3 ?>" style="width:80px" step="1" min="00" max="23"/>
                    <br>ПТ 
                    <input type="number" name="store_time_opens_4.<?= $store->store_id ?>" value="<?= $store->store_time_opens_4 ?>" style="width:80px" step="1" min="00" max="23"/>
                    -
                    <input type="number" name="store_time_closes_4.<?= $store->store_id ?>" value="<?= $store->store_time_closes_4 ?>" style="width:80px" step="1" min="00" max="23"/>
                    <br>СБ 
                    <input type="number" name="store_time_opens_5.<?= $store->store_id ?>" value="<?= $store->store_time_opens_5 ?>" style="width:80px" step="1" min="00" max="23"/>
                    -
                    <input type="number" name="store_time_closes_5.<?= $store->store_id ?>" value="<?= $store->store_time_closes_5 ?>" style="width:80px" step="1" min="00" max="23"/>
                    <br>ВС
                    <input type="number" name="store_time_opens_6.<?= $store->store_id ?>" value="<?= $store->store_time_opens_6 ?>" style="width:80px" step="1" min="00" max="23"/>
                    -
                    <input type="number" name="store_time_closes_6.<?= $store->store_id ?>" value="<?= $store->store_time_closes_6 ?>" style="width:80px" step="1" min="00" max="23"/>
                </div>

                <div>Отключен</div>
                <div>
                    <input type="checkbox" name="is_disabled.<?= $store->store_id ?>" <?= $store->is_disabled ? 'checked' : '' ?>/>
                </div>
                
                <div>Работает</div>
                <div>
                    <input type="checkbox" name="is_working.<?= $store->store_id ?>" <?= $store->is_working ? 'checked' : '' ?>/>
                </div>

                <div>Создан</div>
                <div>
                    <?=dmyt($store->created_at)?> 
                </div>

                <div>Изменен</div>
                <div>
                    <?=dmyt($store->updated_at)?> 
                </div>

                <div>Удаление</div>
                <div>
                    <?php if($store->deleted_at): ?>
                        <?=dmyt($store->deleted_at)?>
                        <i class="fa fa-trash" style="color:red" onclick="ItemList.purgeItem(<?= $store->store_id ?>)" title="Окончательно удалить"></i>
                        <i class="fas fa-trash-restore" onclick="ItemList.undeleteItem(<?= $store->store_id ?>)" title="Восстановить"></i>
                    <?php else: ?>
                        <i class="fa fa-trash" onclick="ItemList.deleteItem(<?= $store->store_id ?>)" title="Удалить"></i> удалить
                    <?php endif; ?>
                </div>

                <div>Группы</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
                    <?php foreach ($store_group_list as $group): ?>
                    <div>
                        <input type="checkbox" value="<?= $group->group_id ?>" name="group_id.<?= $store->store_id ?>.<?= $group->group_id ?>" <?= in_array($group->group_id, explode(',', $store->member_of_groups->group_ids)) ? 'checked' : '' ?>/>
                        <?= $group->group_name ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

            <div class="image_list">
                <?php foreach ($store->images as $image):?>
                <div style="background-image: url(/image/get.php/<?= $image->image_hash ?>.160.90.webp);"
                     class=" <?= $image->is_disabled ? 'disabled' : '' ?> <?= $image->deleted_at ? 'deleted' : '' ?>">
                    
                    <a href="javascript:ItemList.imageOrder(<?= $image->image_id ?>,'up')"><div class="fa fa-arrow-left" style="color:black"></div></a>
                    <?php if ( sudo() && $image->is_disabled ): ?>
                    <a href="javascript:ItemList.imageApprove(<?= $image->image_id ?>)"><div class="fa fa-check" style="color:green"></div></a>
                    <?php endif; ?>
                    <a href="javascript:ItemList.imageDelete(<?= $image->image_id ?>)"><div class="fa fa-trash" style="color:red"></div></a>
                    <a href="/image/get.php/<?= $image->image_hash ?>.1024.1024.webp" target="imagepreview"><div class="fa fa-eye" style="color:blue"></div></a>
                    <a href="javascript:ItemList.imageOrder(<?= $image->image_id ?>,'down')"><div class="fa fa-arrow-right" style="color:black"></div></a>
                    <br><br>
                    <?= $image->is_disabled ? 'Ждет одобрения' : '' ?>
                    
                </div>
                <?php endforeach; ?>
                <div class="vcenter">
                    <a href="javascript:ItemList.fileUploadInit(<?= $store->store_id ?>)">Загрузить <span class="fa fa-plus"></span></a>
                </div>
            </div>
</div>