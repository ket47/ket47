<?php 
    function dmyt( $iso ){
        if( !$iso ){
            return "";
        }
        $expl= explode('-', $iso);
        return "$expl[2].$expl[1].$expl[0]$expl[3]";
    }
    include APPPATH.'Views/home/header.php';
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.js"></script>
<script>
var order_id="<?php echo $order->order_id?>";
var Order={
    init:function(){
        Order.suggestion.init();
    },
    suggestion:{
        init:function(){
            $( "#order_suggest" ).autocomplete({
                source: function( request, response ) {
                    let req={
                        name_query:request.term,
                        name_query_fields:'product_name'
                    };
                    $.get('/Product/listGet/',req).done(function(resp,status,xhr){
                        let product_list=xhr.responseJSON.product_list || [];
                        let suggestions=[];
                        for( let product of product_list ){
                            let sugg=`${product.product_name} [${product.product_quantity}] ${product.product_price}руб`;
                            product.label=sugg;
                            suggestions.push(product);
                        }
                        response( suggestions );
                    });
                },
                select: function( event, ui ) {
                    let request={
                        order_id:order_id,
                        product_id:ui.item.product_id,
                        product_quantity:prompt(`Введите количество [${ui.item.product_quantity}]`,1)||1
                    };
                    $.post('/Entry/itemCreate',request).done(function(resp,status,xhr){

                    });
                }
            });
        }
    },
    entryTable:{
        init:function(){
            
        },
        load:function(){
            
        }
    }
};
$(Order.init);


</script>
<style>
    #order_suggest{
        width:calc( 100% - 10px );
        padding: 5px;
        border: 1px solid #ddd;
        background-color: #ffa;
    }
</style>



<div  style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
        <div style="display:grid;grid-template-columns:1fr 3fr">
            <div>Продавец</div>
            <div>
                <?=$order->store->store_name ?> (<?=$order->store->store_phone ?>)
            </div>

            <div>Покупатель</div>
            <div>
                <?= $order->customer->user_name?> (<?=$order->customer->user_phone ?>)
            </div>

            <div>Комментарий</div>
            <div>
                <?= $order->order_description?>
            </div>


        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr">
            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?= $order->order_id ?>" <?= $order->is_disabled ? 'checked' : '' ?>/>
            </div>

            <div>Создан</div>
            <div>
                <?=dmyt($order->created_at)?>
            </div>

            <div>Изменен</div>
            <div>
                <?=dmyt($order->updated_at)?>
            </div>

            <div>Удален</div>
            <div>
                <?php if($order->deleted_at): ?>
                    <?=dmyt($order->deleted_at)?>
                    <i class="fas fa-trash-restore" onclick="ItemList.undeleteItem(<?= $order->order_id ?>)"> Восстановить</i>
                <?php else: ?>
                    <i class="fa fa-trash" onclick="ItemList.deleteItem(<?= $order->order_id ?>)"> Удалить</i>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div style="border:1px solid #666">
        <div style="position: relative">
            <input id="order_suggest" placeholder="код или название товара"/>
        </div>
        <div style="display:grid;grid-template-columns:3fr 1fr 1fr 1fr;">
            
            <?php if($order->entries):foreach($order->entries as $entry): ?>
            <div style="display: contents">
                <div><?=$entry->entry_text?></div>
                <div><?=$entry->entry_quantity?></div>
                <div><?=$entry->entry_price?></div>
                <div><?=$entry->entry_comment?></div>
            </div>
            <?php endforeach;else: ?>
            <div style="grid-column: 1 / span 4;text-align: center">Заказ пуст</div>
            <?php endif;?>
        </div>
    </div>
    
    <div>
        <div>Сумма доставки</div>
        <div>
            <?= $order->order_sum_shipping?>
        </div>

        <div>Сумма налога</div>
        <div>
            <?= $order->order_sum_tax?>
        </div>

        <div>Сумма итого</div>
        <div>
            <?= $order->order_sum_total?>
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
    
    
    <div>
        <h3>История </h3>
        <?php foreach ($order_group_list as $order_group):?>
        <h3><?= $order_group->group_name ?></h3>
        <?php endforeach;?>
    </div>
    
    
    
</div>