<?php
namespace App\Models;

class DeliveryJobModel extends SecureModel{
    
    use FilterTrait;
    
    protected $table      = 'delivery_job_list';
    protected $primaryKey = 'job_id';
    protected $allowedFields = [
        'job_name',
        'order_id',
        'courier_id',
        'start_longitude',
        'start_latitude',
        'start_prep_time',
        'start_arrival_time',
        'start_plan',
        'start_color',
        'start_address',

        'finish_longitude',
        'finish_latitude',
        'finish_arrival_time',
        'finish_color',
        'finish_address',
        'stage'
        ];

    protected $useSoftDeletes = false;
    protected $returnType     = 'object';
    

    private $shiftStartHour=9;
    private $shiftEndHour=23;
    private $shiftEndMarginMinute=5;// min before shiftEnd skip to next day

    private $deliveryRangeDays=2;
    private $deliveryDurationDelta=900;//+-15 min

    private $avgSpeed=3.05;//11 km/h
    public  $avgStartArrival=1200;//20 min
    private $avgFinishArrival=1200;//20 min
    private $minStartPreparation=900;//15 min
    private $heavyLoadTreshold=2400;//40min if shortest start_plan is later than this then report heavyload

    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }
    ///////////////////////////////////////////////////
    //STAGES SECTION
    ///////////////////////////////////////////////////
    public function itemStageSet( int $order_id, string $stage, object $data=null ){
        $data??=(object)[];
        $data->order_id=$order_id;
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        try{
            return $this->{$stageHandlerName}($data);
        } catch (\Throwable $e){
            log_message('error',"itemStageSet: ".$e->getMessage()." line: ".$e->getLine());
        }
    }

    /**
     * Only places on stack as scheduled
     */
    private function onScheduled( object $data ){
        $data->stage='scheduled';
        $this->itemUpsert( $data ); 
        return $data->stage;
    }

    /**
     * Corrects start_plan and places on stack as awaited 
     */
    private function onAwaited( object $data ){
        if( empty($data->start_plan) && empty($data->finish_plan) ){
            return 'invalid';
        }
        if( empty($data->start_longitude) || empty($data->start_latitude) || empty($data->finish_longitude) || empty($data->finish_latitude) ){
            return 'invalid';
        }
        $data->start_prep_time??=$this->minStartPreparation;
        $shortestChain=$this->chainShortestGet($data->start_longitude,$data->start_latitude);
        if( $shortestChain ){
            $data->start_plan=$shortestChain->start_plan;
            $data->courier_id=$shortestChain->courier_id;
            $init_plan=$data->start_plan-$data->start_prep_time;
            if($init_plan<time()){
                return $this->initOrder($data);
            }
        }
        $data->stage='awaited';
        $this->itemUpsert( $data );
        $this->chainJobs();
        return $data->stage;
    }

    /**
     * Here we init the order by changing stage to customer_start
     */
    private function initOrder( $data ){
        /**
         * Should we check if appointed courier is at work right now???
         */
        $order_stage_data=(object)[
            'is_delivery_job_inited'=>1
        ];
        $OrderModel=model('OrderModel');
        $OrderModel->itemStageCreate( $data->order_id, 'customer_start',  $order_stage_data);        
    }

    /**
     * Inits job 
     */
    private function onInited( object $data ){
        $data->stage='inited';
        $this->itemUpsert( $data );
        $this->itemAvailableNotify( $data );
        return $data->stage;
    }

    public function itemAvailableNotify(){
        $this->groupBy('courier_id');
        $this->select('courier_id');
        $this->select("SUM(stage IN ('awaited','inited')) has_unassigned");
        $this->select("SUM(stage IN ('assigned','started')) has_assigned");
        $couriers=$this->get()->getResult();

        foreach($couriers as $cour){
            if( $cour->has_assigned>0 ){
                continue;
            }
            if( $cour->has_unassigned>0 && $cour->courier_id ){
                model('CourierModel')->itemJobAvailableNotify($cour->courier_id);
            }
        }
    }

    private function onAssigned( object $data ){
        if( empty($data->courier_id) || empty($data->order_id) ){
            return 'invalid';
        }
        $job=$this->itemGet(null,$data->order_id);
        if( $job->stage=='awaited' ){
            $job->stage=$this->onInited($data);
        }
        if( $job->stage!='inited' ){
            return 'idle';
        }
        $data->stage='assigned';
        $this->itemUpdate( $data );
        return $data->stage;
    }

    /**
     * Starts job 
     */
    private function onStarted( object $data ){
        $data->stage='started';
        $data->start_plan=time();
        $this->itemUpdate( $data );

        $this->actualPointUpdate( $data->order_id, 'start' );
        $this->chainJobs();
        return $data->stage;
    }
    /**
     * Deletes job 
     * @todo should calculate courier speed
     */
    private function onFinished( object $data ){
        $job=$this->itemGet(null,$data->order_id);
        $this->itemDelete(null,$data->order_id);
        $this->actualPointUpdate( $data->order_id, 'finish' );
        $this->chainJobs();
        
        $activeJobCount=$this->whereIn('stage',['started','assigned'])->select('COUNT(*) jnum')->get()->getRow('jnum');
        if( !$activeJobCount ){
            $CourierModel=model('CourierModel');
            $CourierModel->itemUpdateStatus($job->courier_id,'ready');
        }
        $data->stage='finished';
        return $data->stage;
    }
    private function onCanceled( object $data ){
        $this->itemDelete(null,$data->order_id);

        $this->chainJobs();
        $data->stage='canceled';
        return $data->stage;
    }


    private function actualPointUpdate( int $order_id, string $point_type ){
        $LocationModel=model('LocationModel');
        if( $point_type=='start' ){
            $LocationModel->join('order_list','location_id=order_start_location_id');
        } else {
            $LocationModel->join('order_list','location_id=order_finish_location_id');
        }
        $point=$LocationModel->select('location_longitude longitude,location_latitude latitude,order_courier_id courier_id')->where('order_id',$order_id)->get()->getRow();

        $CourierShiftModel=model('CourierShiftModel');
        $CourierShiftModel->fieldUpdateAllow('actual_longitude');
        $CourierShiftModel->fieldUpdateAllow('actual_latitude');
        $CourierShiftModel->where('shift_status','open')->where('courier_id',$point->courier_id);
        $CourierShiftModel->update(null,['actual_longitude'=>$point->longitude,'actual_latitude'=>$point->latitude]);
    }
    
    /////////////////////////////////////////////////
    //CHAINING SECTION
    /////////////////////////////////////////////////
    public function chainJobs(){
        $CourierShiftModel=model('CourierShiftModel');
        $openShifts=$CourierShiftModel->listGet( (object)['shift_status'=>'open'] );
        if( !$openShifts ){//no open courier shifts
            return false;
        }

        $this->whereIn('stage',['inited','awaited'])->update(null,['courier_id'=>null]);
        $awaitedJobCount=$this->whereIn('stage',['inited','awaited'])->select("COUNT(*) cnt")->get()->getRow('cnt');
        $awaitedPerShift=ceil($awaitedJobCount/count($openShifts));

        $this->transBegin();
        foreach( $openShifts as $shift ){
            $shift->courier_speed=$this->avgSpeed;// m/s
            $shift->last_finish_plan=time();

            $shift=$this->chainStartedJobs($shift);
            $shift=$this->chainAssignedJobs($shift);
            if($awaitedJobCount){
                $shift=$this->chainAwaitedJobs($shift,$awaitedPerShift);  
            }
            $CourierShiftModel->itemUpdate($shift);
        }
        $this->transCommit();
    }

    private function chainStartedJobs( object $shift ){
        $this->select("job_id,start_plan,finish_arrival_time,finish_longitude,finish_latitude");
        if($shift->actual_longitude && $shift->actual_latitude){
            $this->select("ST_Distance_Sphere( POINT(finish_longitude,finish_latitude), POINT({$shift->actual_longitude}, {$shift->actual_latitude}) ) finish_arrival_distance");
        } else {
            $this->select("4000 finish_arrival_distance");//todo find better way
        }
        $this->where('courier_id',$shift->courier_id);
        $this->where('stage','started');
        $started_jobs=$this->orderBy('finish_arrival_distance')->findAll();
        if( !$started_jobs ){//no started jobs, courier is ready now
            return $shift;
        }
        foreach($started_jobs as $job){
            $shift->last_finish_plan=time()+$job->finish_arrival_distance/$shift->courier_speed;
            $shift->last_longitude=$job->finish_longitude;
            $shift->last_latitude=$job->finish_latitude;

            $finish_arrival_time=$shift->last_finish_plan-$job->start_plan;
            $this->update($job->job_id,['finish_arrival_time'=>$finish_arrival_time]);
        }
        return $shift;
    }

    private function chainAssignedJobs( object $shift ){
        $this->where('courier_id',$shift->courier_id);//only courier of shift
        $nextLink=$this->chainLinkFind($shift->last_longitude,$shift->last_latitude,['assigned'],$shift->courier_speed);
        if( !($nextLink->start_arrival_time??null)  ){
            return $shift;
        }
        $start_plan=$shift->last_finish_plan+$nextLink->start_arrival_time;
        $this->update($nextLink->job_id,['start_plan'=>$start_plan]);

        $shift->last_finish_plan=$start_plan+$nextLink->finish_arrival_time;
        $shift->last_longitude=$nextLink->finish_longitude;
        $shift->last_latitude=$nextLink->finish_latitude;
        return $this->chainAssignedJobs( $shift );
    }

    private function chainAwaitedJobs( object $shift, int $limit ){
        if( $limit--<1 ){
            return $shift;
        }
        //$this->having("start_arrival_distance<$shift->courier_reach");
        $this->where('courier_id IS NULL');//skip jobs that are already chained
        $nextLink=$this->chainLinkFind($shift->last_longitude,$shift->last_latitude,['inited','awaited'],$shift->courier_speed);
        if( !($nextLink->start_arrival_time??null)  ){
            return $shift;
        }
        $nextLink->courier_id=$shift->courier_id;
        $nextLink->start_plan=$shift->last_finish_plan+$nextLink->start_arrival_time;
        $init_plan=$nextLink->start_plan-$nextLink->start_prep_time;
        if( $init_plan<time() && $nextLink->stage=='awaited' ){
            $this->onInited($nextLink);
        } else {
            $job_update=[
                'start_plan'=>$nextLink->start_plan,
                //'start_arrival_time'=>$nextLink->start_arrival_time,
                'start_arrival_time'=>$nextLink->start_arrival_time,
                'finish_arrival_time'=>$nextLink->finish_arrival_time,
                'courier_id'=>$nextLink->courier_id,
                'owner_id'=>$shift->owner_id,//copying courier owner_id to job to give permission to courier owner ower it
            ];
            $this->fieldUpdateAllow('owner_id');
            $this->update($nextLink->job_id,$job_update);
        }

        $shift->last_finish_plan=$nextLink->start_plan+$nextLink->finish_arrival_time;
        $shift->last_longitude=$nextLink->finish_longitude;
        $shift->last_latitude=$nextLink->finish_latitude;
        return $this->chainAwaitedJobs( $shift, $limit );
    }

    private function chainLinkFind( float $last_longitude=null, float $last_latitude=null, array $stages, int $courier_speed ){
        $ready_order_time_offset=15*60;//min offset if order is shipment or supplier_finish
        $early_order_time_offset=30/15;//every 30 min gives 15 min preference
        $this->select("delivery_job_list.*");



        /**
         * Calculating arrival times depending on courier speed.
         * Should be optimized as store arrival distance and cour speed
         * We can store it in job_data and make column as generated!
         */
        if($last_longitude && $last_latitude){
            $this->select("ST_Distance_Sphere( POINT(start_longitude,start_latitude), POINT({$last_longitude}, {$last_latitude}) )/$courier_speed start_arrival_time");
        } else {
            $this->select("(4000/$courier_speed) start_arrival_time");//must find better solution for default value
        }
        //do we need it???
        $this->select("ST_Distance_Sphere( POINT(start_longitude,start_latitude), POINT(finish_longitude,finish_latitude) )/$courier_speed finish_arrival_time");








        $this->select("IF(group_type='supplier_finish' OR is_shipment,$ready_order_time_offset,0) readiness_offset");
        $this->select("ROUND( TIMESTAMPDIFF(SECOND,delivery_job_list.created_at,NOW()) *{$early_order_time_offset} *900 )/900 earliness_offset");

        $this->join('order_list','order_id');
        $this->join('order_group_list','group_id=order_group_id');//
        
        $this->whereIn('stage',$stages);
        $this->orderBy("start_arrival_time - readiness_offset - earliness_offset",'ASC',false);

        return $this->limit(1)->get()->getRow();//not using primary key array s unwanted; permission check is unnecessary
    }

    private function chainShortestGet( float $start_longitude, float $start_latitude ){
        $now=time();
        $CourierShiftModel=model('CourierShiftModel');
        $CourierShiftModel->select("courier_id,courier_speed");
        $CourierShiftModel->select("COALESCE(ST_Distance_Sphere( POINT(last_longitude,last_latitude), POINT({$start_longitude}, {$start_latitude}) )/courier_speed,{$this->avgStartArrival})
                                    + GREATEST(IFNULL(last_finish_plan,0),{$now}) start_plan");
        $CourierShiftModel->where('shift_status','open');
        $CourierShiftModel->orderBy('start_plan');
        return $CourierShiftModel->limit(1)->get()->getRow();
    }

    /**
     * Function estimates when courier may become ready
     * estimates courier busyness time, shift times, margins etc
     * 
     * 
     * output is used to respond to user about delivery start mode
     */
    public function startPlanEstimate( float $start_longitude, float $start_latitude, int $finish_distance=0 ):array{
        $nowHour=(int) date('H');
        $nearestMinute=$this->avgStartArrival/60;
        if( $nowHour<$this->shiftStartHour ){
            $time=strtotime(date("Y-m-d {$this->shiftStartHour}:$nearestMinute:00"));
            return [
                'mode'=>'scheduled',
                'time'=>$time,
            ];
        }

        $shortestChain=$this->chainShortestGet($start_longitude, $start_latitude);
        $start_plan=$shortestChain->start_plan??0;
        $startHour=(int) date('H',$start_plan+$this->shiftEndMarginMinute*60);//start_plan should be smaller than shift_end at least by margin
        $finish_arrival=round($finish_distance/($shortestChain->courier_speed??$this->avgSpeed));
        /**
         * startHour will fall outside of shift
         * suggest schedule for tomorrow
         */
        if( $nowHour>=$this->shiftEndHour || $start_plan && $startHour>=$this->shiftEndHour ){
            $start_plan=strtotime(date("Y-m-d {$this->shiftStartHour}:$nearestMinute:00",strtotime("now +1 day")));
            return [
                'mode'=>'scheduled',
                'start_plan'=>$start_plan,
                'finish_arrival'=>$finish_arrival
            ];
        }
        if( !$start_plan ){
            $CourierModel=model('CourierModel');
            $CourierModel->deliveryNotReadyNotify();
            /**
             * no active shifts reject order
             */
            $start_plan=time()+$this->heavyLoadTreshold;
            return [
                'mode'=>'nocourier',
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

    public function routeStatsGet(int $start_location_id, int $finish_location_id){
        $LocationModel=model('LocationModel');
        $default_location_id=$LocationModel->where('location_holder','default_location')->get()->getRow('location_id');

        $maximum_distance=getenv('delivery.radius')+4000;//why 4000???
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
        if($start_finish_distance==0){
            $result->error='start_finish_same';
            $result->deliveryDistance=0;
            return $result;
        }
        $result->deliveryDistance=$start_finish_distance;//m
        return $result;
    }

    public function routePlanGet(int $start_location_id, int $finish_location_id){
        $result=$this->routeStatsGet($start_location_id, $finish_location_id);
        if( isset($result->error) ){
            return $result;
        }
        $LocationModel=model('LocationModel');
        $start_point=$LocationModel->itemGet($start_location_id);
        $finish_distance=$result->deliveryDistance;//m
        $startPlan=$this->startPlanEstimate($start_point->location_latitude,$start_point->location_longitude,$finish_distance);

        $result->start_plan_mode=$startPlan['mode'];
        $result->start_plan=$startPlan['start_plan'];
        $result->finish_arrival=$startPlan['finish_arrival'];
        return $result;
    }

    /**
     * Function calculates courier arrival ranges to start or finish location
     * 
     */
    public function planScheduleGet( int $plan ){
        $timeArrivalRounded=ceil($plan/$this->deliveryDurationDelta)*$this->deliveryDurationDelta;
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
    

































    public function itemGet(int $job_id=null,int $order_id=null){
        if($order_id){
            $jobsAll=$this->where('order_id',$order_id)->find();
            return $jobsAll[0]??null;
        }
        return $this->find($job_id);
    }
    
    public function itemCreate(object $job){
        $this->allowedFields[]='job_data';
        try{
            $colorMap=new \App\Libraries\Coords2Color();
            if( $job->start_latitude && $job->start_longitude ){
                $job->start_color=$colorMap->getColor('claster1',$job->start_latitude,$job->start_longitude);
            }
            if( $job->finish_latitude && $job->finish_longitude ){
                $job->finish_color=$colorMap->getColor('claster1',$job->finish_latitude,$job->finish_longitude);
            }
            $job_id=$this->ignore()->insert($job,true);
            return $job_id;
        } catch(\Throwable $e){
            return $e->getMessage();
        }
    }
    public function itemDataCreate( int $job_id, object $data_create ){
        foreach($data_create as $path=>$value){
            $data_create->{$path}=addslashes($value);
        }
        $this->set("job_data",json_encode($data_create));
        $this->allowedFields[]='job_data';
        $this->update($job_id);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemUpdate(object $job){
        if( $job->job_id??null ){
            $this->update($job->job_id,$job);
        } else 
        if( $job->order_id??null ){
            $this->where('order_id',$job->order_id);
            $this->update(null,$job);
        } else {
            return 'notfound';
        }
        return $this->db->affectedRows()>0?'ok':'idle';
    }

    public function itemDataUpdate( int $job_id, object $data_update ){
        $path_value='';
        foreach($data_update as $path=>$value){
            $path_value.=','.$this->db->escape("$.$path").','.$this->db->escape($value);
        }
        $this->set("job_data","JSON_SET(`job_data`{$path_value})",false);
        $this->allowedFields[]='job_data';
        $this->update($job_id);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemUpsert( object $job ){
        $this->itemCreate($job);
        if( $this->db->affectedRows()>0 ){
            return 'ok'; 
        }
        $this->itemUpdate($job);
        if( $this->db->affectedRows()>0 ){
            return 'ok'; 
        }
        return 'idle';
    }

    public function itemDelete(int $job_id=null,int $order_id=null){
        if($order_id){
            $this->where('order_id',$order_id)->delete();
        } else {
            $this->delete($job_id);
        }
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listGet(){
        $this->select("job_id,job_name,order_id,start_plan,start_color,start_address,finish_arrival_time,finish_color,finish_address,stage");
        
        $this->orderBy('start_plan');
        return $this->get()->getResult();
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