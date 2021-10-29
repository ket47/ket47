<?=view('home/header')?>
<?=$html_before??'' ?>
<div style="padding: 20px;">
    <button onclick="ItemList.addItem();">Создать <i class="fa fa-plus"></i></button>
    <div class="filter segment">
        <input type="search" id="item_name_search" name="name_query" placeholder="Filter">
        <div>
            <label for="item_active">Активные</label>
            <input type="checkbox" id="item_active" name="is_active" checked="checked"> |
            <label for="item_disabled">Отключенные</label>
            <input type="checkbox" id="item_disabled" name="is_disabled"> |
            <label for="item_deleted">Удаленные</label>
            <input type="checkbox" id="item_deleted" name="is_deleted">
        </div>
    </div>
    <div class="item_list"></div>
</div>
<style>
    .item_table{
        width: 100%;
        display: grid;
    }
    .item_table>div:nth-child(odd)>div{
        background-color: #f5f5f5;
    }
    .item_table>div>div{
        display: flex;
        align-items: center;
        border-bottom: 1px solid white;
    }
    .item_card>div{
        border: 2px #999 dashed;
        width: 100%;
    }
    .item_disabled>div{
        background-color: #ccc !important;
    }
    .item_deleted>div{
        background-color: #fdd !important;
    }
    .image_list{
        background-color: #f5f5f5;
        padding: 5px;
    }
</style>
<script type="text/javascript">
    ItemList={
        $itemCard:null,
        init:function (){
            $('.item_list').on('change',function(e){
                var $input=$(e.target);
                var name_parts=$input.attr('name').split('.');
                var name=name_parts[0];
                var item_id=name_parts[1];
                var subtype=name_parts[2];
                var value=ItemList.val($input);
                if( subtype==='date' ){
                    value=value+' '+(ItemList.val( $(`input[name='${name}.${item_id}.time']`) )||'00:00')+':00';
                }
                if( subtype==='time' ){
                    value=ItemList.val( $(`input[name='${name}.${item_id}.date']`) )+' '+value+':00';
                }
                if( name==='group_id' ){
                    let current_group_id=subtype;
                    let new_group_id=value;
                    let is_joined= value>0?1:0;
                    let group_id= value>0?new_group_id:current_group_id;
                    ItemList.saveItemGroup(item_id,group_id,is_joined);
                } else 
                if( name==='is_disabled' ){
                    ItemList.saveItemDisabled(item_id,value);
                } else {
                    ItemList.saveItem(item_id,name,value);
                }
            });
            $('.item_list').on('click',function(e){
                let $node=$(e.target);
                let item_id=$node.data('id');
                if( !item_id ){
                    return;
                }
                if(ItemList.current_item_id===item_id ){
                    $(`#itemCard${ItemList.current_item_id}`).html('').hide();
                    ItemList.current_item_id=0;
                    return;
                }
                
                ItemList.$itemCard=$(`#itemCard${item_id}`);
                ItemList.$itemCard.css('min-height',ItemList.$itemCard.css('height') ).show();
                ItemList.loadItem(item_id).done(function(){
                    $(`#itemCard${ItemList.current_item_id}`).html('').hide();
                    ItemList.current_item_id=item_id;
                });
            });
            $('.filter').on('input',function(e){
                var $input=$(e.target);
                var value=ItemList.val($input);
                var name=$input.attr('name');
                ItemList.reloadFilter[name]=value;
                ItemList.reload();
            });
            ItemList.reload();
        },
        val:function( $input ){
            return $input.attr('type')==='checkbox'?($input.is(':checked')?$input.val():0):$input.val();
        },
        reloadItem:function(<?=$item_name?>_id){
            ItemList.loadItem(ItemList.itemCardId);           
        },
        loadItem:function(<?=$item_name?>_id){
            ItemList.itemCardId=<?=$item_name?>_id;
            
            return $.post('/Home/<?=$item_name?>CardGet',{<?=$item_name?>_id}).done(function(resp){
                ItemList.$itemCard.html(resp);
                ItemList.$itemCard[0].scrollIntoView(true);
            });            
        },
        saveItem:function (<?=$item_name?>_id,name,value){
            var request={
                <?=$item_name?>_id
            };
            request[name]=value;
            return $.post('/<?=$ItemName?>/itemUpdate',JSON.stringify(request)).done(function(){
                if( name==='is_disabled' ){
                    ItemList.reloadItem();
                }
            }).fail(ItemList.reloadItem);
        },
        saveItemGroup:function (<?=$item_name?>_id,group_id,is_joined){
            $.post('/<?=$ItemName?>/itemUpdateGroup',{<?=$item_name?>_id,group_id,is_joined}).fail(ItemList.reloadItem);
        },
        saveItemDisabled:function(<?=$item_name?>_id,is_disabled){
            $.post('/<?=$ItemName?>/itemDisable',{<?=$item_name?>_id,is_disabled}).always(ItemList.reload);
        },
        deleteItem:function( <?=$item_name?>_id ){
            if( !confirm("Переместить в корзину? \nБудет удалено через 7 дней") ){
                return;
            }
            $.post('/<?=$ItemName?>/itemDelete',{<?=$item_name?>_id}).done(ItemList.reload);
        },
        undeleteItem:function( <?=$item_name?>_id ){
            $.post('/<?=$ItemName?>/itemUnDelete',{<?=$item_name?>_id}).done(ItemList.reload);
        },
        approve:function( <?=$item_name?>_id,field_name ){
            $.post('/<?=$ItemName?>/fieldApprove',{<?=$item_name?>_id,field_name:field_name}).always(ItemList.reloadItem);
        },
        addItemRequest:{},
        addItem:function(){
            if( !(<?=$dontCreateWithName??0?>) ){
                let item_name=prompt("Название","NEW ITEM");
                if(!item_name){
                    return;
                }
                ItemList.addItemRequest.<?=$item_name?>_name=item_name;
            }
            $.post('/<?=$ItemName?>/itemCreate',this.addItemRequest).done(function(){
                $('.search_bar input').val(ItemList.addItemRequest.name);
                ItemList.reload();
            }).fail(function(response){
                
            });
        },
        reload_promise:null,
        reloadFilter:{
            is_active:1,
            is_deleted:0,
            is_disabled:0
        },
        reload:function(){
            if(ItemList.reload_promise){
                ItemList.reload_promise.abort();
            }
            var name_query=$('#item_name_search').val();
            var name_query_fields='<?=$name_query_fields?>';
            var limit=30;
            ItemList.reloadFilter.name_query=name_query;
            ItemList.reloadFilter.name_query_fields=name_query_fields;
            ItemList.reloadFilter.limit=limit;
            ItemList.reload_promise=$.post('/Home/<?=$item_name?>_list',ItemList.reloadFilter).done(function(response){
                ItemList.current_item_id=0;
                $('.item_list').html(response);
            }).fail(function(error){
                $('.item_list').html(error);
            });
        },
        imageApprove:function( image_id ){
            $.post('/<?=$ItemName?>/imageDisable',{image_id,is_disabled:0}).always(ItemList.reloadItem);
        },
        imageDelete:function( image_id ){
            if( !confirm("Удалить?") ){
                return;
            }
            $.post('/<?=$ItemName?>/imageDelete',{image_id}).always(ItemList.reloadItem);            
        },
        imageOrder:function( image_id, dir ){
            $.post('/<?=$ItemName?>/imageOrder',{image_id,dir}).always(ItemList.reloadItem);   
        },
        fileUpload:function(filelist){
            if( filelist.length ){
                let attached_count=0;
                let total_size_limit=10*1024*1024;
                for(let fl of filelist){
                    total_size_limit-=fl.size;
                    if(total_size_limit<0){
                        alert("Разовый объем файлов должен быть не больше 10МБ.\nПрикреплено только: "+attached_count+"файлов");
                        break;
                    }
                    ItemList.fileUploadFormData.append("files[]", fl);
                    attached_count++;
                }
                ItemList.fileUploadXhr.send(ItemList.fileUploadFormData);
            }
        },
        fileUploadFormData:null,
        fileUploadXhr:null,
        fileUploadInit:function( image_holder_id ){
            var url = '/<?=$ItemName?>/fileUpload';
            ItemList.fileUploadXhr = new XMLHttpRequest();
            ItemList.fileUploadFormData = new FormData();
            ItemList.fileUploadFormData.set('image_holder_id',image_holder_id);
            
            ItemList.fileUploadXhr.open("POST", url, true);
            ItemList.fileUploadXhr.onreadystatechange = function() {
                if (ItemList.fileUploadXhr.readyState === 4 && ItemList.fileUploadXhr.status === 201) {
                    ItemList.reloadItem();
                }
            };
            $('#itemlist_uploader').click();
        },
    };
    $(ItemList.init);
</script>
<input type="file" id="itemlist_uploader" name="items[]" multiple style="display:none" onchange="ItemList.fileUpload(this.files)">
<?=$html_after??'' ?>
<?=view('home/footer')?>