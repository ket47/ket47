<?php

$user_group_count=count($user_group_list);

function has_permission( $class, $method, $haystack ){
    foreach($haystack as $perm){
        if($perm->permited_class==$class && $perm->permited_method==$method){
            return true;
        }
    }
    return false;
}
?>
<script
        src="https://code.jquery.com/jquery-3.5.1.min.js"
        crossorigin="anonymous"></script>
<script>

$(function(){
    $("form").change(function(e){
        var $check=$(e.target);
        var is_enabled=$check.prop('checked')?1:0;
        var path=$check.attr('name').split('.');
        var permited_group=path[0];
        var permited_class=path[1];
        var permited_method=path[2];
        var request={
            permited_group:permited_group,
            permited_class:permited_class,
            permited_method:permited_method,
            is_enabled:is_enabled
        };
        $.post("/Admin/Permission/permissionSave",request,function(){
            
        });
    });
});
</script>
<form method="post" action="#">
    <table>
        <thead>
            <tr>
                <th></th>
                <?php foreach($user_group_list as $user_group): ?>
                <th>
                    <?=$user_group->user_group_name?>
                </th>
                <?php endforeach;?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($method_list as $model_name=>$model_methods): ?>
            <tr>
                <td colspan="<?=($user_group_count+2)?>"><h2><?=$model_name?></h2></td>
            </tr>
            <?php foreach($model_methods as $method): ?>
            <tr>
                <td>
                    <?=$method?>
                </td>
                <?php foreach($user_group_list as $user_group): ?>
                <td style="text-align: center">
                    <input type="checkbox" name="<?="{$user_group->user_group_id}.{$model_name}.{$method}"?>"
                        <?=has_permission($model_name,$method,$user_group->permission_list)?"checked=checked":"0"?>
                        >
                </td>
                <?php endforeach;?>
            </tr>
            <?php endforeach; ?>
        <?php endforeach;?>
        </tbody>
    </table>
</form>

<style>
    td{
        padding: 5px;
    }
</style>