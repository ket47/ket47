<?php 
    function dmyt( $iso ){
        if( !$iso ){
            return "";
        }
        $expl= explode('-', str_replace(' ', '-', $iso));
        return "$expl[2].$expl[1].$expl[0] ".($expl[3]??'');
    }
    //include APPPATH.'Views/home/header.php';
?>
<script>
var order_id="<?php echo $order->order_id?>";
var Order={
    init:function(){
        Order.suggestion.init();
        Order.stage.init();
    },
    suggestion:{
        init:function(){
            $( "#order_suggest" ).autocomplete({
                source: function( request, response ) {
                    let req={
                        name_query:request.term,
                        name_query_fields:'product_name'
                    };
                    $.get('/Product/listGet',req).done(function(resp,status,xhr){
                        let product_list=xhr.responseJSON.product_list || [];
                        let suggestions=[];
                        for( let product of product_list ){
                            let sugg=`${product.product_name} ${product.product_price}руб [${product.product_quantity}]`;
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
                        Order.entryTable.load();
                        $( "#order_suggest" ).val('');
                    });
                }
            });
            Order.entryTable.init();
        }
    },
    entryTable:{
        init:function(){
            Order.entryTable.load();
            $('#order_entry_list').on('change',function(e){
                e.stopPropagation();
                var $input=$(e.target);
                var name_parts=$input.attr('name').split('.');
                var name=name_parts[0];
                var item_id=name_parts[1];
                var value=$input.val();

                Order.entryTable.saveItem(item_id,name,value);
            });
        },
        load:function(){
            $("#order_entry_list").load('/Home/orderEntryListGet',{order_id});
        },
        saveItem:function(entry_id,name,value){
            var request={
                entry_id
            };
            request[name]=value;
            return $.post('/Entry/itemUpdate',JSON.stringify(request)).done(function(){
                if( name==='entry_quantity' ){
                    Order.entryTable.load();
                }
            }).fail(Order.entryTable.load);            
        },
        deleteItem:function(entry_id){
            var request={
                entry_id
            };
            return $.post('/Entry/itemDelete',request).done(function(){
                Order.entryTable.load();
            }).fail();
        },
        undeleteItem:function(entry_id){
            var request={
                entry_id
            };
            return $.post('/Entry/itemUnDelete',request).done(function(){
                Order.entryTable.load();
            }).fail();
        }
    },
    stage:{
        init:function(){
            $("#order_stage_actions").click(function(e){
                let $button=$(e.target);
                let action=$button.data('action');
                if( $button.hasClass('negative') ){
                    if( !confirm($button.html()+'?') ){
                        return;
                    }
                }
                if( action ){
                    Order.stage.actions[action] && Order.stage.actions[action]();
                    return;
                }
                let new_stage=$button.data('new_stage');
                if( !new_stage ){
                    return;
                }
                Order.stage.itemStageCreate(new_stage)
            });
        },
        itemStageCreate(new_stage){
            if(!new_stage){
                return
            }
            $.post('/Order/itemStageCreate',{order_id,new_stage}).done(function(){
                ItemList.reloadItem();
            });
        },
        actions:{
            action_take_photo:function(){
                ItemList.fileUploadInit(order_id);
            },
            action_checkout:function(){
                Order.stage.payment_modal();
            },
            action_call_customer:function(){
                var customer_phone="<?=$order?->customer?->user_phone ?>";
                document.location.href = `tel:${customer_phone}`;
            }
        },
        payment_modal:function(){
            let request={
                order_id: order_id,
                order_sum_total: '<?=$order->order_sum_total?>',
                user_id:'<?=$order->owner_id?>'
                };
            App.loadWindow('/Home/paymentModal',request).progress(function(status,data){
                if(status==='closed'){
                    ItemList.reloadItem();
                }
            });
        }
    }
};
$(Order.init);


</script>
<style>
    #order_entry_list{
        background-color: white;
    }
    #order input,#order textarea{
        width:calc(100% - 10px);
        border:1px #ccc solid;
        background: none;
    }
    #order_entry_list div{
        padding: 3px;
    }
    #order_entry_list>div>div:nth-child(even)>div{
        background-color: #f5fcff;
    }
    #order_stage_actions>div{
        border-radius: 10px;
        padding: 10px;
        display: inline-block;
        cursor: default;
        min-width: 100px;
    }
    #order_stage_actions{
        text-align: center;
        padding: 10px;
    }
    input#order_suggest{
        width:calc( 100% - 10px );
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 3px;
        background-color: #ffc;
    }
</style>

<div id="order" style="padding: 5px">
    <div style="display: grid;grid-template-columns:1fr 1fr">
        <div style="grid-column: 1 / 2 span">
            <h2>Заказ #<?=$order->order_id?> (<?=$order->stage_current_name?>)</h2>
        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr;grid-gap:10px;">
            <div>Продавец</div>
            <div>
                <?=$order->store->store_name??'' ?> (<?=$order->store->store_phone??'' ?>)
            </div>

            <div>Покупатель</div>
            <div>
                <?= $order->customer->user_name?> (<?=$order->customer->user_phone ?>)
            </div>

            <div>Комментарий</div>
            <div>
                <textarea name="order_description.<?= $order->order_id ?>"><?= $order->order_description??''?></textarea>
            </div>


        </div>
        <div style="display:grid;grid-template-columns:1fr 3fr;grid-gap:10px;">
<!--            <div>Отключен</div>
            <div>
                <input type="checkbox" name="is_disabled.<?= $order->order_id ?>" <?= $order->is_disabled ? 'checked' : '' ?>/>
            </div>-->

            <div>Создан</div>
            <div>
                <?=dmyt($order->created_at)?>
            </div>

            <div>Изменен</div>
            <div>
                <?=dmyt($order->updated_at)?>
                
                <?php 
                switch($order->updated_by){
                    case $order->owner_id:
                        $updated_by=$order->customer;
                        break;
                    default :
                        $updated_by=model('UserModel')->itemGet($order->updated_by,'basic');
                }
                if( is_object($updated_by) ){
                    echo "{$updated_by->user_name} ({$updated_by->user_phone})";
                }
                ?>
                
            </div>

            <div>Удален</div>
            <div>
                <?php if($order->deleted_at): ?>
                    <?=dmyt($order->deleted_at)?>
                    <i class="fa fa-trash" style="color:red" onclick="ItemList.purgeItem(<?= $order->order_id ?>)" title="Окончательно удалить"></i>
                    <i class="fas fa-trash-restore" onclick="ItemList.undeleteItem(<?= $order->order_id ?>)" title="Восстановить"></i>
                <?php else: ?>
                    <i class="fa fa-trash" onclick="ItemList.deleteItem(<?= $order->order_id ?>)" title="Удалить"></i>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="segment">
        <h3>Состав заказа </h3>
        <div style="position: relative">
            <input id="order_suggest" placeholder="добавить по коду или названию товара" />
        </div>
        <div id="order_entry_list"></div>
    </div>
    
    <div>
        <div id="order_stage_actions">
            <?php foreach($order->stage_next as $stage=>$config):
                if(!isset($config[0])){continue;}
                ?>
                <?php if( strpos($stage,'action')===0 ): ?>
                    <div data-action="<?=$stage?>" class="<?=$config[1]??'primary'?>"><?=$config[0]?></div>
                <?php else: ?>
                    <div data-new_stage="<?=$stage?>" class="<?=$config[1]??'primary'?>"><?=$config[0]?></div>
                <?php endif;?>
            <?php endforeach;?>
        </div>
    </div>



    <div>
        <h3>Изображения </h3>
        <div class="image_list">
                <?php if (isset($order->images)): foreach ($order->images as $image): ?>
                    <?php if(!$image->image_hash)continue;?>
                    <div style="background-image: url(/image/get.php/<?= $image->image_hash ?>.160.90.webp);"
                         class="<?= $image->deleted_at ? 'deleted' : '' ?>">
                        <a href="javascript:ItemList.imageDelete(<?= $image->image_id ?>)"><div class="fa fa-trash" style="color:red"></div></a>
                        <a href="/image/get.php/<?= $image->image_hash ?>.1024.1024.webp" target="imagepreview"><div class="fa fa-eye" style="color:blue"></div></a>
                        <br><br>
                    </div>
            <?php endforeach; endif; ?>
            <div class="vcenter">
                <a href="javascript:ItemList.fileUploadInit(<?= $order->order_id ?>)">Загрузить <span class="fa fa-plus"></span></a>
            </div>
        </div>
    </div>
    
    
    <div class="segment11">
        <h3>Стадии заказа</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;">
            <div style="display: contents;" class="grid_header">
                <div>Название</div>
                <div>Начало</div>
                <div>Начал</div>
            </div>
            <?php foreach ($order->stages as $stage):?>
            <div><?= $stage->group_name ?></div>
            <div><?= dmyt($stage->created_at) ?></div>
            <?php if( is_object($stage->created_user??null) ): ?>
                <div><?= $stage->created_user->user_name ?> (<?= $stage->created_user->user_phone ?>)</div>
            <?php else: ?>
                <div>-</div>
            <?php endif; ?>
            <?php endforeach;?>
        </div>
    </div>

    <div>
        <h3>Установить стадию в ручную</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;">
            <div>
                <select id="order_stage_select">
                    <option value="">---</option>
                    <?php foreach($stage_list as $stage):?>
                        <option value="<?=$stage->group_type?>"><?=$stage->group_name?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <div>
                <button onclick="Order.stage.itemStageCreate($('#order_stage_select').val())">Установить стадию</button>
            </div>
        </div>
    </div>

</div>