<?php
namespace App\Models;

class DeliveryJobModel extends SecureModel{
    
    use DeliveryJobNotificationTrait;
    use DeliveryJobChainTrait;

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

    /**
     * scheduled
     * awaited
     * assigned
     * started
     * finished
     * canceled
     */

    public function itemStageSet( int $order_id, string $stage, object $data=null ){
        $data??=(object)[];
        $data->order_id=$order_id;
        $stageHandlerName = 'on'.str_replace(' ', '', ucwords(str_replace('_', ' ', $stage)));
        try{
            $result=$this->{$stageHandlerName}($data);
            if( $result=='invalid' ){
                pl(["DeliveryJob itemStageSet",$order_id, $stage, $data]);
            }
            return $result;
        } catch (\Throwable $e){
            log_message('error',"itemStageSet: ".$e->getMessage()." line: ".$e->getLine()." ".$e->getFile());
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
        /**
         * sometimes start_plan is null untill figuring out let comment it
         */


        // if( empty($data->start_plan) && empty($data->finish_plan) ){
        //     return 'invalid';
        // }
        if( empty($data->start_longitude) || empty($data->start_latitude) || empty($data->finish_longitude) || empty($data->finish_latitude) ){
            return 'invalid';
        }
        $data->start_prep_time??=$this->minStartPreparation;
        // $shortestChain=$this->chainShortestGet($data->start_longitude,$data->start_latitude);
        // if( $shortestChain ){
        //     $data->start_plan=$shortestChain->start_plan;
        //     $data->courier_id=$shortestChain->courier_id;
        // }
        $data->stage='awaited';
        $this->itemUpsert( $data );
        $this->chainJobs();
        //$this->itemNextCheck($data->courier_id);
        return $data->stage;
    }

    private function onAssigned( object $data ){
        if( empty($data->courier_id) || empty($data->order_id) ){
            return 'invalid';
        }
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $CourierGroupMemberModel->joinGroupByType($data->courier_id,'busy',true);

        $data->stage='assigned';
        $this->itemUpdate( $data );
        $this->chainJobs();
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
        $isCourierReadyForNext=$this->itemNextCheck($job->courier_id);
        if( $isCourierReadyForNext ){//directly change courier group
            $CourierGroupMemberModel=model('CourierGroupMemberModel');
            $CourierGroupMemberModel->joinGroupByType($job->courier_id,'ready',true);
        }
        return 'finished';
    }

    /**
     * Deletes job 
     */
    private function onCanceled( object $data ){
        $job=$this->itemGet(null,$data->order_id);
        $this->itemDelete(null,$data->order_id);

        $this->chainJobs();
        $isCourierReadyForNext=$this->itemNextCheck($job->courier_id);
        if( $isCourierReadyForNext ){//directly change courier group
            $CourierGroupMemberModel=model('CourierGroupMemberModel');
            $CourierGroupMemberModel->joinGroupByType($job->courier_id,'ready',true);
        }
        return 'canceled';
    }


    private function actualPointUpdate( int $order_id, string $point_type ){
        $LocationModel=model('LocationModel');
        $LocationModel->where('order_id',$order_id);
        $LocationModel->select('location_longitude longitude,location_latitude latitude,order_courier_id courier_id');
        if( $point_type=='start' ){
            $LocationModel->join('order_list','location_id=order_start_location_id');
        } else {
            $LocationModel->join('order_list','location_id=order_finish_location_id');
        }
        $point=$LocationModel->get()->getRow();

        $CourierShiftModel=model('CourierShiftModel');
        $CourierShiftModel->where('shift_status','open')->where('courier_id',$point->courier_id);
        $CourierShiftModel->allowWrite();
        $CourierShiftModel->update(null,['actual_longitude'=>$point->longitude,'actual_latitude'=>$point->latitude]);
    }
    
    /////////////////////////////////////////////////
    //PLANNING SECTION
    /////////////////////////////////////////////////

    /**
     * Function estimates when courier may become ready
     * estimates courier busyness time, shift times, margins etc
     * 
     * 
     * output is used to respond to user about delivery start mode
     */
    /**
     * @deprecated
     */
    public function startPlanEstimate( float $start_longitude, float $start_latitude, int $finish_distance=0 ):array{
        $nowHour=(int) date('H');
        $nearestMinute=$this->avgStartArrival/60;
        /**
         * Customer requests before shift start. 
         * So we offer to schedule after shift start
         */
        if( $nowHour<$this->shiftStartHour ){
            $time=strtotime(date("Y-m-d {$this->shiftStartHour}:$nearestMinute:00"));
            return [
                'mode'=>'scheduled',
                'start_plan'=>$time,
            ];
        }

        $shortestChain=$this->chainShortestGet($start_longitude, $start_latitude);
        // ql($this);
        // pl($shortestChain);

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

    /**
     * @deprecated
     */
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

    /**
     * @deprecated
     */
    public function routePlanGet(int $start_location_id, int $finish_location_id){
        $result=$this->routeStatsGet($start_location_id, $finish_location_id);
        if( isset($result->error) ){
            return $result;
        }
        $LocationModel=model('LocationModel');
        $start_point=$LocationModel->itemGet($start_location_id);
        $finish_distance=$result->deliveryDistance;//m
        $startPlan=$this->startPlanEstimate($start_point->location_longitude,$start_point->location_latitude,$finish_distance);

        $result->start_plan_mode=$startPlan['mode'];
        $result->start_plan=$startPlan['start_plan'];
        $result->finish_arrival=$startPlan['finish_arrival']??0;
        return $result;
    }

    /**
     * Function calculates courier arrival ranges to start or finish location
     * @deprecated
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
            $err=$e->getMessage();
            pl(['DeliveryJob->itemCreate',$err]);
            return $err;
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
        // is_shipment deprecated
        $this->select("
        job_id,
        job_name,
        courier_id,
        IFNULL(job_data->>'$.order_script',null) order_script,
        IFNULL(job_data->>'$.is_shipment',0) is_shipment,
        IFNULL(job_data->>'$.payment_by_cash',0) payment_by_cash,
        IFNULL(job_data->>'$.finish_plan_scheduled',0) finish_plan_scheduled,
        order_id,
        start_plan,
        start_color,
        start_address,
        start_longitude,
        start_latitude,
        finish_arrival_time,
        finish_color,
        finish_address,
        finish_longitude,
        finish_latitude,
        stage
        ");
        $this->orderBy('courier_id');
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