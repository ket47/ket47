<div  style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 3fr">
            <div>Название</div>
            <div>
                <input type="text" name="product_name.<?= $product->product_id ?>" value="<?= $product->product_name ?>"/>
            </div>


            <div>Код</div>
            <div>
                <input type="text" name="product_code.<?= $product->product_id ?>" value="<?= $product->product_code ?>"/>
            </div>


            <div>Цена</div>
            <div>
                <input type="number" name="product_price.<?= $product->product_id ?>" value="<?= $product->product_price ?>"/>
            </div>

            <div>Количество</div>
            <div>
                <input type="number" name="product_quantity.<?= $product->product_id ?>" value="<?= $product->product_quantity ?>" <?= $product->is_produced ? 'disabled' : '' ?>/>
            </div>

            <div>Вес кг</div>
            <div>
                <input type="number" name="product_weight.<?= $product->product_id ?>" value="<?= $product->product_weight ?>"/>
            </div>


            <div>Описание</div>
            <div>
                <textarea name="product_description.<?= $product->product_id ?>"><?= $product->product_description ?></textarea>
            </div>

        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr">

            <div>Производится</div>
            <div>
                <input type="checkbox" name="is_produced.<?= $product->product_id ?>" <?= $product->is_produced ? 'checked' : '' ?>/>
            </div>

            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?= $product->product_id ?>" <?= $product->is_disabled ? 'checked' : '' ?>/>
            </div>

            <div>Создан</div>
            <div>
                <input type="date" readonly="readonly" name="created_at.<?= $product->product_id ?>.date" value="<?php $date_time = explode(' ', $product->created_at); echo $date_time[0] ?? ''?>"/>
                <input type="time" readonly="readonly" name="created_at.<?= $product->product_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
            </div>

            <div>Изменен</div>
            <div>
                <input type="date" readonly="readonly" name="updated_at.<?= $product->product_id ?>.date" value="<?php $date_time = explode(' ', $product->updated_at);
                   echo $date_time[0] ?? ''
                   ?>"/>
                <input type="time" readonly="readonly" name="updated_at.<?= $product->product_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
            </div>

            <div>Удален</div>
            <div>
                <input type="date" readonly="readonly" name="deleted_at.<?= $product->product_id ?>.date" value="<?php $date_time = explode(' ', $product->deleted_at);
                   echo $date_time[0] ?? ''
                   ?>"/>
                <input type="time" readonly="readonly" name="deleted_at.<?= $product->product_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
                <button type="button" onclick="ItemList.deleteItem(<?= $product->product_id ?>)">Удалить</button>
                <button type="button" onclick="ItemList.undeleteItem(<?= $product->product_id ?>)">Восстановить</button>
            </div>

            <div>Группа</div>
            <div>
                <select name="group_id.<?= $product->product_id ?>.<?= $product->member_of_groups->group_ids ?>">
                    <option value="0">---</option>
                    <?php foreach ($product_group_list as $product_group):?>
                    <?php if( !$product_group->group_parent_id ):?>
                    <optgroup label="<?= $product_group->group_name ?>">
                    <?php continue; endif; ?>
                    <option value="<?= $product_group->group_id ?>" <?= in_array($product_group->group_id, explode(',', $product->member_of_groups->group_ids)) ? 'selected' : '' ?>><?= $product_group->group_name ?></option>
                    <?php endforeach;?>
                </select>
            </div>
        </div>
    </div>


    <div>
        <h3>Изображения </h3>
        <div class="image_list">
                <?php if (isset($product->images)): foreach ($product->images as $image): ?>
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
                <a href="javascript:ItemList.fileUploadInit(<?= $product->product_id ?>)">Загрузить <span class="fa fa-plus"></span></a>
            </div>
        </div>
    </div>
</div>