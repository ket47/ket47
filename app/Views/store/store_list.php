<?= ($store_list ? '' : 'No results found') ?>
<?php foreach ($store_list as $store): ?>
    <h2><?= $store->store_name ?></h2>
    <div class="segment <?= $store->deleted_at ? 'item_deleted' : '' ?>  <?= $store->is_disabled ? 'item_disabled' : '' ?>">
        <div style="display: grid;grid-template-columns:1fr 1fr">
            <div style="display:grid;grid-template-columns:1fr 3fr">
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


                <div>Телефон</div>
                <div>
                    <input type="tel" name="store_phone.<?= $store->store_id ?>" value="<?= $store->store_phone ?>"/>
                </div>


                <div>Емаил</div>
                <div>
                    <input type="email" name="store_email.<?= $store->store_id ?>" value="<?= $store->store_email ?>"/>
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
            </div>
            <div style="display:grid;grid-template-columns:1fr 3fr">
                
                <div>ИНН</div>
                <div>
                    <input type="number" name="store_tax_num.<?= $store->store_id ?>" value="<?= $store->store_tax_num ?>"/>
                </div>
                
                <div>Предпирятие</div>
                <div>
                    <input type="email" name="store_company_name.<?= $store->store_id ?>" value="<?= $store->store_company_name ?>"/>
                </div>


                <div>Отключен</div>
                <div>
                    <input type="checkbox" name="is_disabled.<?= $store->store_id ?>" <?= $store->is_disabled ? 'checked' : '' ?>/>
                </div>

                <div>Создан</div>
                <div>
                    <input type="date" readonly="readonly" name="created_at.<?= $store->store_id ?>.date" value="<?php $date_time = explode(' ', $store->created_at);
                echo $date_time[0] ?? '' ?>"/>
                    <input type="time" readonly="readonly" name="created_at.<?= $store->store_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
                </div>

                <div>Изменен</div>
                <div>
                    <input type="date" readonly="readonly" name="modified_at.<?= $store->store_id ?>.date" value="<?php $date_time = explode(' ', $store->modified_at);
                echo $date_time[0] ?? '' ?>"/>
                    <input type="time" readonly="readonly" name="modified_at.<?= $store->store_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
                </div>

                <div>Удален</div>
                <div>
                    <input type="date" readonly="readonly" name="deleted_at.<?= $store->store_id ?>.date" value="<?php $date_time = explode(' ', $store->deleted_at);
                echo $date_time[0] ?? '' ?>"/>
                    <input type="time" readonly="readonly" name="deleted_at.<?= $store->store_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
                    <button type="button" onclick="ItemList.deleteItem(<?= $store->store_id ?>)">Удалить</button>
                    <button type="button" onclick="ItemList.undeleteItem(<?= $store->store_id ?>)">Восстановить</button>
                </div>

                <div>Группы</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
                        <?php foreach ($store_group_list as $group): ?>
                        <div>
                            <input type="checkbox" name="group_id.<?= $store->store_id ?>.<?= $group->group_id ?>" <?= in_array($group->group_id, explode(',', $store->member_of_groups->group_ids)) ? 'checked' : '' ?>/>
                            <?= $group->group_name ?>
                        </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="segment secondary">
            <h3>Изображения </h3>
            
            <div class="image_list">
                <?php foreach ($store->images as $image):?>
                <div style="background-image: url(/image/get.php/<?= $image->image_hash ?>.160.90.webp);"
                     class=" <?= $image->is_disabled ? 'disabled' : '' ?>">
                    
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
        
    </div>
    <hr>
<?php endforeach; ?>
