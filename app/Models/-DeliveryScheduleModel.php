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


    private $shiftStartHour=9;
    private $shiftEndHour=23;
    private $shiftEndMarginMinute=30;//30 min before shiftEnd skip to next day
    private $deliveryRangeDays=2;
    private $deliveryDurationDelta=15;//+-15 min
    private $avgOrderDuration=30;
    private $avgSpeed;
    private $avgDistance;

    function __construct(){
        parent::__construct();

        $this->avgSpeed=(int) getenv('delivery.speed');// m/h
        $this->avgDistance=(int) getenv('delivery.radius');// m
    }

    /**
     * Function estimates when courier may become ready
     * when all couriers are busy
     */
    private function courierBusynessEndEstimate():int{
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderGroupMemberModel->join('order_group_list','group_id');
        $OrderGroupMemberModel->where("TIMESTAMPDIFF(HOUR,order_group_member_list.created_at,NOW())<5");
        $OrderGroupMemberModel->where('group_type','delivery_search');
        $orderInQueueCount=$OrderGroupMemberModel->select("COUNT(*) orderInQueueCount")->get()->getRow('orderInQueueCount');

        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $CourierGroupMemberModel->join('courier_group_list','group_id');
        $CourierGroupMemberModel->where('group_type','busy');
        $courierBusyCount=$CourierGroupMemberModel->select("COUNT(*) courierBusyCount")->get()->getRow('courierBusyCount');

        $orderTotalDuration=$this->avgOrderDuration*($orderInQueueCount+0.5);//half time for order in progress
        $courierBusyDuration=$orderTotalDuration*60/$courierBusyCount;//sec

        return time()+$courierBusyDuration;
    }

    /**
     * Function estimates when courier may become ready
     * estimates courier busyness time, shift times, margins etc
     */
    public function courierReadinessBeginEstimate():array{
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
                    'status'=>'downtime',
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
            'status'=>'heavyload',
            'time'=>$time,
        ];
    }

    public function routeStatsGet(int $start_location_id, int $finish_location_id){
        $LocationModel=model('LocationModel');
        $default_location_id=$LocationModel->where('location_holder','default_location')->get()->getRow('location_id');

        $maximum_distance=getenv('delivery.radius');
        $result=(object)[
            'max_distance'=>$maximum_distance
        ];
        $start_center_distance=(int) $LocationModel->distanceGet($default_location_id,$start_location_id);
        if($start_center_distance>$maximum_distance){
            $result->error='start_center_toofar';
            $result->deliveryDistance=$start_center_distance;
            return $result;
        }
        $finish_center_distance=(int) $LocationModel->distanceGet($default_location_id,$finish_location_id);
        if($finish_center_distance>$maximum_distance){
            $result->error='finish_center_toofar';
            $result->deliveryDistance=$finish_center_distance;
            return $result;
        }
        $start_finish_distance=(int) $LocationModel->distanceGet($start_location_id,$finish_location_id);
        if($start_finish_distance>$maximum_distance){
            $result->error='start_finish_toofar';
            $result->deliveryDistance=$start_finish_distance;
            return $result;
        }
        $result->deliveryDistance=$start_finish_distance;//km
        return $result;
    }

    public function routePlanGet(int $start_location_id, int $finish_location_id){
        $result=$this->routeStatsGet($start_location_id, $finish_location_id);
        if( isset($result->error) ){
            return $result;
        }
        $courierReadinessBegin=$this->courierReadinessBeginEstimate();
        pl($courierReadinessBegin);
        $estStartArrivalDurationSeconds=round($this->avgDistance*60*60/$this->avgSpeed);//seconds

        $result->time_offset=(int) date("Z");
        $result->time_start_arrival=$estStartArrivalDurationSeconds;
        $result->time_delivery=round($result->deliveryDistance*60*60/$this->avgSpeed);//seconds

        $result->plan_mode="start";
        $result->plan_delivery_ready=$courierReadinessBegin['time'];
        $result->plan_delivery_start=$result->plan_delivery_ready+$result->time_start_arrival;
        $result->plan_delivery_finish=$result->plan_delivery_start+$result->time_delivery;

        if( $courierReadinessBegin['status']=='heavyload' ){
            $result->plan_mode="await";
        }
        if( $courierReadinessBegin['status']=='downtime' ){
            $result->plan_mode="schedule";
        }
        return $result;
    }

    /**
     * Function calculates courier arrival ranges to start or finish location
     * 
     */
    public function itemDeliveryArrivalRangeGet( int $timeArrival ){
        $timeArrivalRounded=round($timeArrival/($this->deliveryDurationDelta*60))*($this->deliveryDurationDelta*60);
        $dateArrival=date('Y-m-d,H,i',$timeArrivalRounded);
        list($arrivalDay,$arrivalHour,$arrivalMinute)=explode(',',$dateArrival);

        $defaultMinuteRange=['00','15','30','45'];
        $nearest=null;
        $range=[
            'dayFirst'=>date("Y-m-d",$timeArrivalRounded),
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
                $range['dayHours'][$date]["h_{$hourPadded}"]=[];
                if( $day==0 && $hour==$arrivalHour ){
                    foreach($defaultMinuteRange as $minute){
                        if( $minute<$arrivalMinute ){
                            continue;
                        }
                        //$minutePadded=str_pad($minute,2,'0',STR_PAD_LEFT);
                        $range['dayHours'][$date]["h_{$hourPadded}"][]=$minute;
                        if( !$nearest ){
                            $nearest="{$date} {$hourPadded}:{$minute}:00";
                        }
                    }
                    continue;
                }
                $range['dayHours'][$date]["h_{$hourPadded}"]=$defaultMinuteRange;
                if( !$nearest ){
                    $nearest="{$date} {$hourPadded}:{$defaultMinuteRange[0]}:00";
                }
            }
        }
        return [
            'nearest'=>$nearest,
            'range'=>$range,
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