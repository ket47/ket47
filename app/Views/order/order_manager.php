<?=view('common/item_manager',[
    'item_name'=>'order',
    'ItemName'=>'Order',
    'dontCreateWithName'=>1,
    'name_query_fields'=>'order_name,store_name,customer_name,order_description',
    'html_before'=>view('order/store_selector',['use_all_stores'=>1,'store_click_handler'=>'
        ItemList.addItemRequest.store_id=store_id;
        ItemList.reloadFilter.store_id=store_id;
        ItemList.reload();
        ']),
    'html_after'=>''
    ])?>