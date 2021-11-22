<?php

namespace App\Controllers;

class Task extends \App\Controllers\BaseController{
    
    public function jobDo(){
        set_time_limit(0);
        session_write_close();
        
        $time_limit = 60 * 1;
        $time_limit += rand(0, 60 * 1);
        $start_time = time();
        
        require_once '../app/ThirdParty/Credis/Client.php';
        $predis = new \Credis_Client();
        $worker_id = rand(100, 999);
        header('Content-type: text/plain');
        echo "Worker #$worker_id Starting.".date('H:i:s')."\n";
        $predis->hset('worker.status', $worker_id, 'Started');
        $predis->hset('worker.status.last_time', $worker_id, time());
        echo "	Waiting for a Job";
        while(time() < $start_time + $time_limit){
            $predis->hset('worker.status', $worker_id, 'Waiting');
            $predis->hset('worker.status.last_time', $worker_id, time());
            $job = $predis->blpop('queue.priority.normal',10);
            if($job && $job[1]){
                echo "\nJob Started at".date('H:i:s')."";
                $predis->hset('worker.status', $worker_id, 'Working');
                $predis->hset('worker.status.last_time', $worker_id, time());
                $task= json_decode($job[1]);
                $result=$this->itemExecute( $task );
                echo " Done($result)!\n";
            }
            else{
                echo ".";
            }
        }
        $predis->hset('worker.status', $worker_id, 'Closed');
        $predis->hset('worker.status.last_time', $worker_id, time());
        echo "\nWorker #$worker_id Finished! Goodbye!\n";
    }
    
    
    public function jobCreate(){
        echo $sms_job=json_encode([
            'task_name'=>"customer_start Notify #order_id",
            'task_programm'=>[
                    ['model'=>'MessageModel','method'=>'listSend','arguments'=>[[(object)[
            'message_reciever_id'=>44,
            'message_transport'=>'sms',
            'message_text'=>'Hello REDDIISS '.date('H:i:s')
        ]],true]]
                ],
            'is_singlerun'=>1
        ]);
        
        helper('job');
        jobCreate($sms_job);
    }

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
        $programm=$task->task_programm;
        if( !is_array($programm) && !is_array($programm= json_decode($programm)) ){
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
            try{
                $task_result=$Class->{$command->method}(...$arguments);
            } catch (\Exception $e){
                log_message('error', 'TASK EXECUTION ERROR:'. json_encode($task)."\n".$e->getTrace() );
            }
        }
        if( isset($task->task_id) ){//task from task_list
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
        }
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
