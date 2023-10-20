<?php
namespace App\Models;
use CodeIgniter\Model;

class DeliveryScheduleModel extends SecureModel{

    use FilterTrait;
    
    protected $table      = 'delivery_list';
    protected $primaryKey = 'delivery_id';
    protected $allowedFields = [

        ];

    protected $useSoftDeletes = false;

    private $orderAverageDuration=40;//minute
    private $shiftStartHour=9;
    private $shiftEndHour=23;
    private $shiftEndMarginMinute=30;//30 min before shiftEnd skip to next day
    private $deliveryRangeDays=2;
    private $deliveryDurationDelta=15;//+-15 min

    /**
     * Function estimates when courier may become ready
     * when all couriers are busy
     */
    private function courierBusynessEndEstimate(){
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderGroupMemberModel->join('order_group_list','group_id');
        $OrderGroupMemberModel->where("TIMESTAMPDIFF(HOUR,order_group_member_list.created_at,NOW())<5");
        $OrderGroupMemberModel->where('group_type','delivery_search');
        $orderInQueueCount=$OrderGroupMemberModel->select("COUNT(*) orderInQueueCount")->get()->getRow('orderInQueueCount');

        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $CourierGroupMemberModel->join('courier_group_list','group_id');
        $CourierGroupMemberModel->where('group_type','busy');
        $courierBusyCount=$CourierGroupMemberModel->select("COUNT(*) courierBusyCount")->get()->getRow('courierBusyCount');

        $orderTotalDuration=$this->orderAverageDuration*($orderInQueueCount+0.5);//half time for order in progress
        $courierBusyDuration=$orderTotalDuration/$courierBusyCount;//min

        return strtotime("now +{$courierBusyDuration} minute");
    }

    /**
     * Function estimates when courier may become ready
     * estimates courier busyness time, shift times, margins etc
     */
    public function courierReadinessTimeEstimate(){
        $nowHour=(int) date('H');
        $nowMinute=(int) date('i');
        if( $nowHour<$this->shiftStartHour ){
            $time=strtotime(date("Y-m-d {$this->shiftStartHour}:00:00"));
            return [
                'status'=>'downtime',
                'time'=>$time,
            ];
        }
        if( $nowHour>=$this->shiftEndHour || ( $nowHour==$this->shiftEndHour-1 && $nowMinute>$this->shiftEndMarginMinute ) ){
            $time=strtotime(date("Y-m-d {$this->shiftStartHour}:00:00",strtotime("now +1 day")));
            return [
                'status'=>'downtime',
                'time'=>$time,
            ];
        }
        $CourierModel=model('CourierModel');
        $active_courier_status=$CourierModel->hasActiveCourier();
        if( $active_courier_status=='ready' ){
            $time=time();
            return [
                'status'=>'ready',
                'time'=>$time,
            ];
        }
        if( $active_courier_status=='busy' ){
            $ReadinessTime=$this->courierBusynessEndEstimate();
            $ReadinessHour=(int) date('H',$ReadinessTime);
            $ReadinessMinute=(int) date('i',$ReadinessTime);
            if( $ReadinessHour>$this->shiftEndHour || ( $ReadinessHour==$this->shiftEndHour && $ReadinessMinute>$this->shiftEndMarginMinute ) ){
                /**
                 * Estimated readiness time falls into the downtime, so schedule to next day
                 */
                $ReadinessTime=strtotime(date("Y-m-d {$this->shiftStartHour}:00:00",strtotime("now +1 day")));
                return [
                    'status'=>'heavyload,downtime',
                    'time'=>$ReadinessTime,
                ];
            }
            return [
                'status'=>'heavyload',
                'time'=>$ReadinessTime,
            ];
        }
        $CourierModel->deliveryNotReadyNotify();
        /**
         * start shipping anyway even if courier is not found
         */
        $time=time();
        return [
            'status'=>'ready',
            'time'=>$time,
        ];
    }

    /**
     * Function calculates courier arrival ranges to start or finish location
     * 
     */
    public function itemDeliveryArriveRangeGet(){
        //$orderTotalDuration=$this->orderTotalDurationGet();
        $courierReadiness=$this->courierReadinessTimeEstimate();
        $courierArrivalTime=$courierReadiness['time']+$this->orderAverageDuration*60/2;
        $courierArrivalTimeRounded=round($courierArrivalTime/($this->deliveryDurationDelta*60))*($this->deliveryDurationDelta*60);

        $arrivalDay     =date('Y-m-d',$courierArrivalTimeRounded);
        $arrivalHour    =date('H',$courierArrivalTimeRounded);
        $arrivalMinute  =date('i',$courierArrivalTimeRounded);

        $defaultMinuteRange=['00','15','30','45'];
        $deliveryArrivalNearest=null;
        $deliveryArrivalRange=[
            'dayFirst'=>date("Y-m-d",$courierArrivalTimeRounded),
            'dayLast'=>date("Y-m-d",strtotime("now +{$this->deliveryRangeDays} day")),
            'dayHours'=>[]
        ];

        for( $day=0; $day<=$this->deliveryRangeDays; $day++ ){
            $date=date("Y-m-d",strtotime("now +$day day"));
            if($date<$arrivalDay){
                continue;
            }
            for($hour=0; $hour<=24; $hour++){
                if( $hour<$this->shiftStartHour || $hour>=$this->shiftEndHour ){
                    continue;
                }
                if( $day==0 && $hour<$arrivalHour ){//calculating offset for first day in range
                    continue;
                }
                $hourPadded=str_pad($hour,2,'0',STR_PAD_LEFT);
                $deliveryArrivalRange['dayHours'][$date]["h_{$hourPadded}"]=[];
                if( $day==0 && $hour==$arrivalHour ){
                    foreach($defaultMinuteRange as $minute){
                        if( $minute<$arrivalMinute ){
                            continue;
                        }
                        //$minutePadded=str_pad($minute,2,'0',STR_PAD_LEFT);
                        $deliveryArrivalRange['dayHours'][$date]["h_{$hourPadded}"][]=$minute;
                        if( !$deliveryArrivalNearest ){
                            $deliveryArrivalNearest="{$date} {$hourPadded}:{$minute}:00";
                        }
                    }
                    continue;
                }
                $deliveryArrivalRange['dayHours'][$date]["h_{$hourPadded}"]=$defaultMinuteRange;
                if( !$deliveryArrivalNearest ){
                    $deliveryArrivalNearest="{$date} {$hourPadded}:{$defaultMinuteRange[0]}:00";
                }
            }
        }
        return [
            'deliveryDuration'=>'',
            'deliveryStatus'=>$courierReadiness['status'],
            'deliveryArrivalNearest'=>$deliveryArrivalNearest,
            'deliveryArrivalRange'=>$deliveryArrivalRange,
        ];
    }


    public function shiftStartHourGet(){
        return $this->shiftStartHour;
    }

    public function shiftEndHourGet(){
        return $this->shiftEndHour;
    }
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        return false;
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