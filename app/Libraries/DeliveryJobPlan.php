<?php
namespace App\Libraries;
class DeliveryJobPlan{

    private $shiftStartHour=9;
    private $shiftEndHour=23;
    private $deliveryRangeDays=4;
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

        $PrefModel=model('PrefModel');
        $this->shiftStartHour=$PrefModel->itemGet('shiftStartHour','pref_value');
        $this->shiftEndHour=$PrefModel->itemGet('shiftEndHour','pref_value');
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

    /**
     * Function caclutates cost of delivery, gain of courier, validates route
     */
    public function routeReckonGet( int $start_location_id, int $finish_location_id, int $order_sum_product=0, int $store_comission_fee=0 ):object{
        $routeData=$this->routeValidate( $start_location_id, $finish_location_id );
        if( $routeData['error'] ){
            return (object)[
                'error'=>$routeData['error'],
            ];
        }
        $reserved_profit_fee=10;//minimum profit from order
        $reserved_bonus_fee=3;//budget to award courier by rating

        $PrefModel=model('PrefModel');
        $delivery_cost=$PrefModel->itemGet('delivery_cost','pref_value');
        $delivery_fee_distance=$PrefModel->itemGet('delivery_fee_distance','pref_value');

        $delivery_heavy_cost=0;
        $delivery_heavy_bonus=0;
        $delivery_heavy_level=$PrefModel->itemGet('delivery_heavy_level','pref_value');
        if($delivery_heavy_level>0){
            $delivery_heavy_cost=$PrefModel->itemGet("delivery_heavy_cost_{$delivery_heavy_level}",'pref_value');
            $delivery_heavy_bonus=$PrefModel->itemGet("delivery_heavy_bonus_{$delivery_heavy_level}",'pref_value');
        }

        $comission_budget_total=0;
        if( $order_sum_product && $store_comission_fee ){
            $comission_budget_total=$order_sum_product*($store_comission_fee-$reserved_profit_fee-$reserved_bonus_fee)/100;
        }

        $delivery_rating_pool=$order_sum_product*$reserved_bonus_fee/100;
        $delivery_cost_distance=$routeData['deliveryDistance']*$delivery_fee_distance/1000;//meters to kilometers
        $delivery_gain_base=round( ($delivery_cost+$delivery_cost_distance+$delivery_heavy_bonus)/10)*10;
        // $delivery_rating_bonus=0;
        // if( isset($meta->order_sum_product) && isset($meta->courier_id) ){
        //     $CourierModel=model('CourierModel');
        //     $ratings=$CourierModel->itemRatingGet($meta->courier_id);
        //     $avg_rating=( ($ratings[0]->rating??0)+($ratings[1]->rating??0) ) / 2;
        //     $delivery_rating_bonus=$meta->order_sum_product*$reserved_bonus_fee/100*$avg_rating;
        // }
        //$courier_gain_total=$delivery_gain_base+$delivery_rating_bonus;

        $customer_cost_total=round(max($delivery_cost+$delivery_cost_distance+$delivery_heavy_cost-$comission_budget_total,0)/10)*10;
        return (object)[
            //'error'=>null,
            //'deliveryDistance'=>$routeData['deliveryDistance'],
            'customer_cost_total'=>$customer_cost_total,
            'delivery_gain_base'=>$delivery_gain_base,
            'delivery_rating_pool'=>$delivery_rating_pool,
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
        $offset=20*60;//40 min
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
        if( $firstWorkingWindow['begin']>time() || $firstWorkingWindow['end']<time() ){//now courier service and store are not working suggest schedule
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
        $beforeEndMargin=getenv('schedule.shiftBeforeEndMargin')??0;
        for($day=0;$day<$this->deliveryRangeDays;$day++){
            $startingTime=$this->schedule->timeGet( $day, $this->shiftStartHour );
            $endingTime=$this->schedule->timeGet( $day, $this->shiftEndHour );
            $this->schedule->begin($startingTime);
            $this->schedule->end($endingTime-$beforeEndMargin);
        }
    }
    public function scheduleFillTimetable( object $store ){
        $beforeCloseMargin=getenv('schedule.storeBeforeCloseMargin');
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
            $openingTime=$this->schedule->timeGet( $day, $openHour );
            $closingTime=$this->schedule->timeGet( $day, $closeHour );
            $this->schedule->begin($openingTime);
            $this->schedule->end($closingTime-$beforeCloseMargin);
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
}