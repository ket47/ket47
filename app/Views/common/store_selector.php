<div style="padding: 5px;margin: 20px;" class="segment">
    <b>Доступные магазины</b>
    <div class="store_search_bar">
        <input type="search" placeholder="Filter stores" style="width: calc(100% - 20px)">
    </div>
    <div class="found_store_list"></div>
</div>
<style>
    .item_deleted{
        background-color: #fdd;
    }
    .item_disabled{
        background-color: #ddd;
    }
    .found_store_list div{
        display:inline-block;
        border-radius:5px;
        background-color:#eee;
        padding:5px;
        margin:5px;
        cursor:pointer;
        width: 100px;
    }
    .selected_store{
        font-weight:bold;
        background-color:#6cf !important;
    }
</style>
<script type="text/javascript">
    FoundStoreList={
        store_id:null,
        init:function (){
            $('.store_search_bar').on('input',function(e){
                FoundStoreList.reload();
            });
            FoundStoreList.reload();
            $('.found_store_list').on('click',function(e){
                var $store=$(e.target);
                var store_id=$store.data('store_id');
                if(!store_id){
                    return;
                }
                $('.selected_store').removeClass('selected_store');
                $store.addClass('selected_store');
                FoundStoreList.selectStore(store_id);
            });
        },
        selectStore:function(store_id){
            FoundStoreList.store_id=store_id;
            $(`.found_store_list div[data-store_id=${store_id}]`).addClass('selected_store');
            <?= $store_click_handler??'' ?>
        },
        reload_promise:null,
        reload:function(){
            if( FoundStoreList.reload_promise ){
                FoundStoreList.reload_promise.abort();
            }
            var name_query=$('.store_search_bar input').val();
            var name_query_fields='store_name';
            var limit=5;
            var filter={
                name_query,
                name_query_fields,
                limit
            };
            
            
            if(<?=$owned_stores_only??0?>){
                filter.owner_id='<?=session()->get('user_id') ?>';
            }
            FoundStoreList.reload_promise=$.post('/Store/listGet',filter).done(function(store_list){
                if( <?= $use_all_stores??0 ?> ){
                    store_list.push({store_id:0,store_name:'Все'});
                }
                let html='';
                for(let store of store_list){
                    html+=`<div data-store_id="${store.store_id}">
                                <img src="/image/get.php/${store.image_hash}.100.100.webp" alt=""><br>
                                ${store.store_name||'-'}
                            </div>`;
                }
                $('.found_store_list').html(html);
                if(store_list[0]){
                    FoundStoreList.selectStore(store_list[0].store_id);
                }
            }).fail(function(error){
                $('.found_store_list').html(error);
            });
        }
    };
    $(FoundStoreList.init);
</script>