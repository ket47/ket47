<?=view('home/header')?>
<style>
    td{
        width:200px;
        padding: 0px;
        background-color: white;
    }
    input{
        width:100%;
        border:none;
        margin: 0px;
    }
</style>
<script>
    GroupManager={
        init:function(){
            $("table").click(function(e){
                let $node=$(e.target);
                let action=$node.data('action');
                let group_id=$node.data('group_id');
                let type=$node.data('type');
                let group_table=(type==='product')?'product_group_list':
                        (type==='store')?'store_group_list':
                        (type==='user')?'user_group_list':'';
                if( !action ){
                    return;
                }
                GroupManager.actions[action](group_table,group_id);
            });
            $("input").change(function(e){
                let $node=$(e.target);
                let group_id=$node.data('group_id');
                if( !group_id ){
                    return;
                }
                let type=$node.data('type');
                let val=$node.val();
                let group_table=(type==='product')?'product_group_list':
                        (type==='store')?'store_group_list':
                        (type==='user')?'user_group_list':'';
                GroupManager.actions.update(group_table,group_id,val);
            });
        },
        actions:{
            delete:function(group_table,group_id){
                if(!confirm("Осторожно все члены группы выйдут из нее! Удалить группу?")){
                    return;
                }
                $.post("/Admin/GroupManager/itemDelete",{group_table,group_id}).done(function(){
                    location.reload();
                });
            },
            create:function(group_table,group_parent_id){
                let group_name=prompt('Название новой группы','Новая группа');
                $.post("/Admin/GroupManager/itemCreate",{group_table,group_parent_id,group_name}).done(function(){
                    location.reload();
                });
            },
            update:function(group_table,group_id,group_name){
                let request={
                    group_table,group_id,group_name
                };
                $.post("/Admin/GroupManager/itemUpdate",JSON.stringify(request));
            },
            upload:function(group_table,group_id){
                GroupManager.fileUploadInit(group_table,group_id);
            }
        },
        fileUpload:function(filelist){
            if( filelist.length ){
                let attached_count=0;
                let total_size_limit=10*1024*1024;
                for(let fl of filelist){
                    total_size_limit-=fl.size;
                    if(total_size_limit<0){
                        alert("Разовый объем файлов должен быть не больше 10МБ.\nПрикреплено только: "+attached_count+"файлов");
                        break;
                    }
                    GroupManager.fileUploadFormData.append("files[]", fl);
                    attached_count++;
                }
                GroupManager.fileUploadXhr.send(GroupManager.fileUploadFormData);
            }
        },
        fileUploadFormData:null,
        fileUploadXhr:null,
        fileUploadInit:function( group_table,image_holder_id ){
            var url = '/Admin/GroupManager/fileUpload';
            GroupManager.fileUploadXhr = new XMLHttpRequest();
            GroupManager.fileUploadFormData = new FormData();
            GroupManager.fileUploadFormData.set('group_table',group_table);
            GroupManager.fileUploadFormData.set('image_holder_id',image_holder_id);
            
            GroupManager.fileUploadXhr.open("POST", url, true);
            GroupManager.fileUploadXhr.onreadystatechange = function() {
                if (GroupManager.fileUploadXhr.readyState === 4) {
                    App.xhr.onresponse(GroupManager.fileUploadXhr);
                    GroupManager.fileUploadXhr.status === 201 && location.reload();
                }
            };
            $('#groupmanager_uploader').click();
        },
        imageDelete:function(image_id){
            $.post("/Admin/GroupManager/imageDelete",{image_id}).done(function(){
                location.reload();
            });
        }
    };
    $(GroupManager.init);
</script>
<input type="file" id="groupmanager_uploader" name="items[]" multiple style="display:none" onchange="GroupManager.fileUpload(this.files)">
<div style="padding: 20px;">
<div class="segment">
    <?php foreach( $tables as $table): ?>
    <h2><?=$table->name?></h2>
    <table>
        <tr>
            <th></th>
            <th>Глав. категория</th>
            <th>Категория</th>
            <th>Изобр.</th>
        </tr>
        <tr>
            <td style="width:30px;"></td>
            <td colspan="3" style="color:green;">
                <i class="fa fa-plus" data-group_id="0" data-type="<?=$table->type?>" data-action="create"></i>
            </td>
        </tr>
        <?php foreach( $table->entries as $group): ?>
        <tr>
            <td style="width:30px;text-align: center;color:red;"><i class="fa fa-trash" data-group_id="<?=$group->group_id?>" data-type="<?=$table->type?>" data-action="delete"></i></td>
            <?php if($group->group_parent_id):?>
            <td></td>
            <td>
                <input value="<?=$group->group_name?>" data-group_id="<?=$group->group_id?>" data-type="<?=$table->type?>">
            </td>
            <?php else: ?>
            <td>
                <input value="<?=$group->group_name?>" data-group_id="<?=$group->group_id?>" data-type="<?=$table->type?>">
            </td>
            <td style="color:green;"><i class="fa fa-plus" data-group_id="<?=$group->group_id?>" data-type="<?=$table->type?>" data-action="create"></i></td>
            <?php endif;?>
            <td style="width:100px">
                <?php if($group->image_hash): ?>
                <div style="background-image: url(/image/get.php/<?= $group->image_hash ?>.100.100.webp);text-align: center;border-radius: 5px;">
                    <a href="javascript:GroupManager.imageDelete(<?=$group->image_id?>)" data-group_id="<?=$group->group_id?>" data-type="<?=$table->type?>" data-action="imagedelete"><div class="fa fa-trash" style="color:red"></div></a>
                    <a href="/image/get.php/<?= $group->image_hash ?>.1024.1024.webp" target="imagepreview"><div class="fa fa-eye" style="color:blue"></div></a>
                    <br><br>
                </div>
                <?php else: ?>
                <div class="vcenter" style="border-radius: 5px;background-color: #eee"
                     data-group_id="<?=$group->group_id?>" data-type="<?=$table->type?>" data-action="upload">
                    <i class="fa fa-upload" data-group_id="<?=$group->group_id?>" data-type="<?=$table->type?>" data-action="upload"></i>
                </div>
                <?php endif;?>
            </td>
            <td>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endforeach; ?>
</div>
</div>
<?=view('home/footer')?>