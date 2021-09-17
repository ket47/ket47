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
                    ItemList.saveItemMemberGroup(item_id,subtype,value);
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
        saveItem:function (<?=$item_name?>_id,name,value){
            var data={
                <?=$item_name?>_id
            };
            data[name]=value;
            var request={
                data:JSON.stringify(data)
            };
            $.post('/<?=$ItemName?>/itemUpdate',request).done(function(){
                if( name==='is_disabled' ){
                    ItemList.reload();
                }
            }).fail(ItemList.reload);
        },
        saveItemMemberGroup:function (member_id,group_id,value){
            var table='<?=$item_name?>_group_member_list';
            $.post('/GroupMember/itemUpdate',{table,member_id,group_id,value}).fail(ItemList.reload);
        },
        deleteItem:function( <?=$item_name?>_id ){
            $.post('/<?=$ItemName?>/itemDelete',{<?=$item_name?>_id}).done(ItemList.reload);
        },
        undeleteItem:function( <?=$item_name?>_id ){
            var name='deleted_at';
            $.post('/<?=$ItemName?>/itemUpdate',{<?=$item_name?>_id,name}).done(ItemList.reload);
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
<?=$html_after??'' ?>
<?=view('home/footer')?>