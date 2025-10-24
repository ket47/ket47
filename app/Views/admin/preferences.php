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
                        request.location_group_id=0;
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
    $pref_map=[
        'admin_email'=>                         ['Админ','email'],
        
        'customer_confirmed_timeout_min'=>      ['Автосброс Статусов (минут)','Подтвержден-Корзина'],
        'customer_start_timeout_min'=>          ['Автосброс Статусов (минут)','На обработке-Отказ поставщика'],
        'delivery_no_courier_timeout_min'=>     ['Автосброс Статусов (минут)','Собран-Курьер не найден'],
        'delivery_finish_timeout_min'=>         ['Автосброс Статусов (минут)','Доставлен-Завершен'],

        'delivery_cost'=>                       ['Доставка','Подача'],
        'delivery_fee_distance'=>               ['Доставка','За километр'],

        'delivery_heavy_level'=>                ['Повышенная доставка','Уровень 0 | 1 | 2 | 3'],
        'delivery_heavy_cost_1'=>               ['Повышенная доставка','Повышение 1'],
        'delivery_heavy_bonus_1'=>              ['Повышенная доставка','Бонус 1'],
        'delivery_heavy_cost_2'=>               ['Повышенная доставка','Повышение 2'],
        'delivery_heavy_bonus_2'=>              ['Повышенная доставка','Бонус 2'],
        'delivery_heavy_cost_3'=>               ['Повышенная доставка','Повышение 3'],
        'delivery_heavy_bonus_3'=>              ['Повышенная доставка','Бонус 3'],

        'delivery_sweet_start_hour'=>           ['Сладкие часы доставка','Начало'],
        'delivery_sweet_finish_hour'=>          ['Сладкие часы доставка','Конец'],
        'delivery_sweet_ratio'=>                ['Сладкие часы доставка','Скидка %'],

        'shiftStartHour'=>                      ['Смена','Начало'],
        'shiftEndHour'=>                        ['Смена','Конец'],
    ];
    foreach( $pref_map as $pref_name=>$pref){
        $pref_map[$pref_name][2]='';
        $pref_map[$pref_name][3]='';
        foreach( $pref_list as $saved_pref ){
            if($pref_name==$saved_pref->pref_name){
                $pref_map[$pref_name][2]=$saved_pref->pref_value;
                $pref_map[$pref_name][3]=$saved_pref->pref_json;
                break;
            }
        }
    }
    $pref_group='';
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
            <?php foreach( $pref_map as $pref_name=>$pref): ?>

            <?php if($pref[0]!=$pref_group): $pref_group=$pref[0];?>
            <tr>
                <td colspan="4">
                <h2><?=$pref[0]?></h2>
                </td>
            </tr>
            <?php endif;?>

            <tr>
                <td style="width:30px;text-align: center;color:red;vertical-align: top">
                    <i class="fa fa-trash" data-pref_name="<?=$pref_name?>" data-action="delete"></i>
                </td>
                <td style="vertical-align: top" title="<?=$pref_name?>">
                    <b><?=$pref[1]?></b>
                </td>
                <td style="vertical-align: top">
                    <input data-field="pref_value" value="<?=$pref[2]?>" data-pref_name="<?=$pref_name?>">
                </td>
                <td style="vertical-align: top">
                    <!--<textarea data-field="pref_json" data-pref_name="<?=$pref_name?>"><?=$pref[3]?></textarea>-->
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