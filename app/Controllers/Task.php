<?php

namespace App\Controllers;

class Task extends \App\Controllers\BaseController{
    private $workerLifeTime=1*60;//1min
    private $workerLifeSpread=1*60;//1min
    private $timedJobInterval=1*60;//1 hour *60



    public function jobDo(){
        require_once '../app/ThirdParty/Credis/Client.php';
        set_time_limit(3600);
        session_write_close();
        
        $time_limit = $this->workerLifeTime;
        $time_limit += rand(0, $this->workerLifeSpread);
        $start_time = time();
        $worker_id = rand(100, 999);
        
        echo "Worker #$worker_id Starting.".date('H:i:s')."\n	Waiting for a Job";
        $predis = new \Credis_Client();
        $this->timedJobCheck($predis);
        while(time() < $start_time + $time_limit){
            $job = $predis->blPop('queue.priority.normal',4);
            if(!$job || !$job[1]){
                echo ".";
                continue;
            }
            $task= json_decode($job[1]);
            if( !$task ){
                echo "Invalid job syntax: ".json_last_error_msg();
            }
            if( isset($task->task_next_start_time) && $task->task_next_start_time>time() ){
                $final_count=$predis->rPush('queue.priority.normal', $job[1]);
                if( $final_count<2 ){
                    sleep(1);//if only one timed job is left wait 1 sec
                }
                continue;
            }
            echo "\nJob {$task->task_name} Started at ".date('H:i:s')."";
            $result=$this->itemExecute( $task );
            echo " Done!\n";
            $time_limit+=2;//adding 2 seconds if there is job
        }
        echo "\nWorker #$worker_id Finished! Goodbye!\n\n\n";
    }

    private function timedJobCheck($predis){
        $timer=$predis->get('cronjobtimer');
        if($timer){//time not came
            echo "timedJobCheck skipping: $timer";
            return false;
        }
        echo "\nTimed Jobs will execute:";
        $this->timedJobDo();
        $predis->setEx('cronjobtimer',$this->timedJobInterval,1);
        return true;
    }

    private function timedJobDo(){
        $this->taskPurge();
    }

    private function taskPurge(){
        echo "\npurging started...";
        $trashed_days= getenv('app.trashed_days');
        model("ImageModel")->listPurge($trashed_days);
        model("ProductModel")->listPurge($trashed_days);
        model("StoreModel")->listPurge($trashed_days);
        model("OrderModel")->listPurge($trashed_days);
        model("UserModel")->listPurge($trashed_days);
        echo "\npurged";
        log_message('error',"PURGED");
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
        $task_result=[];
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
            } else if( $command->library??0 ){
                $Class=new $command->library();
            } else {
                $Class=$this;
            }
            try{
                $task_result=$Class->{$command->method}(...$arguments);
            } catch (\Exception $e){
                log_message('error', 'TASK EXECUTION ERROR:'. json_encode($task)."\n".$e->getMessage() );
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
    
    // private function orderResetStage( $stage_from, $stage_to, $order_id=null ){
    //     $OrderModel=model('OrderModel');
    //     $UserModel=model('UserModel');
    //     $UserModel->systemUserLogin();
    //     if( $order_id ){
    //         $OrderModel->where('order_id',$order_id);
    //     }
    //     $OrderModel->join('order_group_list ogl',"order_group_id=group_id");
    //     $OrderModel->where('ogl.group_type',$stage_from);
    //     $orders=$OrderModel->get()->getResult();
    //     $result='';
    //     foreach($orders as $order){
    //         $result.=$OrderModel->itemStageCreate( $order->order_id, $stage_to );
    //     }
    //     $UserModel->systemUserLogout();
    //     return $result;
    // }
}
