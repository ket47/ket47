<?=view('common/item_manager',[
    'item_name'=>'product',
    'ItemName'=>'Product',
    'name_query_fields'=>'product_name,product_code,product_description',
    'html_before'=>view('product/store_selector'),
    'html_after'=>view('product/script_modifier')
    ])?>