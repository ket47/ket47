<?=view('home/header')?>
<?=$html_before??'' ?>
<div style="padding: 20px;">
    <button onclick='$("#importlist_uploader").click()' class="primary">Загрузить таблицу XLSX</button>
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
        display: grid;
        grid-template-areas: "C1 C2 C3 C4 C5 C6 C7 C8 C9 C10 C11 C12 C13 C14 C15 C16";
    }
    #import_table_head select{
        width: 100%;
    }
    #import_table_head{
        display: contents;
    }
    #import_table_body{
        display: contents;
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
                {field:"product_categories",name:"Категории"},
                {field:"product_price",name:"Цена"},
                {field:"product_action_price",name:"Акция Цена"},
                {field:"product_action_start",name:"Акция Начало"},
                {field:"product_action_finish",name:"Акция Конец"},
            ],
            init:function(){
                this.theadInit();
            },
            reload:function(){
                
            },
            theadInit:function(){
                let html='';
                let selector=ImportList.table.theadSelectorGet();
                for(let i=1;i<=16;i++){
                    html+=`<div style="grid-area:C${i}" id="import_table_h${i}">${selector}</div>`;
                }
                $("#import_table_head").html(html);
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
            
            
        fileUpload:function(filelist){
            if( filelist.length ){
                ImportList.fileUploadInit();
                let attached_count=0;
                let total_size_limit=10*1024*1024;
                for(let fl of filelist){
                    total_size_limit-=fl.size;
                    if(total_size_limit<0){
                        alert("Разовый объем файлов должен быть не больше 10МБ.");
                        break;
                    }
                    ImportList.fileUploadFormData.append("files[]", fl);
                    attached_count++;
                }
                ImportList.fileUploadXhr.send(ImportList.fileUploadFormData);
            }
        },
        fileUploadFormData:null,
        fileUploadXhr:null,
        fileUploadInit:function(){
            var url = '/Importer/fileUpload';
            ImportList.fileUploadXhr = new XMLHttpRequest();
            ImportList.fileUploadFormData = new FormData();
            //ImportList.fileUploadFormData.set('holder','product');
            
            ImportList.fileUploadXhr.open("POST", url, true);
            ImportList.fileUploadXhr.onreadystatechange = function() {
                if (ImportList.fileUploadXhr.readyState === 4 && ImportList.fileUploadXhr.status === 201) {
                    
                }
                ImportList.table.reload();
            };
        },
    };
    $(ImportList.init);
</script>
<input type="file" id="importlist_uploader" name="items[]" style="display:none" onchange="ImportList.fileUpload(this.files)">
<div style="padding: 20px;">
    <div class="segment">
        <div title="Main Import table" id="import_table">
            <div id="import_table_head"></div>
            <div id="import_table_body"></div>
        </div>
    </div>
</div>


<?=$html_after??'' ?>
<?=view('home/footer')?>