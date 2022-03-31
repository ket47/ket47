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
            $("table,#default_location").click(function(e){
                let $node=$(e.target);
                let action=$node.data('action') || $node.parent().data('action');
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
                $.post("/Admin/PrefManager/itemSave",JSON.stringify(request));
            },
            locationAdd(){
                App.loadWindow('/Location/pickerModal').progress(function(status,data){
                    if(status==='selected'){
                        let request={};
                        request.location_holder_id=-1;
                        request.location_type_id=0;
                        request.location_latitude=data.coordsSelected[0];
                        request.location_longitude=data.coordsSelected[1];
                        request.location_address=data.addressSelected;
                        $.post('/Admin/PrefManager/locationDefaultSave',request).done(function(){
                            location.reload();
                        });
                    }
                });
            },
            locationDelete(){
                $.post('/Admin/PrefManager/locationDefaultDelete').done(function(){
                    location.reload();
                });
            }
        },
    };
    $(PrefManager.init);
</script>
<input type="file" id="groupmanager_uploader" name="items[]" multiple style="display:none" onchange="PrefManager.fileUpload(this.files)">
<div style="padding: 20px;">
<?php 
    $default_prefs=[
        'shipping_fee',
        'customer_confirmed_timeout_min'
    ];
    foreach( $pref_list as $pref ){
        $default_pref_index=array_search($pref->pref_name,$default_prefs);
        if( $default_pref_index>-1 ){
            array_splice($default_prefs,$default_pref_index,1);
        }
    }
    //print_r();
    foreach($default_prefs as $pref_name){
        array_unshift($pref_list,(object)[
            'pref_name'=>$pref_name,
            'pref_value'=>null,
            'pref_json'=>null
        ]);
    }
?>




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

    <div class="segment">
        <h2>Адрес по умолчанию</h2>
        <div id="default_location" style="display:grid;grid-template-columns:1fr 10fr 1fr;gap:10px">
            <?php if($default_location??0): ?>
            <div style="display:contents;" class="row">
                <div><i class="fa fa-map-marker"></i></div>
                <div><?=$default_location->location_address??'' ?></div>
                <div data-action="locationDelete" data-location_id="<?=$default_location->location_id??0  ?>"><i class="fa fa-trash" style="color:red"></i></div>
            </div>
            <?php else: ?>
            <div style="display:contents;cursor:pointer" data-action="locationAdd">
                <div>
                    <i class="fa fa-plus" style="color:green"></i>
                </div>
                <div> Добавить адрес по умолчанию</div>
                <div></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
</div>
<?=view('home/footer')?>