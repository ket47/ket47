<?=view('home/header')?>
<style>
    table{
        border-collapse: collapse;
        border:1px solid #ccc;
        width: 100%;
    }
    table td,table th{
        border-bottom:1px solid #ccc;
        padding: 1px;
    }
    table tr:nth-child(even){
        background-color: #f5fcff;
    }
    input{
        width:100%;
        border:none;
        margin: 0px;
    }
    textarea{
        width:calc( 100% - 10px );
        height:70px;
    }
</style>
<script>
    TaskManager={
        init:function(){
            $("table").click(function(e){
                let $node=$(e.target);
                let action=$node.data('action');
                let task_id=$node.data('task_id');
                if( !action ){
                    return;
                }
                TaskManager.actions[action](task_id);
            });
            $("input,textarea").change(function(e){
                let $node=$(e.target);
                let task_id=$node.data('task_id');
                if( !task_id ){
                    return;
                }
                let field=$node.data('field');
                let value=$node.val();
                TaskManager.actions.update(task_id,field,value);
            });
        },
        actions:{
            delete:function(task_id){
                if(!confirm("Удалить задание?")){
                    return;
                }
                $.post("/Admin/TaskManager/itemDelete",{task_id}).done(function(){
                    location.reload();
                });
            },
            create:function(){
                let task_name=prompt('Название нового задания','Новое задание');
                $.post("/Admin/TaskManager/itemCreate",{task_name}).done(function(){
                    location.reload();
                });
            },
            update:function(task_id,field,value){
                let request={
                    task_id
                };
                request[field]=value;
                $.post("/Admin/TaskManager/itemUpdate",JSON.stringify(request));
            }
        }
    };
    $(TaskManager.init);
</script>
<div style="padding: 20px;">
<div class="segment">
    <table>
        <tr>
            <th></th>
            <th>Задание</th>
            <th>Программа</th>
            <th>Результат</th>
            <th>Интервал д/ч/м</th>
            <th>След. запуск</th>
            <th>Прошлый запуск</th>
<!--            <th>Status</th>-->
        </tr>
        <?php foreach( $task_list as $task): ?>
        <tr>
            <td style="width:30px;text-align: center;color:red;"><i class="fa fa-trash" data-task_id="<?=$task->task_id?>" data-action="delete"></i></td>
            <td>
                <input value="<?=$task->task_name?>" data-field="task_name" data-task_id="<?=$task->task_id?>">
            </td>
            <td>
                <textarea data-field="task_programm" data-task_id="<?=$task->task_id?>"><?=$task->task_programm?></textarea>
            </td>
            <td>
                <input readonly="readonly" value="<?=$task->task_result?>" data-field="task_result" data-task_id="<?=$task->task_id?>">
            </td>
            <td>
                <input size="2" style="width:30%;" value="<?=$task->task_interval_day?>" data-field="task_interval_day" data-task_id="<?=$task->task_id?>">
                <input size="2" style="width:30%;" value="<?=$task->task_interval_hour?>" data-field="task_interval_hour" data-task_id="<?=$task->task_id?>">
                <input size="2" style="width:30%;" value="<?=$task->task_interval_min?>" data-field="task_interval_min" data-task_id="<?=$task->task_id?>">
            </td>
            <td>
                <input value="<?=$task->task_next_start?>" data-field="task_next_start" data-task_id="<?=$task->task_id?>">
            </td>
            <td>
                <input readonly="readonly" value="<?=$task->task_last_start?>" data-field="task_last_start" data-task_id="<?=$task->task_id?>">
            </td>
<!--            <td>
                <input readonly="readonly" value="<?=$task->task_status?>" data-field="task_status" data-task_id="<?=$task->task_id?>">
            </td>-->
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="8" style="width:30px;text-align: center;color:green;">
                <i class="fa fa-plus" data-action="create"> Создать задание</i>
            </td>
        </tr>
    </table>
</div>
</div>
<?=view('home/footer')?>