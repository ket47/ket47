<div style="display:grid;grid-template-columns:30px 4fr 80px 80px 80px 2fr 30px;">
    <div style="display: contents;text-align: center" class="grid_header">
        <div>#</div>
        <div>Название</div>
        <div>Кол-во</div>
        <div>Цена</div>
        <div>Сумма</div>
        <div>Комент.</div>
        <div></div>
    </div>
    <?php $active_count=0; foreach($entry_list as $entry): ?>
    <?php if($entry->deleted_at)continue; ?>
    <div style="display: contents">
        <div><?=++$active_count?></div>
        <div><?=$entry->entry_text?></div>
        <div><input style="text-align: right" value="<?=$entry->entry_quantity?>"  name="entry_quantity.<?= $entry->entry_id ?>" /></div>
        <div style="text-align: right"><?=$entry->entry_price?></div>
        <div style="text-align: right"><?=$entry->entry_sum?></div>
        <div><input value="<?=$entry->entry_comment?>"  name="entry_comment.<?= $entry->entry_id ?>" /></div>
        <div>
            <i class="fa fa-trash" style="margin: 3px;" title="Удалить" onclick="Order.entryTable.deleteItem('<?= $entry->entry_id ?>')"></i>            
        </div>
    </div>
    <?php endforeach;?>
    <?php if($active_count>0): ?>
    <div style="display: contents">
        <div style="grid-column: 1 / span 4;text-align: right">Доставка:</div>
        <div style="text-align: right"><?=$order->order_sum_delivery?></div>
        <div style=""></div>
        <div style=""></div>
    </div>
    <div style="display: contents">
        <div style="grid-column: 1 / span 4;text-align: right">Сумма итого:</div>
        <div style="text-align: right;font-weight: bold"><?=$order->order_sum_total?></div>
        <div style=""></div>
        <div style=""></div>
    </div>
    <?php else: ?>
    <div style="grid-column: 1 / span 7;text-align: center;padding: 15px"><b>Заказ пуст</b></div>
    <?php endif;?>
    
    <div style="grid-column: 1 / span 7;text-align: center;padding: 15px"></div>
    <?php $i=0; foreach($entry_list as $entry): ?>
    <?php if(!$entry->deleted_at)continue; ?>
    <div style="display: contents" class="item_deleted">
        <div><?=++$i?></div>
        <div><?=$entry->entry_text?></div>
        <div style="text-align: right"><?=$entry->entry_quantity?></div>
        <div style="text-align: right"><?=$entry->entry_price?></div>
        <div style="text-align: right"><?=$entry->entry_sum?></div>
        <div><?=$entry->entry_comment?></div>
        <div>
            <i class="fas fa-trash-restore" style="margin: 3px;" title="Восстановить" onclick="Order.entryTable.undeleteItem('<?= $entry->entry_id ?>')"></i>  
        </div>
    </div>
    <?php endforeach;?>
</div>