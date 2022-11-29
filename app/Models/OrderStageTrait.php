<?php
namespace App\Models;


trait OrderStageTrait{

    private $StageScript=null;
    private function itemStageScriptLoad(){
        if( !$this->StageScript ){
            $ScriptLibraryName="App\\Models\\OrderStageScript";
            $this->StageScript=new $ScriptLibraryName();
            $this->StageScript->OrderModel=$this;
        }
        return $this->StageScript;
    }

    private function itemStageNextGet($current_stage,$user_role){
        $this->itemStageScriptLoad();
        $filtered_stage_next=[];
        $unfiltered_stage_next=$this->StageScript->stageMap[$current_stage??'']??[];
        foreach($unfiltered_stage_next as $stage=>$config){
            $valid=$user_role=='admin' 
            || strpos($stage, $user_role)===0 
            || strpos($stage, 'action')===0 
            || strpos($stage, 'system')===0;
            if($valid){
                $filtered_stage_next[$stage]=$config;
            }
        }
        return $filtered_stage_next;
    }

    private function itemStageValidate($stage,$order,$next_stage_group_id){
        $next_stages=$this->itemStageNextGet($order->stage_current,$order->user_role);
        if( isset($next_stages[$stage]) && $next_stage_group_id && strpos($stage, 'action')===false ){
            return 'ok';
        }
        return 'invalid_next_stage';
    }

    public function itemStageAdd( $order_id, $stage, $data=null, $check_permission=true ){
        //only adds member group to order and executes handler
        if( $check_permission ){
            $this->permitWhere('w');
        }
        $OrderGroupModel=model('OrderGroupModel');
        $OrderGroupMemberModel=model('OrderGroupMemberModel');

        $group=$OrderGroupModel->select('group_id')->itemGet(null,$stage);
        $OrderGroupMemberModel->joinGroup($order_id,$group->group_id);
        return $this->itemStageHandle( $order_id, $stage, $data );
    }

    public function itemStageCreate( $order_id, $stage, $data=null, $check_permission=true ){
        $order=$this->itemGet( $order_id, 'basic' );
        if( !is_object($order) ){
            return $order;
        }
        if($order->stage_current==$stage){
            return 'ok';
        }
        $OrderGroupModel=model('OrderGroupModel');
        $next_stage_group_id=$OrderGroupModel->select('group_id')->itemGet(null,$stage)?->group_id;
        $result=$this->itemStageValidate($stage,$order,$next_stage_group_id);
        if( $result!=='ok' ){
            return $result;
        }
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $this->allowedFields[]='order_group_id';
        if( $check_permission ){
            $this->permitWhere('w');
        }

        $this->transBegin();
            $updated=$this->update($order_id,['order_group_id'=>$next_stage_group_id]);
            $joined=$OrderGroupMemberModel->joinGroup($order_id,$next_stage_group_id);
            $this->itemCacheClear();//because order properties have changed
            $handled=$this->itemStageHandle( $order_id, $stage, $data );
            if( !$updated || !$joined || $handled!=='ok' ){//failed
                $this->transRollback();
                return $handled;
            }
        $this->transCommit();
        $this->itemStageChangeNotify($order, $stage);
        return 'ok';
    }
    
    private function itemStageHandle( $order_id, $stage, $data ){
        $this->itemStageScriptLoad();
        helper('job');
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        return $this->StageScript->{$stageHandlerName}($order_id, $data);
    }
    
    private function itemStageChangeNotify($order, $stage){
        $recievers_id=$order->owner_id.','.$order->owner_ally_ids;
        $push=(object)[
            'message_transport'=>'push',
            'message_reciever_id'=>$recievers_id,
            'message_data'=>[
                'topic'=>'pushStageChanged',
                'order_id'=>$order->order_id,
                'orderActiveCount'=>$this->listCountGet(),
                'stage'=>$stage,
                'title'=>view('messages/order/stage_changed_title',(array)$order),
                'body' =>view('messages/order/stage_changed_body',(array)$order),
            ]
        ];
        $notification_task=[
            'task_name'=>"onStageChanged background push Notify #$order->order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$push]]]
                ]
        ];
        jobCreate($notification_task);
    }
}