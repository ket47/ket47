<?php
namespace App\Models;


trait OrderStageTrait{

    protected $ScriptLibraryName=null;//"App\\Models\\OrderStageScript";
    private $StageScript=null;
    private function itemStageScriptLoad($order_id){
        //if( !$this->StageScript ){
            $order_basic=$this->itemGet($order_id,'basic');
            if( !is_object($order_basic) ){
                pl('itemStageScriptLoad forbidden');
                die();//forbidden notfound etc
                //return null;
            }
            if( empty($order_basic->order_script) ){//for backward compatibility???
                if($order_basic->is_shipment??null){
                    $order_basic->order_script='shipment';
                } else {
                    $order_basic->order_script='deprecatedscript';
                }
            }
            switch( $order_basic->order_script ){
                case 'order_delivery':
                    $this->ScriptLibraryName="App\\Models\\OrderStageDeliveryScript";
                    break;
                case 'order_supplier':
                    $this->ScriptLibraryName="App\\Models\\OrderStageSupplierScript";
                    break;
                case 'shipment':
                    $this->ScriptLibraryName="App\\Models\\OrderStageShipmentScript";
                    break;
                default://deprecatedscript
                    $this->ScriptLibraryName="App\\Models\\OrderStageDefaultScript";
                    break;
            }
            //pl([$this->ScriptLibraryName,'itemStageScriptLoad',$order_basic]);
            $this->StageScript=new $this->ScriptLibraryName();
            $this->StageScript->OrderModel=$this;
        //}
        return $this->StageScript;
    }

    private function itemStageNextGet($order_id,$current_stage,$user_role){
        $this->itemStageScriptLoad($order_id);
        $filtered_stage_next=[];
        $unfiltered_stage_next=$this->StageScript->stageMap[$current_stage??'']??[];
        foreach($unfiltered_stage_next as $stage=>$config){
            $valid=$user_role=='admin' 
            || strpos($stage, $user_role)===0 
            || strpos($stage, 'action')===0 ;
            //|| strpos($stage, 'system')===0
            












            /**
             * system should be accessible only for admins!!! || strpos($stage, 'system')===0;
             */
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
        //pl([$this->ScriptLibraryName,"current: $order->stage_current","tried: $stage","user_role $order->user_role",'allowed next stages',$next_stages]);//,'order',$order
        return 'invalid_next_stage';
    }

    /**
     * Not accessible from frontend, only from order scripts
     */
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

    public function itemStageCreate( $order_id, $stage, $data=null, $permission='as_user' ){
        $order=$this->itemGet( $order_id, 'basic' );
        if( !is_object($order) ){
            return $order;
        }
        if($order->stage_current==$stage){
            return 'ok';
        }
        $offHandled=$this->itemStageOffHandle( $order_id,  $stage, $order->stage_current, $data );
        if( 'ok'!=$offHandled ){
            return $offHandled;
        }

        /**
         * Check permission false cant be set at controller
         * so only internal calls can be unchecked
         */
        if( $permission=='as_user' ){
            $this->permitWhere('w');
        } else 
        if( $permission=='as_admin' ){
            $order->user_role='admin';//will it work???
        }
        
        $OrderGroupModel=model('OrderGroupModel');
        $next_stage_group_id=$OrderGroupModel->select('group_id')->itemGet(null,$stage)?->group_id;
        $result=$this->itemStageValidate($stage,$order,$next_stage_group_id);
        if( $result!=='ok' ){
            return $result;
        }
        
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $this->allowedFields[]='order_group_id';

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

    private function itemStageOffHandle( $order_id, $stage_next, $stage_current, $data ){
        if( !$stage_current ){
            return 'ok';
        }
        $this->itemStageScriptLoad($order_id);
        helper('job');
        $stageHandlerName = 'off'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage_current,)));
        if( !method_exists($this->StageScript,$stageHandlerName) ){
            return 'ok';
        }
        try{
            return $this->StageScript->{$stageHandlerName}($order_id, $stage_next, $data);
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
            ],
            'task_priority'=>'low'
        ];
        jobCreate($notification_task);
    }
}