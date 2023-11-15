<?php
namespace App\Controllers;
require_once '../app/ThirdParty/Credis/Client.php';

class Task extends \App\Controllers\BaseController{
    private $workerLifeTime=2*60;//2min
    private $workerLifeSpread=60;//1min
    private $timedJobInterval=1*60*30;//30 min

    private function jobTimeoutsSet(){
        $db = \Config\Database::connect();
        $db->query("set session wait_timeout=3600");
        set_time_limit(3600);
        session_write_close();
    }

    private function jobDelayedMove( $predis ){
        $time=time();
        $job2execute=$predis->zRangeByScore('queue.delayed', 0, $time);
        $predis->zRemRangeByScore('queue.delayed',0,$time);
        //pl($time);
        //pl($job2execute);
        foreach($job2execute as $job){
            $predis->lPush('queue.priority.normal', $job);
        }
    }

    public function jobDo(){
        if( getenv('app.logworkers') ){
            ob_start();
        }

        $this->jobTimeoutsSet();
        $time_limit = $this->workerLifeTime;
        $time_limit += rand(0, $this->workerLifeSpread);
        $start_time = time();
        $worker_id = rand(100, 999);
        
        echo "\nWorker #$worker_id Starting.".date('H:i:s');
        echo "\nWaiting for a Job";
        $predis = new \Credis_Client();
        $this->timedJobDo($predis);

        while(time() < $start_time + $time_limit){
            $this->jobDelayedMove( $predis );
            $job_chunk = $predis->blPop('queue.priority.normal',4);
            $job=$job_chunk[1]??null;
            if(!$job){
                $job = $predis->lPop('queue.priority.low');
            }
            if(!$job){
                echo ".";
                continue;
            }
            $task= json_decode($job);
            if( !$task ){
                echo "\nInvalid job syntax: ".json_last_error_msg();
            }
            echo "\nJob {($task->task_name??'-')} Started at ".date('H:i:s')." result=";
            print_r( $this->itemExecute( $task ) );
            echo "\nDone!";
            $time_limit+=2;//adding 2 seconds if there is a job
        }
        echo "\nWorker #$worker_id Finished! Goodbye!\n\n\n";
        if( getenv('app.logworkers') ){
            log_message('error',ob_get_flush());
        }
    }

    // private function timedJobCheck($predis){
    //     $timer=$predis->get('cronjobtimer');
    //     if($timer){//time not came
    //         echo "\ntimedJobCheck skipping: $timer";
    //         return false;
    //     }
    //     echo "\nTimed Jobs will execute:";
    //     $this->timedJobDo();
    //     $predis->setEx('cronjobtimer',$this->timedJobInterval,1);
    //     return true;
    // }

    private function timedJobDo($predis){
        $this->taskPurge($predis);
        $this->taskShiftClose($predis);
    }

    private function taskPurge($predis){
        $timerNotExpired=$predis->get('purgetimer');
        if( $timerNotExpired ){
            return false;
        }
        $predis->setEx('purgetimer',30*60,1);//30 min

        $trashed_days= getenv('app.trashed_days');
        model("ImageModel")->listPurge($trashed_days);
        model("ProductModel")->listPurge($trashed_days);
        model("StoreModel")->listPurge($trashed_days);
        model("OrderModel")->listPurge(-1);
        model("UserModel")->listPurge($trashed_days);
        model("CourierModel")->listPurge();
    }

    private function taskShiftClose($predis){
        $timerNotExpired=$predis->get('couriershifttimer');
        if( $timerNotExpired ){
            return false;
        }
        $predis->setEx('couriershifttimer',10*60,1);//10 min
        
        $CourierModel=model('CourierModel');
        $UserModel=model('UserModel');
        $UserModel->systemUserLogin();
            $CourierModel->listIdleShiftClose();
        $UserModel->systemUserLogout();
    }







    public function run(){
        $TaskModel=model('TaskModel');
        $filter=['is_pending'=>1];
        $pending_tasks=$TaskModel->listGet($filter);
        foreach($pending_tasks as $task){
            $result=$this->itemExecute($task);
            echo "\n".date('H:i:s')." $task->task_name: $result\n";
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
                log_message('error', 'TASK EXECUTION ERROR:'. json_encode($task->task_name)."\n".$e->getMessage()."\n".$e->getTraceAsString() );
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

    private function orderStageCreate($order_id,$new_stage,$data){
        $OrderModel=model('OrderModel');
        $UserModel=model('UserModel');
        $UserModel->systemUserLogin();
            $result=$OrderModel->itemStageCreate( $order_id, $new_stage, $data );
        $UserModel->systemUserLogout();
    }

    private function orderResetStage( $stage_from, $stage_to, $order_id=null ){
        //reset stage of multiple orders Probably it will be better to avoid this behavior!!!
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
