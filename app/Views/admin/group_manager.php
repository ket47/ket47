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
            }
        }
    };
    $(GroupManager.init);
</script>
<div style="padding: 20px;">
<div class="segment">
    <?php foreach( $tables as $table): ?>
    <h2><?=$table->name?></h2>
    <table>
        <tr>
            <td style="width:30px;"></td>
            <td colspan="2" style="color:green;">
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
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endforeach; ?>
</div>
</div>
<?=view('home/footer')?>