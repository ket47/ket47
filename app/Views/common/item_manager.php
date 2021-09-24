<?=view('home/header')?>
<?=$html_before??'' ?>
<div style="padding: 20px;">
    <button onclick="ItemList.addItem();">Add new Item</button>
    <div class="filter segment">
        <input type="search" id="item_name_search" placeholder="Filter">
        <div>
            <label for="item_deleted">Active items</label>
            <input type="checkbox" id="item_active" name="is_active" checked="checked">
            <label for="item_deleted">Deleted items</label>
            <input type="checkbox" id="item_deleted" name="is_deleted">
            <label for="item_disabled">Disabled items</label>
            <input type="checkbox" id="item_disabled" name="is_disabled">
        </div>
    </div>
    <div class="item_list"></div>
</div>
<style>
    .item_disabled{
        background-color: #ddd;
    }
    .item_deleted{
        background-color: #fdd;
    }
</style>
<script type="text/javascript">
    ItemList={
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
                    ItemList.saveItemGroup(item_id,subtype,value);
                } else 
                if( name==='is_disabled' ){
                    ItemList.saveItemDisabled(item_id,value);
                } else {
                    ItemList.saveItem(item_id,name,value);
                }
            });
            $('.filter').on('change',function(e){
                var $input=$(e.target);
                var value=ItemList.val($input);
                var name=$input.attr('name');
                ItemList.reloadFilter[name]=value;
                ItemList.reload();
            });
            ItemList.reload();
        },
        val:function( $input ){
            return $input.attr('type')==='checkbox'?($input.is(':checked')?1:0):$input.val();
        },
        dnd:{
            init:function(){
                
            },
            allowDrop:function(){
                e.preventDefault();
            },
            drag:function(){
                e.dataTransfer.setData("text", e.target.id);
            },
            drop:function(){
                e.preventDefault();
                var data = e.dataTransfer.getData("text");
                e.target.appendChild(document.getElementById(data));
            }
        },
        saveItem:function (<?=$item_name?>_id,name,value){
            var data={
                <?=$item_name?>_id
            };
            data[name]=value;
            var request={
                data:JSON.stringify(data)
            };
            return $.post('/<?=$ItemName?>/itemUpdate',request).done(function(){
                if( name==='is_disabled' ){
                    ItemList.reload();
                }
            }).fail(ItemList.reload);
        },
        saveItemGroup:function (<?=$item_name?>_id,group_id,is_joined){
            $.post('/<?=$ItemName?>/itemUpdateGroup',{<?=$item_name?>_id,group_id,is_joined}).fail(ItemList.reload);
        },
        saveItemDisabled:function(<?=$item_name?>_id,is_disabled){
            $.post('/<?=$ItemName?>/itemDisable',{<?=$item_name?>_id,is_disabled}).always(ItemList.reload);
        },
        deleteItem:function( <?=$item_name?>_id ){
            $.post('/<?=$ItemName?>/itemDelete',{<?=$item_name?>_id}).done(ItemList.reload);
        },
        undeleteItem:function( <?=$item_name?>_id ){
            ItemList.saveItem(<?=$item_name?>_id,'deleted_at',null).done(ItemList.reload);
        },
        approve:function( <?=$item_name?>_id,field_name ){
            $.post('/<?=$ItemName?>/fieldApprove',{<?=$item_name?>_id,field_name:field_name}).always(ItemList.reload);
        },
        imageApprove:function( image_id ){
            $.post('/<?=$ItemName?>/imageApprove',{image_id}).always(ItemList.reload);
        },
        imageDelete:function( image_id ){
            if( !confirm("Удалить?") ){
                return false;
            }
            $.post('/<?=$ItemName?>/imageDelete',{image_id}).always(ItemList.reload);            
        },
        fileUpload:function(filelist){
            if( filelist.length ){
                let attached_count=0;
                let total_size_limit=10000000;
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
                    ItemList.reload();
                }
            };
            $('#itemlist_uploader').click();
        },
        addItemRequest:{},
        addItem:function(){
            ItemList.addItemRequest.name="NEW ITEM";
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
            var name_query=$('.search_bar input').val();
            var name_query_fields='<?=$name_query_fields?>';
            var limit=30;
            ItemList.reloadFilter.name_query=name_query;
            ItemList.reloadFilter.name_query_fields=name_query_fields;
            ItemList.reloadFilter.limit=limit;
            ItemList.reload_promise=$.post('/Home/<?=$item_name?>_list',ItemList.reloadFilter).done(function(response){
                $('.item_list').html(response);
            }).fail(function(error){
                $('.item_list').html(error);
            });
        }
    };
    $(ItemList.init);
</script>
<input type="file" id="itemlist_uploader" name="items[]" multiple style="display:none" onchange="ItemList.fileUpload(this.files)">
<?=$html_after??'' ?>
<?=view('home/footer')?>