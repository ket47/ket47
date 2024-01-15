<?php
namespace App\Models;


trait OrderStageTrait{

    protected $ScriptLibraryName="App\\Models\\OrderStageScript";
    private $StageScript=null;
    private function itemStageScriptLoad($order_id){
        if( !$this->StageScript ){
            $order_basic=$this->itemGet($order_id,'basic');
            if($order_basic->is_shipment??null){
                $this->ScriptLibraryName="App\\Models\\ShipmentStageScript";
            }
            $this->StageScript=new $this->ScriptLibraryName();
            $this->StageScript->OrderModel=$this;
        }
        return $this->StageScript;
    }

    private function itemStageNextGet($order_id,$current_stage,$user_role){
        $this->itemStageScriptLoad($order_id);
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
        $next_stages=$this->itemStageNextGet($order->order_id,$order->stage_current,$order->user_role);
        if( isset($next_stages[$stage]) && $next_stage_group_id && strpos($stage, 'action')===false ){
            return 'ok';
        }
        //pl([$this->ScriptLibraryName,"current: $order->stage_current","tried:$stage",'allowed next stages:',$next_stages]);
        return 'invalid_next_stage';
    }

    public function itemStageAdd( $order_id, $stage, $data=null, $check_permission=true ){
        //only adds member group to order and executes handler
        if( $check_permission ){
            $this->permitWhere('w');
        }
        $OrderGroupModel=model('OrderGroupModel');
        $OrderGroupMemberModel=model('OrderGroupMemberModel');

        $this->transBegin();
        $handled=$this->itemStageHandle( $order_id, $stage, $data );
        if($handled!=='ok'){
            $this->transRollback();
            return $handled;
        }
        $group=$OrderGroupModel->select('group_id')->itemGet(null,$stage);
        $OrderGroupMemberModel->joinGroup($order_id,$group->group_id);
        $this->transCommit();
        return $handled;
    }

    private $itemStageUnconfirmedGroupId=null;
    private $itemStageUnconfirmedOrderId=null;
    public function itemStageCreate( $order_id, $stage, $data=null, $check_permission=true ){
        $this->itemStageConfirm();
        $order=$this->itemGet( $order_id, 'basic' );
        if( !is_object($order) ){
            return $order;
        }
        if($order->stage_current==$stage){
            return 'ok';
        }
        $OrderGroupModel=model('OrderGroupModel');
        $this->itemStageUnconfirmedGroupId=$OrderGroupModel->select('group_id')->itemGet(null,$stage)?->group_id;
        $result=$this->itemStageValidate($stage,$order,$this->itemStageUnconfirmedGroupId);
        if( $result!=='ok' ){
            return $result;
        }
        if( $check_permission ){
            $this->permitWhere('w');
        }

        $this->itemStageUnconfirmedOrderId=$order_id;
        $this->transBegin();
            $handled=$this->itemStageHandle( $order_id, $stage, $data );
            if($handled==='ok'){
                $this->itemStageConfirm();
            } else {//failed
                $this->transRollback();
                return $handled;
            }
        $this->transCommit();
        $this->itemStageChangeNotify($order, $stage);
        $this->itemStageUnconfirmedOrderId=null;
        return 'ok';
    }

    public function itemStageConfirm(){
        if(!$this->itemStageUnconfirmedOrderId || !$this->itemStageUnconfirmedGroupId){
            return;
        }
        $this->allowedFields[]='order_group_id';
        $this->update($this->itemStageUnconfirmedOrderId,['order_group_id'=>$this->itemStageUnconfirmedGroupId]);
        $this->itemCacheClear();//because order properties have changed
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderGroupMemberModel->joinGroup($this->itemStageUnconfirmedOrderId,$this->itemStageUnconfirmedGroupId);
    }
    
    private function itemStageHandle( $order_id, $stage, $data ){
        $this->itemStageScriptLoad($order_id);
        helper('job');
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        try{
            return $this->StageScript->{$stageHandlerName}($order_id, $data);
        } catch (\Throwable $e){
            log_message('error',"itemStageCreate ".$e->getMessage()."\n".json_encode($e->getTrace(),JSON_PRETTY_PRINT));
        }
        return 'error';
    }
    
    private function itemStageChangeNotify($order, $stage){
        if( in_array($stage,['customer_deleted','customer_cart','customer_confirmed','customer_start','customer_finish','system_reckon','system_finish']) ){
            return;//not notifying for this stages
        }
        $order=$this->itemGet($order->order_id,'basic');
        $recievers_id=$order->owner_id.','.$order->owner_ally_ids;
        $push=(object)[
            'message_transport'=>'push',
            'message_reciever_id'=>$recievers_id,
            'message_data'=>[
                'topic'=>'pushStageChanged',
                'order_id'=>$order->order_id,
                'orderActiveCount'=>$this->listCountGet(),
                'stage'=>$order->stage_current,
                'title'=>view('messages/order/stage_changed_title',(array)$order),
                'body' =>view('messages/order/stage_changed_body',(array)$order),
                'tag'  =>'orderStage'
            ]
        ];
        $notification_task=[
            'task_name'=>"onStageChanged to $stage. background push Notify #$order->order_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$push]]]
                ]
        ];
        jobCreate($notification_task);
    }
}