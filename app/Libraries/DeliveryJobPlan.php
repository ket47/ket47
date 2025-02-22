<?php
namespace App\Libraries;
class DeliveryJobPlan{

    private $shiftStartHour=9;
    private $shiftEndHour=23;
    private $shiftEndMarginMinute=5;// min before shiftEnd skip to next day

    private $deliveryRangeDays=3;
    private $deliveryDurationDelta=900;//+-15 min
    private $avgSpeed=3.05;//11 km/h
    private $heavyLoadTreshold=2400;//40min if shortest start_plan is later than this then report heavyload


    public $maxDistance;//should adjust for shipment at runtime
    public $maxReach;
    public $schedule;

    public $minFinishArrival=1200;//20 min
    public $avgStartArrival=1200;//20 min
    public $minStartPreparation=900;//15 min

    public function __construct(){
        $this->schedule=new \App\Libraries\Schedule();
        //$this->scheduleFillShift();

        $this->maxDistance=getenv('delivery.radius');
        $this->maxReach=$this->maxDistance*1.5;
    }

    public function routePlanGet( int $start_location_id, int $finish_location_id ):object{
        $routeData=$this->routeValidate( $start_location_id, $finish_location_id );
        if( $routeData['error'] ){
            return (object)[
                'error'=>$routeData['error'],
            ];
        }

        // $LocationModel=model('LocationModel');
        // $start_finish_distance=(int) $LocationModel->distanceGet($start_location_id,$finish_location_id);

        $LocationModel=model('LocationModel');
        $start_point=$LocationModel->itemGet($start_location_id,'basic');
        $startPlan=$this->startPlanEstimate($start_point->location_longitude,$start_point->location_latitude,$routeData['deliveryDistance']);

        $finish_arrival=max($this->minFinishArrival,$startPlan['finish_arrival']);
        $init_finish_offset=$finish_arrival+$this->minStartPreparation;

        return (object)[
            'start_plan'=>$startPlan['start_plan'],
            'start_plan_mode'=>$startPlan['mode'],
            'finish_arrival'=>$finish_arrival,
            'init_finish_offset'=>$init_finish_offset,

            'error'=>null,
            'deliveryDistance'=>$routeData['deliveryDistance'],
        ];
    }

    private function routeValidate( int $start_location_id, int $finish_location_id ){
        $LocationModel=model('LocationModel');
        $start_finish_distance=(int) $LocationModel->distanceGet($start_location_id,$finish_location_id);
        if($start_finish_distance>$this->maxDistance){
            return [
                'error'=>'start_finish_toofar',
            ];
        }
        if($start_finish_distance==0){
            return [
                'error'=>'start_finish_same',
            ];
        }

        $default_location_id=$LocationModel->where('location_holder','default_location')->get()->getRow('location_id');
        $start_center_distance=(int) $LocationModel->distanceGet($default_location_id,$start_location_id);
        if($start_center_distance>$this->maxReach){
            return [
                'error'=>'start_center_toofar',
            ];
        }
        $finish_center_distance=(int) $LocationModel->distanceGet($default_location_id,$finish_location_id);
        if($finish_center_distance>$this->maxReach){
            return [
                'error'=>'finish_center_toofar',
            ];
        }
        return [
            'error'=>null,
            'deliveryDistance'=>$start_finish_distance
        ];
    }

    public function peakHourOffset( int $time ){
        $h=date('H',$time);
        $offset=40*60;//40 min
        if( $h>=12 && $h<=15 || $h>=18 && $h<=20 ){
            return $offset;
        }
        return 0;
    }

    public function startPreparationSet( int $time ){
        $this->minStartPreparation=max($time,$this->minStartPreparation);
    }

    private function startPlanEstimate( float $start_longitude, float $start_latitude, int $finish_distance=0 ):array{
        $finish_arrival=round($finish_distance/$this->avgSpeed);
        //get day where courier service and store are working
        $firstWorkingWindow=$this->schedule->firstGet();
        $beforeCloseMargin=getenv('store.beforeCloseMargin');
        if( $firstWorkingWindow['begin']>time() || $firstWorkingWindow['end']<time()+$beforeCloseMargin ){//now courier service and store are not working suggest schedule
            return [
                'mode'=>'scheduled',
                'start_plan'=>null,
                'finish_arrival'=>round($finish_distance/$this->avgSpeed)
            ];
        }

        $DeliveryJobModel=model('DeliveryJobModel');
        $shortestChain=$DeliveryJobModel->chainShortestGet($start_longitude, $start_latitude);
        $finish_arrival=round($finish_distance/($shortestChain->courier_speed??$this->avgSpeed));

        $start_plan=$shortestChain->start_plan??0;
        if( !$start_plan ){
            // $CourierModel=model('CourierModel');
            // $CourierModel->deliveryNotReadyNotify();
            /**
             * no active shifts, place order at queue
             */
            $start_plan=time()+$this->heavyLoadTreshold;
            return [
                'mode'=>'awaited',
                'start_plan'=>$start_plan,
                'finish_arrival'=>$finish_arrival
            ];
        }
        if( $start_plan>time()+$this->heavyLoadTreshold ){
            return [
                'mode'=>'awaited',
                'start_plan'=>$start_plan,
                'finish_arrival'=>$finish_arrival
            ];
        }
        return [
            'mode'=>'inited',
            'start_plan'=>$start_plan,
            'finish_arrival'=>$finish_arrival
        ];
    }

    public function scheduleFillShift(){
        for($day=0;$day<$this->deliveryRangeDays;$day++){
            $this->schedule->beginHour($day,$this->shiftStartHour);
            $this->schedule->endHour($day,$this->shiftEndHour);
        }
    }
    public function scheduleFillTimetable( object $store ){
        $todayweekday=date('N')-1;
        for($day=0;$day<$this->deliveryRangeDays;$day++){
            $weekday=($todayweekday+$day)%7;
            $openHour=$store->{"store_time_opens_$weekday"};
            $closeHour=$store->{"store_time_closes_$weekday"};
            if( $openHour===null || $closeHour===null ){//store is not working on that day so purge it
                $this->schedule->purgeIndex("d$day",'same');
                continue;
            }
            if( $openHour>$closeHour ){
                /**
                 * If closes next day, count it as till the midnight
                 */
                $closeHour=24;
            }
            $this->schedule->beginHour($day,$openHour);
            $this->schedule->endHour($day,$closeHour);
        }
    }

    /**
     * Function calculates courier arrival ranges to start or finish location
     */
    public function planScheduleGet( int $roundto=900 ):array{
        $swatch=$this->schedule->swatchGet($roundto);
        $nearest=$this->schedule->swatchNearest;
        return [
            'nearest'=>$nearest,
            'range'=>[
                'dayFirst'=>array_key_first($swatch),
                'dayLast'=>array_key_last($swatch),
                'dayHours'=>$swatch,
            ],
        ];
    }


    //  /**
    //   * @deprecated
    //   */
    // public function planScheduleGetOld( int $plan ){
    //     $timeArrivalRounded=ceil($plan/$this->deliveryDurationDelta)*$this->deliveryDurationDelta;
    //     $dateArrival=date('Y-m-d,H,i',$timeArrivalRounded);
    //     list($arrivalDay,$arrivalHour,$arrivalMinute)=explode(',',$dateArrival);

    //     $defaultMinuteRange=['00','15','30','45'];
    //     $nearest=null;
    //     $range=[
    //         'dayFirst'=>date("Y-m-d",$timeArrivalRounded),
    //         'dayLast'=>date("Y-m-d",strtotime("now +{$this->deliveryRangeDays} day")),
    //         'dayHours'=>[]
    //     ];

    //     for( $day=0; $day<=$this->deliveryRangeDays; $day++ ){
    //         $date=date("Y-m-d",strtotime("now +$day day"));
    //         if($date<$arrivalDay){
    //             continue;
    //         }
    //         for($hour=0; $hour<=24; $hour++){
    //             if( $day==0 && $hour<$arrivalHour ){//calculating offset for first day in range
    //                 continue;
    //             }
    //             if( $day==0 && $hour<$arrivalHour ){//calculating offset for first day in range
    //                 continue;
    //             }
    //             $hourPadded=str_pad($hour,2,'0',STR_PAD_LEFT);
    //             $range['dayHours'][$date]["h_{$hourPadded}"]=[];
    //             if( $day==0 && $hour==$arrivalHour ){//calculating offset for first day in range
    //                 foreach($defaultMinuteRange as $minute){
    //                     if( $minute<$arrivalMinute ){
    //                         continue;
    //                     }
    //                     //$minutePadded=str_pad($minute,2,'0',STR_PAD_LEFT);
    //                     $range['dayHours'][$date]["h_{$hourPadded}"][]=$minute;
    //                     if( !$nearest ){
    //                         $nearest="{$date} {$hourPadded}:{$minute}:00";
    //                     }
    //                 }
    //                 continue;
    //             }
    //             $range['dayHours'][$date]["h_{$hourPadded}"]=$defaultMinuteRange;
    //             if( !$nearest ){
    //                 $nearest="{$date} {$hourPadded}:{$defaultMinuteRange[0]}:00";
    //             }
    //         }
    //     }
    //     return [
    //         'nearest'=>$nearest,
    //         'range'=>$range,
    //     ];
    // }

}