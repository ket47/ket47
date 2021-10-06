<?=view('home/header')?>
<?=$html_before??'' ?>
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
        grid-template-columns: repeat(16, min-content);
    }
    #import_table>div:nth-child(even)>div{
        background-color: #eee;
    }
    #import_table_head select{
        width: 100%;
    }
    #import_table_head{
        display: contents;
    }
    .import_table_row{
        display: contents;
    }
    .import_table_row div{
        text-align: left;
        padding: 3px;
    }
    .import_table_row input,.import_table_row textarea{
        width:100%;
        margin: 0px;
        margin-top: 2px;
        padding: 0px;
        border:none;
        text-align: left;
        font-family: 'Raleway', sans-serif;
    }
    .import_table_row textarea{
        height: 100px;
    }
    #import_table>div.selected div{
        background-color: #ffa;
    }
</style>
<div style="padding: 20px;">
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
</div>
<div style="padding: 20px;">
    <button onclick='ImportList.table.reload()'><i class="fas fa-redo"></i> Обновить</button>
    <button onclick='ImportList.table.listDelete()'><span class="fa fa-trash"></span> Удалить строки</button>
    <button onclick='ImportList.table.listTruncate()'><span class="fa fa-table"></span> Очистить таблицу</button>
    |
    <button onclick='$("#importlist_uploader").click()' class="primary"><span class="fa fa-upload"></span> Загрузить таблицу XLSX</button>
    <div class="segment">
        <div title="Main Import table" id="import_table">
            <div id="import_table_head"></div>
        </div>
        
    </div>
    <div id="import_table_loader"></div>
</div><script type="text/javascript">
    ImportList={
        init:function (){
            ImportList.table.init();
        },
        table:{
            cols:[
                {field:"",name:"-пропустить-"},
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
                $(window).scroll(function(){
                    ImportList.table.loadmorecheck();
                });
                ImportList.table.loadmorecheck();
                
                $("#import_table").click(function(e){
                    let $node=$(e.target);
                    if( $node.data('editable') ){
                        ImportList.table.celledit($node);
                        let parent=$node.parent();
                        parent.toggleClass('selected');
                    }
                });
            },
            itemUpdate:function(id,field,value){
                let request={id};
                request[field]=value;
                $.post('/Importer/itemUpdate',JSON.stringify(request));
            },
            listDelete:function(){
                let ids=[];
                $("#import_table .selected").each(function(){
                    ids.push($(this).data('id'));
                });
                if( ids.length>0 && confirm(`Удалить ${ids.length} строк?`) ){
                    $.post('/Importer/listDelete',{ids:ids.join(',')}).done(function(){ImportList.table.reload()});
                }
            },
            listTruncate:function(){
                if( confirm(`Очистить таблицу?`) ){
                    $.post('/Importer/listDelete',{ids:''}).done(function(){ImportList.table.reload()});
                }
            },
            celledit:function( $node ){
                if( $node.data('editable') ){
                    let val=$node.html();
                    let $editor=null;
                    if( val.length>20 ){
                        $node.html(`<textarea>${val}</textarea>`);
                        $editor=$node.find('textarea');
                    } else {
                        $node.html(`<input value="${val}"/>`);
                        $editor=$node.find('input');
                    }
                    $editor.focus();
                    $editor.on('change blur',function(){
                        let id=$node.parent().data('id');
                        let field=$node.data('field');
                        let value=$editor.val();
                        $node.html(value);
                        ImportList.table.itemUpdate(id,field,value);
                    });
                }
            },
            loadoffset:0,
            loadingcompleted:false,
            loadrequest:{},
            reload:function(){
                this.loadoffset=0;
                this.loadingcompleted=false;
                $(".import_table_row").remove();
                this.loadmore();
            },
            loadmorecheck:function(){
                if(  isElementInViewport( $("#import_table_loader") ) ) {
                    ImportList.table.loadmore();
                }
            },
            loadmore:function(){
                if(this.inprogress===true || this.loadingcompleted===true){
                    return false;
                }
                this.inprogress=true;
                this.loadrequest.limit=30;
                this.loadrequest.offset=this.loadoffset;
                $.post('/Importer/listGet',this.loadrequest,'json').done(function(list){
                    ImportList.table.inprogress=false;
                    ImportList.table.loadoffset+=list.length+1;
                    ImportList.table.loadingcompleted=false;
                    if( list.length<ImportList.table.loadrequest.limit ){
                        ImportList.table.loadingcompleted=true;
                    }
                    ImportList.table.appendRows(list);
                    ImportList.table.loadmorecheck();
                });
            },
            columnCount:0,
            appendRows:function( list ){
                for( let row of list ){
                    let rowhtml='';
                    for(let i=1;i<=16;i++){
                        let col=`C${i}`;
                        let val=row[col];
                        let minwidth=val?(val.length>40?'200px':'100px'):'0px';
                        rowhtml+=`<div data-editable="1" data-field="${col}" style="min-width:${minwidth}">${row[col]||''}</div>`;// style="grid-area:C${i}"
                    }
                    $("#import_table").append(`<div class="import_table_row" data-id="${row['id']}">${rowhtml}</div>`);
                }
            },
            theadInit:function(){
                let html='';
                let selector=ImportList.table.theadSelectorGet();
                for(let i=1;i<=16;i++){
                    html+=`<div id="import_table_h${i}">${selector}</div>`;// style="grid-area:C${i}"
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
    function isElementInViewport (el) {
        if (typeof jQuery === "function" && el instanceof jQuery) {
            el = el[0];
        }
        var rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && /* or $(window).height() */
            rect.right <= (window.innerWidth || document.documentElement.clientWidth) /* or $(window).width() */
        );
    }
</script>
<input type="file" id="importlist_uploader" name="items[]" style="display:none" onchange="ImportList.fileUpload(this.files)">
<?=$html_after??'' ?>
<?=view('home/footer')?>