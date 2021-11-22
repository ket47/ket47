<?php

namespace App\Controllers;

class Task extends \App\Controllers\BaseController{

    public function run(){
        $TaskModel=model('TaskModel');
        $filter=['is_pending'=>1];
        $pending_tasks=$TaskModel->listGet($filter);
        foreach($pending_tasks as $task){
            $result=$this->itemExecute($task);
            echo date('H:i:s')." $task->task_name: $result\n";
        }
        return true;
    }
    
    private function itemExecute( $task ){
        $programm= json_decode($task->task_programm);
        if( !is_array($programm) ){
            return true;
        }
        foreach($programm as $command){
            if( !$command->method??0 ){
                return false;
            }
            $arguments=$command->arguments??[];
            foreach ($arguments as $arg){
                if($arg==='PREV-RESULT'){
                    $arg=json_decode($task->task_result);
                }
            }
            if( $command->model??0 ){
                $Class=model($command->model);
            } else if( $command->controller??0 ){
                $Class=new $command->controller($this->request, $this->response, $this->logger);
            } else {
                $Class=$this;
            }
            $task_result=$Class->{$command->method}(...$arguments);
        }
        $TaskModel=model('TaskModel');
        if( $task->is_singlerun ){
            $TaskModel->itemDelete($task->task_id);
            return true;
        }
        $task->task_result=json_encode($task_result);
        $task->task_last_start=date('Y-m-d H:i:s');
        $task->task_next_start= new \CodeIgniter\I18n\Time("$task->task_interval_day days $task->task_interval_hour hours $task->task_interval_min minutes");
        $task->task_result=$task_result;
        $TaskModel->update($task->task_id,$task);
        return $task_result;
    }
    
    private function taskPurge(){
        $trashed_days= getenv('app.trashed_days');
        model("ImageModel")->listPurge($trashed_days);
        model("ProductModel")->listPurge($trashed_days);
        model("StoreModel")->listPurge($trashed_days);
        model("OrderModel")->listPurge($trashed_days);
        model("UserModel")->listPurge($trashed_days);
        return 'purged';
    }
    
    private function orderResetStage( $stage_from, $stage_to, $order_id=null ){
        $OrderModel=model('OrderModel');
        $UserModel=model('UserModel');
        $UserModel->systemUserLogin();
        if( $order_id ){
            $OrderModel->where('order_id',$order_id);
        }
        $OrderModel->join('order_group_list ogl',"order_group_id=group_id");
        $OrderModel->where('ogl.group_type',$stage_from);
        $orders=$OrderModel->get()->getResult();
        $result='';
        foreach($orders as $order){
            $result.=$OrderModel->itemStageCreate( $order->order_id, $stage_to );
        }
        $UserModel->systemUserLogout();
        return $result;
    }
}
