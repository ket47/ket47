<?=view('home/header')?>
<style>
    table{
        width: 100%;
    }
    td{
        padding: 10px;
        background-color: white;
    }
    input,textarea{
        width:100%;
        border:solid 1px #eee;
        margin: 0px;
    }
</style>
<script>
    PrefManager={
        init:function(){
            $("table").click(function(e){
                let $node=$(e.target);
                let action=$node.data('action');
                let pref_name=$node.data('pref_name');
                if( !action ){
                    return;
                }
                PrefManager.actions[action](pref_name);
            });
            $("input,textarea").change(function(e){
                let $node=$(e.target);
                let pref_name=$node.data('pref_name');
                if( !pref_name ){
                    return;
                }
                let field=$node.data('field');
                let value=$node.val();
                PrefManager.actions.update(pref_name,field,value);
            });
        },
        actions:{
            delete:function(pref_name){
                if( !confirm("Delete?") ){
                    return;
                }
                $.post("/Admin/PrefManager/itemDelete",{pref_name}).done(function(){
                    location.reload();
                });
            },
            create:function(){
                let pref_name=prompt('Название новой настройки','new_pref');
                if( !pref_name ){
                    return;
                }
                $.post("/Admin/PrefManager/itemCreate",{pref_name}).done(function(){
                    location.reload();
                });
            },
            update:function(pref_name,field,value){
                let request={
                    pref_name
                };
                request[field]=value;
                $.post("/Admin/PrefManager/itemUpdate",JSON.stringify(request));
            },
        },
    };
    $(PrefManager.init);
</script>
<input type="file" id="groupmanager_uploader" name="items[]" multiple style="display:none" onchange="PrefManager.fileUpload(this.files)">
<div style="padding: 20px;">
<div class="segment">
    <h2>Preferences</h2>
    <table>
        <tr>
            <th><i class="fa fa-plus" data-pref_name="0" data-action="create" style="color:green"></i></th>
            <th>Настройка</th>
            <th>Значение</th>
            <th>JSON</th>
        </tr>
        <?php foreach( $pref_list as $pref): ?>
        <tr>
            <td style="width:30px;text-align: center;color:red;vertical-align: top">
                <i class="fa fa-trash" data-pref_name="<?=$pref->pref_name?>" data-action="delete"></i>
            </td>
            <td style="vertical-align: top">
                <b><?=$pref->pref_name?></b>
            </td>
            <td style="vertical-align: top">
                <input data-field="pref_value" value="<?=$pref->pref_value?>" data-pref_name="<?=$pref->pref_name?>">
            </td>
            <td style="vertical-align: top">
                <textarea data-field="pref_json" data-pref_name="<?=$pref->pref_name?>"><?=$pref->pref_json?></textarea>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</div>
<?=view('home/footer')?>