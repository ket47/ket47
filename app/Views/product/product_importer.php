<?=view('home/header')?>
<?=view('common/store_selector',['use_all_stores'=>0,'owned_stores_only'=>0, 'store_click_handler'=>'
        ImportList.table.loadrequest.holder="store";
        ImportList.table.loadrequest.holder_id=store_id;
        //ImportList.listGet();
        ImportList.table.reload();
        '])?>
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
        grid-template-columns: repeat(17, min-content);
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
    
    
    .action_button{
        display:inline-block;
        border-radius:5px;
        background-color:#eee;
        padding:5px;
        margin:5px;
        cursor:pointer;
    }
</style>
<script type="text/javascript">
    ImportList={
        init:function (){
            ImportList.table.init();
            $("#import_table_actions").click(function(e){
                let $button=$(e.target);
                let cmds=$button.data('cmd');
                if( !cmds ){
                    return;
                }
                let commands=cmds.split(',');
                let message='';
                let url='';
                for( let cmd of commands ){
                    if( !cmd ){
                        continue;
                    }
                    let action=cmd.split(':')[0];
                    let count=cmd.split(':')[1];
                    
                    if( action==='add' && count>0 ){
                        url=url?'importAll':'importCreate';
                        message+=`\nДобавить ${count} товаров?`;
                    }
                    if( action==='update' && count>0 ){
                        url=url?'importAll':'importUpdate';
                        message+=`\nОбновить ${count} товаров?`;
                    }
                    if( action==='delete' && count>0 ){
                        url=url?'importAll':'importDelete';
                        message+=`\nУдалить ${count} товаров?`;
                    }
                }
                if( url && confirm(message) ){
                    let selectors=ImportList.table.colconfigGet();
                    let request={
                        holder:ImportList.table.loadrequest.holder,
                        holder_id:ImportList.table.loadrequest.holder_id,
                        target:'product',
                        colconfig:selectors.colconfig
                    };
                    $.post(`/Importer/${url}`,JSON.stringify(request)).done(()=>{
                        ImportList.listAnalyse().done(function(){
                            ImportList.table.reload();
                        });
                    });
                }
            });
        },
        // listGet:function(){
        //     let request={
        //         target:'product',
        //         holder:ImportList.table.loadrequest.holder,
        //         holder_id:ImportList.table.loadrequest.holder_id
        //     };
        //     return $.post('/Importer/listGet',request).done((response,status)=>{
        //         if( status==='success' ){
        //             ImportList.listAnalyseRenderButtons(response);
        //         }
        //     });
        // },
        listAnalyse:function(){
            let selectors=ImportList.table.colconfigGet();
            if( selectors.dublicate_reseted ){
                return false;
            }
            let request={
                target:'product',
                holder:ImportList.table.loadrequest.holder,
                holder_id:ImportList.table.loadrequest.holder_id,
                columns:selectors.colconfig
            };
            return $.post('/Importer/listAnalyse',JSON.stringify(request)).done((response,status)=>{
                if( status==='success' ){
                    ImportList.listAnalyseRenderButtons(response);
                }
            });
        },
        listAnalyseRenderButtons:function( actions ){
            let upload=``;
            let all='';
            let all_cmds='';
            let add='';
            let update='';
            let del='';
            let skip='';
            for(let i in actions){
                let action=actions[i];
                if( action.action==='add' && action.row_count>0 ){
                    let cmd=`${action.action}:${action.row_count}`;
                    all_cmds+=','+cmd;
                    add=`<div data-cmd="${cmd}" class="action_button" style="background-color:#cfc">Добавить товары (${action.row_count})</div>`;
                }
                if( action.action==='update' && action.row_count>0 ){
                    let cmd=`${action.action}:${action.row_count}`;
                    all_cmds+=','+cmd;
                    update=`<div data-cmd="${cmd}" class="action_button" style="background-color:#def">Обновить товары (${action.row_count})</div>`;
                }
                if( action.action==='delete' && action.row_count>0 ){
                    let cmd=`${action.action}:${action.row_count}`;
                    all_cmds+=','+cmd;
                    del=`<div data-cmd="${cmd}" class="action_button" style="background-color:#fdd"><i class="fa fa-fast-trash"></i> Удалить товары (${action.row_count})</div>`;
                }
                if( action.action==='skip' && action.row_count>0 ){
                    let cmd=`${action.action}:${action.row_count}`;
                    skip=`<div data-cmd="${cmd}" class="action_button">Пропустить ${action.row_count}</div>`;
                }
            }
            if(all_cmds){
                all=`<div data-cmd="${all_cmds}" class="action_button" style="background-color:#ddd"><i class="fas fa-file-import"></i> Импортировать всё</div>`;
            }
            $("#import_table_actions").html(upload+add+update+del+skip+all);
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
            ImportList.fileUploadFormData.set('target','product');
            ImportList.fileUploadFormData.set('holder','store');
            ImportList.fileUploadFormData.set('holder_id',ImportList.table.loadrequest.holder_id);
            
            ImportList.fileUploadXhr.open("POST", url, true);
            ImportList.fileUploadXhr.onreadystatechange = function() {
                if (ImportList.fileUploadXhr.readyState === 4 && ImportList.fileUploadXhr.status === 201) {
                    ImportList.listAnalyse().done(function(){
                        ImportList.table.reload();
                    });
                }
            };
        },
        table:{
            cols:[
                {field:"",name:"-пропустить-"},
                {field:"product_name",name:"Название",required:1},
                {field:"product_price",name:"Цена",required:1},
                {field:"product_quantity",name:"Остаток",required:1},
                {field:"product_code",name:"Код товара"},
                {field:"product_description",name:"Описание"},
                {field:"product_weight",name:"Вес кг"},
                {field:"product_unit",name:"Единица"},
                {field:"product_category_name",name:"Категория"},
                {field:"product_barcode",name:"Штрихкод"},
                {field:"product_promo_price",name:"Акция Цена"},
                {field:"product_promo_start",name:"Акция Начало"},
                {field:"product_promo_finish",name:"Акция Конец"},
                {field:"is_counted",name:"Учет остатков?"},
            ],
            init:function(){
                ImportList.table.theadInit();
                $("#import_table_head").change(function(e){
                    ImportList.listAnalyse().done(function(){
                        ImportList.table.reload();
                    });
                });
                
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
                    $.post('/Importer/listDelete',{ids:ids.join(',')}).done(function(){
                        ImportList.listAnalyse().done(function(){
                            ImportList.table.reload();
                        });
                    });
                }
            },
            listTruncate:function(){
                if( confirm(`Очистить таблицу?`) ){
                    $.post('/Importer/listDelete',{ids:''}).done(function(){
                        ImportList.listAnalyse().done(function(){
                            ImportList.table.reload();
                        });
                    });
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
                ImportList.listAnalyse()
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
                this.loadrequest.holder=ImportList.table.loadrequest.holder;
                this.loadrequest.holder_id=ImportList.table.loadrequest.holder_id;
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
            appendRows:function( list ){
                for( let row of list ){
                    let rowhtml='';
                    for(let i=0;i<=16;i++){
                        if( i>0 ){
                            let col=`C${i}`;
                            let val=row[col];
                            let minwidth=val?(val.length>40?'200px':'100px'):'0px';
                            rowhtml+=`<div data-editable="1" data-field="${col}" style="min-width:${minwidth}">${row[col]||''}</div>`;// style="grid-area:C${i}"
                        } else {
                            let icon='<i class="fa fa-fast-forward" aria-hidden="true" title="пропустить" style="color:gray"></i>';
                            if( row.action==='delete' ){
                                icon='<i class="fa fa-trash" aria-hidden="true" title="удалить" style="color:red"></i>';
                            }
                            if( row.action==='update' ){
                                icon='<i class="fa fa-refresh" aria-hidden="true" title="обновить" style="color:blue"></i>';
                            }
                            if( row.action==='add' ){
                                icon='<i class="fa fa-plus" aria-hidden="true" title="добавить" style="color:green"></i>';
                            }
                            if( row.action==='done' ){
                                icon='<i class="fa fa-check" aria-hidden="true" title="выполнено" style="color:gray"></i>';
                            }
                            rowhtml+=`<div style="min-width:0px;background-color:white;">${icon}</div>`;
                        }
                    }
                    $("#import_table").append(`<div class="import_table_row" data-id="${row['id']}">${rowhtml}</div>`);
                }
            },
            theadInit:function(){
                let html='';
                for(let i=0;i<=16;i++){
                    if( i>0 ){
                        let selector=ImportList.table.theadSelectorGet(i);
                        html+=`<div>${selector}</div>`;
                    } else {
                        html+=`<div> </div>`;
                    }
                    
                }
                $("#import_table_head").html(html);
            },
            theadSelectorGet:function(i){
                let val=localStorage.getItem(`importerC${i}`)||0;
                let html=`<select data-col="C${i}">`;
                for(let item of ImportList.table.cols){
                    let is_required=(item.required)?' style="font-weight:bold"':'';
                    let is_selected=(val==item.field)?'selected':'';
                    html+=`<option ${is_required} value="${item.field}" ${is_selected}>${item.name}</option>`;
                }
                html+=`</select>`;
                return html;
            },
            colconfigGet:function(){
                let dublicate_reseted=false;
                function is_distinct( val, list ){
                    for(let i in list){
                        if(list[i]===val){
                            return false;
                        }
                    }
                    return true;
                }
                let colconfig={};
                $("#import_table_head select").each(function(){
                    let $select=$(this);
                    let col=$select.data('col');
                    let val=$select.val();
                    localStorage.setItem(`importer${col}`,val);
                    if( val ){
                        if( is_distinct( val, colconfig ) ){
                            colconfig[val]=col;
                        } else {
                            $select.val("");
                            dublicate_reseted=true;
                        }
                    }
                });
                return {colconfig,dublicate_reseted};
            },
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
<div style="padding: 20px;">
    
    <div>
        <div style="background-color:#ddd" class="action_button" onclick='$("#importlist_uploader").click()'><span class="fa fa-upload"></span> Загрузить файл XLSX</div> |
        <div style="background-color:#ddd" class="action_button" onclick='ImportList.listAnalyse()'>Анализ</div> | 
        <div id="import_table_actions" style="display: inline-block"></div>
    </div>
    
    <div class="segment" style="min-width: calc(100% - 20px);width: min-content">
        <a href='javascript:ImportList.table.reload()'>Обновить</a> | 
        <a href='javascript:ImportList.table.listDelete()'>Удалить строки</a> | 
        <a href='javascript:ImportList.table.listTruncate()'>Очистить таблицу</a>
        <div id="import_table" style="margin-top: 10px;">
            <div id="import_table_head"></div>
        </div>
    </div>
    <div id="import_table_loader"></div>
</div>
<input type="file" id="importlist_uploader" name="items[]" style="display:none" onchange="ImportList.fileUpload(this.files)">
<?=$html_after??'' ?>
<?=view('home/footer')?>