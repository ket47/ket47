<div  style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 3fr">
            <div>Продавец</div>
            <div>
                <?=$order->store->store_name ?> (<?=$order->store->store_phone ?>, <?=$order->store->store_email ?>)
            </div>


            <div>Покупатель</div>
            <div>
                <?= $order->customer->user_name?> (<?=$order->customer->user_phone ?>, <?=$order->customer->user_email ?>)
            </div>


            <div>Комментарий</div>
            <div>
                <?= $order->order_description?>
            </div>

            <div>Количество</div>
            <div>
                <?= $order->order_shipping_fee?>
            </div>

            <div>Вес кг/единица</div>
            <div>
                <?= $order->order_tax?>
            </div>

        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr">


            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?= $order->order_id ?>" <?= $order->is_disabled ? 'checked' : '' ?>/>
            </div>

            <div>Создан</div>
            <div>
                <input type="date" readonly="readonly" name="created_at.<?= $order->order_id ?>.date" value="<?php $date_time = explode(' ', $order->created_at); echo $date_time[0] ?? ''?>"/>
                <input type="time" readonly="readonly" name="created_at.<?= $order->order_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
            </div>

            <div>Изменен</div>
            <div>
                <input type="date" readonly="readonly" name="updated_at.<?= $order->order_id ?>.date" value="<?php $date_time = explode(' ', $order->updated_at);
                   echo $date_time[0] ?? ''
                   ?>"/>
                <input type="time" readonly="readonly" name="updated_at.<?= $order->order_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
            </div>

            <div>Удален</div>
            <div>
                <input type="date" readonly="readonly" name="deleted_at.<?= $order->order_id ?>.date" value="<?php $date_time = explode(' ', $order->deleted_at);
                   echo $date_time[0] ?? ''
                   ?>"/>
                <input type="time" readonly="readonly" name="deleted_at.<?= $order->order_id ?>.time" value="<?php echo $date_time[1] ?? '' ?>"/>
                <button type="button" onclick="ItemList.deleteItem(<?= $order->order_id ?>)">Удалить</button>
                <button type="button" onclick="ItemList.undeleteItem(<?= $order->order_id ?>)">Восстановить</button>
            </div>

            <div>Группа</div>
            <div>
                <select name="group_id.<?= $order->order_id ?>.<?= $order->member_of_groups->group_ids ?>">
                    <option value="0">---</option>
                    <?php foreach ($order_group_list as $order_group):?>
                    <?php if( !$order_group->group_parent_id ):?>
                    <optgroup label="<?= $order_group->group_name ?>">
                    <?php continue; endif; ?>
                    <option value="<?= $order_group->group_id ?>" <?= in_array($order_group->group_id, explode(',', $order->member_of_groups->group_ids)) ? 'selected' : '' ?>><?= $order_group->group_name ?></option>
                    <?php endforeach;?>
                </select>
            </div>
        </div>
    </div>


    <div>
        <h3>Изображения </h3>
        <div class="image_list">
                <?php if (isset($order->images)): foreach ($order->images as $image): ?>
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
                <a href="javascript:ItemList.fileUploadInit(<?= $order->order_id ?>)">Загрузить <span class="fa fa-plus"></span></a>
            </div>
        </div>
    </div>
</div>