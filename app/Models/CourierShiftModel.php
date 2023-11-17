<?php
namespace App\Models;
use CodeIgniter\Model;

class CourierShiftModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'courier_shift_list';
    protected $primaryKey = 'shift_id';
    protected $allowedFields = [
        'shift_status',
        'courier_id',
        'total_bonus',
        'total_duration',
        'closed_at',
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    
    
    public function itemGet( $shift_id ){
        return false;
    }

    public function itemCreate($courier_id, $courier_owner_id){
        /**
         * should i check if its opened shift already?
         */
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $shift=[
            'shift_status'=>'open',
            'courier_id'=>$courier_id,
            'owner_id'=>$courier_owner_id,
        ];
        $this->allowedFields[]="owner_id";
        return $this->insert($shift,true);
    }
    
    public function itemOpen( $courier_id, $courier_owner_id ){
        $shift_id=$this->itemCreate( $courier_id, $courier_owner_id );
        if( !$shift_id || $shift_id=='forbidden' ){
            return $shift_id;
        }

        $message=(object)[
            'message_reciever_id'=>$courier_owner_id,
            'message_transport'=>'telegram',
            'message_subject'=>'🚦 Смена открыта',
            'context'=>[],
            'template'=>'messages/events/on_delivery_shift_opened_sms'
        ];
        $sms_job=[
            'task_name'=>"Courier Shift opened msg send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'itemSend','arguments'=>[$message]]
                ],
        ];
        jobCreate($sms_job);
        return $shift_id;
    }

    public function itemOpenGet($courier_id){
        $this->where('courier_id',$courier_id);
        $this->where('shift_status','open');
        $this->permitWhere('r');
        return $this->get()->getRow();
    }

    public function itemWorkStatisticsGet($courier_id,$start_at,$finish_at){
        $OrderModel=model('OrderModel');
        $OrderModel->join('order_group_member_list ogml','member_id=order_id');
        $OrderModel->join('order_group_list ogl','group_id');
        $OrderModel->where('group_type','delivery_start');
        $OrderModel->where("ogml.created_at BETWEEN '$start_at' AND '$finish_at'");
        $OrderModel->where('order_courier_id',$courier_id);
        $OrderModel->where("order_data->>'$.order_is_canceled' IS NULL",null,false);
        $OrderModel->select("COUNT(*) order_count,COUNT(order_data->>'$.delivery_heavy_bonus'>0) heavy_count,SUM(COALESCE(order_data->>'$.delivery_heavy_bonus')) heavy_bonus");
        return $OrderModel->get()->getRow();
    }

    public function itemClose( $courier_id ){
        $this->select('*,NOW() finished_at');
        $openedShift=$this->itemOpenGet($courier_id);
        if( !$openedShift ){
            return 'notfound';
        }
        //$LocationModel=model('LocationModel');
        //$total_distance=$LocationModel->routeLengthGet( 'courier', $courier_id, $openedShift->created_at, $openedShift->finished_at );
        $total_duration=strtotime($openedShift->finished_at)-strtotime($openedShift->created_at);
        $statistics=$this->itemWorkStatisticsGet($courier_id,$openedShift->created_at,$openedShift->finished_at);

        $this->set('closed_at',$openedShift->finished_at);
        $this->set('total_duration',$total_duration??0);
        $this->set('total_bonus',$statistics->heavy_bonus??0);
        $this->set('shift_status','closed');
        $this->where('shift_id',$openedShift->shift_id);
        $this->permitWhere('w');
        $this->update();
        if( !$this->db->affectedRows() ){
            return 'idle';
        }

        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($courier_id);

        $message=(object)[
            'message_reciever_id'=>"-50,{$courier->owner_id}",//copy to courier group
            'message_transport'=>'telegram',
            'message_subject'=>'💤 Смена Закрыта',
            'context'=>[
                'total_duration'=>$total_duration,
                //'total_distance'=>$total_distance,
                'courier'=>$courier,
                'shift'=>$openedShift,
                'statistics'=>$statistics,
            ],
            'template'=>'messages/events/on_delivery_shift_closed_sms'
        ];
        $sms_job=[
            'task_name'=>"Courier Shift closed msg send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'itemSendMulticast','arguments'=>[$message]]
                ],
        ];
        jobCreate($sms_job);
        return 'ok';
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
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