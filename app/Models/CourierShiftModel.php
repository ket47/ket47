<?php
namespace App\Models;
class CourierShiftModel extends SecureModel{

    use FilterTrait;
    
    protected $table      = 'courier_shift_list';
    protected $primaryKey = 'shift_id';
    protected $allowedFields = [
        'shift_status',
        'courier_id',
        'courier_speed',
        'courier_reach',
        'actual_longitude',
        'actual_latitude',
        'actual_color',
        'last_finish_plan',
        'last_longitude',
        'last_latitude',
        'closed_at',
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $returnType     = 'object';
    
    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }
    
    public function itemGet( $shift_id ){
        return $this->find($shift_id);
    }

    public function itemCreate($courier_id, $courier_owner_id){
        $shift=[
            'shift_status'=>'open',
            'courier_id'=>$courier_id,
            'owner_id'=>$courier_owner_id,
            'courier_reach'=>15000,// m maximum distance,
            'courier_speed'=>2.78,// m/s
            'last_finish_plan'=>time(),
        ];
        $this->fieldUpdateAllow("owner_id");
        return $this->insert($shift,true);
    }
    
    public function itemOpen( $courier_id, $courier_owner_id ){
        $this->itemClose( $courier_id );
        $shift_id=$this->itemCreate( $courier_id, $courier_owner_id );
        if( !$shift_id || $shift_id=='forbidden' ){
            return $shift_id;
        }

        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($courier_id);

        $message=(object)[
            'message_reciever_id'=>"-100,$courier_owner_id",
            'message_transport'=>'telegram',
            'message_subject'=>'shift open',
            'context'=>[
                'courier'=>$courier
            ],
            'template'=>'messages/events/on_delivery_shift_opened_sms',
            'telegram_options'=>[
                'opts'=>[
                    'disable_notification'=>1
                ]
            ],
        ];
        $sms_job=[
            'task_name'=>"Courier Shift opened msg send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'itemSendMulticast','arguments'=>[$message]]
                ],
        ];
        jobCreate($sms_job);

        $DeliveryJobModel=model('DeliveryJobModel');
        $DeliveryJobModel->chainJobs();

        return $shift_id;
    }

    public function itemOpenGet($courier_id){
        $this->where('courier_id',$courier_id);
        $this->where('shift_status','open');
        $shiftsAll=$this->find();
        return $shiftsAll[0]??null;
    }

    public function itemWorkStatisticsGet($courier_id,$start_at,$finish_at){
        $OrderModel=model('OrderModel');
        $OrderModel->join('order_group_member_list ogml','member_id=order_id');
        $OrderModel->join('order_group_list ogl','group_id');
        $OrderModel->where('group_type','delivery_start');
        $OrderModel->where("ogml.created_at BETWEEN '$start_at' AND '$finish_at'");
        $OrderModel->where('order_courier_id',$courier_id);
        $OrderModel->where("( order_data->>'$.order_is_canceled' IS NULL OR order_data->>'$.order_is_canceled' <> 1 )",null,false);//sometimes order_is_canceled==0
        $OrderModel->select("COUNT(*) order_count,SUM(COALESCE(order_data->>'$.delivery_heavy_bonus',0)>0) heavy_count,SUM(COALESCE(order_data->>'$.delivery_heavy_bonus')) heavy_bonus");
        return $OrderModel->get()->getRow();
    }

    public function itemClose( $courier_id ){
        $this->select('*,NOW() finished_at');
        $openedShift=$this->itemOpenGet($courier_id);
        if( !$openedShift ){
            return 'notfound';
        }
        
        $this->set('closed_at','NOW()',false);
        $this->set('shift_status','closed');
        $this->where('shift_id',$openedShift->shift_id);
        $this->update();
        if( !$this->db->affectedRows() ){
            return 'idle';
        }
        $this->itemReportSend($openedShift->shift_id);

        $DeliveryJobModel=model('DeliveryJobModel');
        $DeliveryJobModel->chainJobs();
        
        return 'ok';
    }

    public function itemReportSend( $shift_id ){
        $shift=$this->itemGet($shift_id);
        if( !$shift ){
            return 'notfound';
        }
        $total_duration=strtotime($shift->closed_at)-strtotime($shift->created_at);
        $statistics=$this->itemWorkStatisticsGet($shift->courier_id,$shift->created_at,$shift->closed_at);
        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($shift->courier_id);

        $message=(object)[
            'message_reciever_id'=>"-100,{$courier->owner_id}",//copy to courier group
            'message_transport'=>'telegram',
            'message_subject'=>'shift close',
            'context'=>[
                'total_duration'=>$total_duration,
                'courier'=>$courier,
                'shift'=>$shift,
                'statistics'=>$statistics,
            ],
            'template'=>'messages/events/on_delivery_shift_closed_sms',
            'telegram_options'=>[
                'opts'=>[
                    'disable_notification'=>1
                ]
            ],
        ];
        $sms_job=[
            'task_name'=>"Courier Shift closed msg send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'itemSendMulticast','arguments'=>[$message]]
                ],
        ];
        jobCreate($sms_job);        
    }
    
    public function itemUpdate( object $update ){
        if( $update->actual_longitude??null && $update->actual_latitude??null ){
            $colorMap=new \App\Libraries\Coords2Color();
            $update->actual_color=$colorMap->getColor('claster1',$update->actual_latitude,$update->actual_longitude);
        }
        if($update->shift_id??null){
            $this->update($update->shift_id,$update);
        } else 
        if($update->courier_id??null){
            $this->where('courier_id',$update->courier_id)->where('shift_status','open');
            $this->update(null,$update);
        }
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet( object $filter ){
        if( $filter->shift_status??null ){
            $this->where('shift_status',$filter->shift_status);
        }
        $this->select("
            shift_id,
            courier_id,
            actual_color,
            actual_longitude,
            actual_latitude,
            last_longitude,
            last_latitude,
            last_finish_plan,
            courier_shift_list.owner_id
        ");
        return $this->findAll();
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}