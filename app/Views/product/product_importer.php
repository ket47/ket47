<?=view('home/header')?>
<?=$html_before??'' ?>
<div style="padding: 20px;">
    <button onclick="ImportList.addItem();">Add new Item</button>
    <div class="filter segment">
        <input type="search" id="item_name_search" placeholder="Filter">
        <div>
            <label for="item_active">Active items</label>
            <input type="checkbox" id="item_active" name="is_active" checked="checked"> |
            <label for="item_deleted">Deleted items</label>
            <input type="checkbox" id="item_deleted" name="is_deleted"> |
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
    #import_table{
        width: 100%;
    }
    #import_table th{
    }
    #import_table th select{
        width: 100%;
    }
</style>
<script type="text/javascript">
    ImportList={
        init:function (){
//            $('.import_list').on('change',function(e){
//
//            });
//            $('.filter').on('change',function(e){
//                var $input=$(e.target);
//                var value=ImportList.val($input);
//                var name=$input.attr('name');
//                ImportList.reloadFilter[name]=value;
//                ImportList.reload();
//            });
//            ImportList.reload();
            
            ImportList.table.init();
        },
        
        table:{
            cols:[
                {field:"",name:"-"},
                {field:"product_code",name:"Код товара"},
                {field:"product_name",name:"Название"},
                {field:"product_description",name:"Описание"},
                {field:"product_weight",name:"Вес кг"},
                {field:"product_quantity",name:"Остаток"},
                {field:"is_produced",name:"Производится"},
                {field:"product_price",name:"Цена"},
                {field:"product_action_price",name:"Акция Цена"},
                {field:"product_action_start",name:"Акция Начало"},
                {field:"product_action_finish",name:"Акция Конец"},
            ],
            init:function(){
                this.theadInit();
            },
            theadInit:function(){
                let html='';
                for(let i=0;i<16;i++){
                    let selector=ImportList.table.theadSelectorGet();
                    html+=`<th>${selector}</th>`;
                }
                $("#import_table thead").html(html);
                
            },
            theadSelectorGet:function(){
                let html='<select>';
                for(let item of ImportList.table.cols){
                    html+=`<option value="${item.field}">${item.name}</option>`;
                }
                html+='</select>';
                return html;
            }
        },
        
        
    };
    $(ImportList.init);
</script>
<input type="file" id="importlist_uploader" name="items[]" multiple style="display:none" onchange="ImportList.fileUpload(this.files)">

<div style="padding: 20px;">
    <div class="segment">
        <table title="Main Import table" id="import_table">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
</div>


<?=$html_after??'' ?>
<?=view('home/footer')?>