<?php
namespace App\Models;

trait DeliveryJobChainTrait{

    public function chainShortestGet( float $start_longitude, float $start_latitude ){
        $now=time();
        $CourierShiftModel=model('CourierShiftModel');
        $CourierShiftModel->select("courier_id,courier_speed");
        $CourierShiftModel->select("COALESCE(FLOOR(ST_Distance_Sphere( POINT(last_longitude,last_latitude), POINT({$start_longitude}, {$start_latitude}) )/courier_speed),{$this->avgStartArrival})
                                    + GREATEST(IFNULL(last_finish_plan,0),{$now}) start_plan");
        $CourierShiftModel->where('shift_status','open');
        $CourierShiftModel->orderBy('start_plan');
        return $CourierShiftModel->limit(1)->get()->getRow();
    }

    private $maxJobsPerShift=2;
    public function chainJobs(){
        $CourierShiftModel=model('CourierShiftModel');
        $CourierShiftModel->allowRead();//called from cronjob as guest so need to skip permission check
        $openShifts=$CourierShiftModel->orderBy('last_finish_plan')->listGet( (object)['shift_status'=>'open'] );
        if( !$openShifts ){//no open courier shifts
            return false;
        }

        $awaitedJobCount=$this->where('stage','awaited')->select("COUNT(*) awaited_count")->get()->getRow('awaited_count');
        $awaitedPerShift=max(floor($awaitedJobCount/count($openShifts)),$this->maxJobsPerShift);

        $this->transBegin();
        $this->whereIn('stage',['awaited'])->update(null,['courier_id'=>null]);
        foreach( $openShifts as $shift ){
            $shift->courier_speed=$this->avgSpeed;// m/s temporary must be set at shift start
            $shift->last_finish_plan=time();
            $shift->assigned_in_chain=[];

            $shift=$this->chainStartedJobs($shift);
            $shift=$this->chainAssignedJobs($shift);
            $shift=$this->chainAwaitedJobs($shift,$awaitedPerShift);
            $CourierShiftModel->allowWrite();//called from cronjob as guest so need to skip permission check
            $CourierShiftModel->itemUpdate($shift);
        }
        $this->transCommit();
    }

    private function chainStartedJobs( object $shift ){
        $this->where('courier_id',$shift->courier_id);
        $this->where('stage','started');
        $this->select("job_id,start_plan,finish_arrival_time,finish_longitude,finish_latitude");
        if($shift->actual_longitude && $shift->actual_latitude){
            $this->select("ST_Distance_Sphere( POINT(finish_longitude,finish_latitude), POINT({$shift->actual_longitude}, {$shift->actual_latitude}) ) finish_arrival_distance");
        } else {
            $this->select("4000 finish_arrival_distance");//todo find better way
        }
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

    /**
     * When courier closes the shift
     * unassign all delivery jobs of that courier and make them awaiting
     */
    public function unassignJobs( $courier_id ){
        $this->where('courier_id',$courier_id);
        $this->whereIn('stage',['assigned','awaited']);
        $this->update(null,['stage'=>'awaited','courier_id'=>null]);
        $this->chainJobs();
    }

    private function chainAssignedJobs( object $shift ){
        if($shift->assigned_in_chain){
            $this->whereNotIn('job_id',$shift->assigned_in_chain);
        }
        $this->where('courier_id',$shift->courier_id);//only courier of shift
        $nextLink=$this->chainLinkFind($shift->last_longitude,$shift->last_latitude,['assigned'],$shift->courier_speed);
        if( !($nextLink->start_arrival_time??null)  ){
            return $shift;
        }
        $start_plan=$shift->last_finish_plan+$nextLink->start_arrival_time;
        $this->update($nextLink->job_id,['start_plan'=>$start_plan]);

        $shift->assigned_in_chain[]=$nextLink->job_id;
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
        $nextLink=$this->chainLinkFind($shift->last_longitude,$shift->last_latitude,['awaited'],$shift->courier_speed);
        if( !($nextLink->start_arrival_time??null)  ){
            return $shift;
        }
        $nextLink->courier_id=$shift->courier_id;
        $nextLink->start_plan=$shift->last_finish_plan+$nextLink->start_arrival_time;
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

        $shift->last_finish_plan=$nextLink->start_plan+$nextLink->finish_arrival_time;
        $shift->last_longitude=$nextLink->finish_longitude;
        $shift->last_latitude=$nextLink->finish_latitude;
        return $this->chainAwaitedJobs( $shift, $limit );
    }

    private function chainLinkFind( float $last_longitude=null, float $last_latitude=null, array $stages, float $courier_speed ){
        $ready_order_time_offset=15*60;//15min offset if order is shipment or supplier_finish
        $early_order_time_offset=30/15;//every 30 min gives 15 min preference
        $this->select("job_id,finish_latitude,finish_longitude");
        if($last_longitude && $last_latitude){
            $this->select("ST_Distance_Sphere( POINT(start_longitude,start_latitude), POINT({$last_longitude}, {$last_latitude}) )/$courier_speed start_arrival_time");
        } else {
            $this->select("(4000/$courier_speed) start_arrival_time");//must find better solution for default value
        }
        $this->select("CEIL( IFNULL(job_data->>'$.distance',0)/$courier_speed ) finish_arrival_time");
        $this->select("ROUND( TIMESTAMPDIFF(SECOND,delivery_job_list.created_at,NOW()) *{$early_order_time_offset} *900 )/900 earliness_offset");

        $this->select("IF(group_type='supplier_finish' OR is_shipment OR order_script='shipment',$ready_order_time_offset,0) readiness_offset");
        $this->join('order_list','order_id');
        $this->join('order_group_list','group_id=order_group_id');//
        
        $this->whereIn('stage',$stages);
        $this->orderBy("start_arrival_time - readiness_offset - earliness_offset",'ASC',false);

        return $this->limit(1)->get()->getRow();//not using primary key array s unwanted; permission check is unnecessary
    }
}