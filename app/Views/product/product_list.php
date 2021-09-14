    <?=($product_list?'':'No results found')?>
    <?php foreach($product_list as $product):?>
    <h2><?=$product->product_name?></h2>
    <div class="segment <?=$product->deleted_at?'item_deleted':''?>  <?=$product->is_disabled?'item_disabled':''?>" style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 3fr">
            <div>Название</div>
            <div>
                <input type="text" name="product_name.<?=$product->product_id?>" value="<?=$product->product_name?>"/>
            </div>


            <div>Код</div>
            <div>
                <input type="text" name="product_code.<?=$product->product_id?>" value="<?=$product->product_phone?>"/>
            </div>


            <div>Цена</div>
            <div>
                <input type="number" name="product_price.<?=$product->product_id?>" value="<?=$product->product_price?>"/>
            </div>

            <div>Количество</div>
            <div>
                <input type="number" name="product_quantity.<?=$product->product_id?>" value="<?=$product->product_quantity?>" <?=$product->is_produced?'disabled':''?>/>
            </div>

            <div>Вес кг</div>
            <div>
                <input type="number" name="product_weight.<?=$product->product_id?>" value="<?=$product->product_weight?>"/>
            </div>


            <div>Комментарий</div>
            <div>
                <textarea name="product_description.<?=$product->product_id?>"><?=$product->product_description?></textarea>
            </div>

        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr">

            <div>Производится</div>
            <div>
                <input type="checkbox" name="is_produced.<?=$product->product_id?>" <?=$product->is_produced?'checked':''?>/>
            </div>

            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?=$product->product_id?>" <?=$product->is_disabled?'checked':''?>/>
            </div>

            <div>Создан</div>
            <div>
                <input type="date" readonly="readonly" name="created_at.<?=$product->product_id?>.date" value="<?php $date_time=explode(' ',$product->created_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="created_at.<?=$product->product_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Изменен</div>
            <div>
                <input type="date" readonly="readonly" name="modified_at.<?=$product->product_id?>.date" value="<?php $date_time=explode(' ',$product->modified_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="modified_at.<?=$product->product_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>

            <div>Удален</div>
            <div>
                <input type="date" readonly="readonly" name="deleted_at.<?=$product->product_id?>.date" value="<?php $date_time=explode(' ',$product->deleted_at);echo $date_time[0]??''?>"/>
                <input type="time" readonly="readonly" name="deleted_at.<?=$product->product_id?>.time" value="<?php echo $date_time[1]??''?>"/>
            </div>
            
            <div>Группы</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr">
                <?php foreach($product_group_list as $product_group ):?>
                <div>
                    <input type="checkbox" name="product_group_id.<?=$product->product_id?>.<?=$product_group->product_group_id?>" <?=in_array($product_group->product_group_id,explode(',',$product->member_of_groups->product_group_ids))?'checked':''?>/>
                    <?=$product_group->product_group_name?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>
    <div class="product_card_actions" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr 1fr">
        <div>
            <button type="button" onclick="ItemList.deleteItem(<?=$product->product_id?>)">Удалить</button>
        </div>
        <div>
            <button type="button" onclick="ItemList.undeleteItem(<?=$product->product_id?>)">Восстановить</button>
        </div>
    </div>
    <hr>
    <?php endforeach;?>
