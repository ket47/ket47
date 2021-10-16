<?=view('home/header')?>
<?php
function has_permission( $permission_list,$role,$class,$method,$right ){
    if( !$permission_list ){
        return false;
    }
    foreach($permission_list as $perm){
        if($perm->permited_class==$class && $perm->permited_method==$method && str_contains($perm->$role,$right) ){
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
        var path=$check.data('path').split('.');
        var permited_owner=path[0];
        var permited_class=path[1];
        var permited_method=path[2];
        var permissions=[];
        $(`input[data-path='${permited_owner}.${permited_class}.${permited_method}']`).each(function(i,item){
            var $check=$(item);
            var is_enabled=$check.prop('checked')?1:0;
            if( is_enabled ){
                permissions.push($check.data('right'));
            }
        });
        var request={
            permited_owner:permited_owner,
            permited_class:permited_class,
            permited_method:permited_method,
            permited_rights:permissions.sort().join()
        };
        $.post("/Admin/Permission/permissionSave",request,function(){
            
        });
    });
});
</script>
<div class="segment" style="margin: 20px">
<form method="post" action="#">
    <table>
        <thead>
            <tr>
                <th></th>
                <th>
                    Owner
                </th>
                <th>
                    Ally
                </th>
                <th>
                    Other
                </th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($method_list as $class=>$model_methods): ?>
            <tr>
                <td colspan="<?=(count($permission_role_list)+2)?>"><h2><?=$class?></h2></td>
            </tr>
            <?php foreach($model_methods as $method): ?>
            <tr>
                <td style="width:100px;">
                    <?=$method?>
                </td>
                <?php foreach($permission_role_list as $role): ?>
                <td style="text-align: center">
                    <?php foreach($permission_right_list as $right):?>
                    <div class="right <?=$right?>">
                        <?=$right?>
                        <input type="checkbox" 
                               data-path="<?="{$role}.{$class}.{$method}"?>"
                               data-right="<?=$right?>"
                               <?=has_permission($permission_list,$role,$class,$method,$right)?"checked=checked":""?>
                            >
                    </div>
                    <?php endforeach;?>
                </td>
                <?php endforeach;?>
            </tr>
            <?php endforeach; ?>
        <?php endforeach;?>
        </tbody>
    </table>
</form>
</div>
<style>
    th{
        width: 150px;
    }
    td{
        padding: 5px;
    }
    .right{
        display: inline-block;
        width:20px;
        text-align: center;
    }
    .r{
        color:green;
    }
    .w{
        color:orange;
    }
    .d{
        color:red;
    }
</style>
<?=view('home/footer')?>