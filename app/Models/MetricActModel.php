<?php
namespace App\Models;
use CodeIgniter\Model;

class MetricActModel extends Model{
    protected $table      = 'metric_act_list';
    protected $primaryKey = 'act_id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'metric_id',
        'act_group',
        'act_type',
        'act_result',
        'act_description',
        'act_target_id',
        'act_data',
        ];

    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;
    
    public function itemGet(){
        return false;
    }
    
    private function headerIdGet():int {
        $metricsHeaderId=session()->get('metricsHeaderId');
        if( $metricsHeaderId ){
            return $metricsHeaderId;
        }
        return model('MetricModel')->itemIdGet();
    }

    public function itemCreate( object $new_act ):bool{
        try{
            $new_act->metric_id??=$this->headerIdGet();
            if( ($new_act->append??0) && $this->itemAppend($new_act) ){
                //search for similar query and update it
                return true;
            }
            $this->insert($new_act);
        } catch(\Throwable $e){
            pl($e->getMessage());
            return false;
        }
        return true;
    }

    private function itemAppend( object $new_act ){
        $this->where('metric_id',$new_act->metric_id);
        $this->where('act_group',$new_act->act_group);
        $this->where('act_type',$new_act->act_type);
        $this->where('act_target_id',$new_act->act_target_id);

        $descr_escaped=$this->db->escape($new_act->act_description);
        $this->where("$descr_escaped LIKE CONCAT(act_description,'%')",null);
        $this->orderBy('act_description','DESC');
        $act=$this->select("act_id")->limit(1)->get()->getRow();

        if( !$act ){
            return false;
        }
        $this->update($act->act_id,$new_act);
        return true;
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
    public function getHourlyUserActivity($filter)
    {
        if( !sudo() ){
            return [];
        }
        $this->select("
            metric_act_list.metric_id,
            metric_act_list.act_group,
            metric_act_list.act_type,
            metric_act_list.act_result,
            metric_act_list.act_description,
            metric_act_list.created_at,
            ml.come_referrer,
            ml.come_media_id,
            ml.device_platform,
            ml.created_at AS session_start,
            ul.user_id,
            ul.user_name,
            ul.user_phone,
            DATE_FORMAT(metric_act_list.created_at, '%Y-%m-%d %H:00:00') as hour_slot
        ");
        $this->select("COUNT(ol.order_id) AS user_orders");
        $this->select("COALESCE((SELECT ugl.group_type FROM user_group_list ugl JOIN user_group_member_list ugml USING(group_id) WHERE ul.user_id = ugml.member_id ORDER BY group_type = 'customer',group_type = 'courier' LIMIT 1), 'guest') AS group_type");

        $this->join('metric_list ml', 'ml.metric_id = metric_act_list.metric_id');
        $this->join('user_list ul', 'ml.user_id = ul.user_id', 'left');
        $this->join('order_list ol',"ul.user_id = ol.owner_id AND order_status='finished'",'left');

        $this->where("metric_act_list.created_at >=",date('Y-m-d H:i:s', strtotime($filter->start_at)));
        $this->where("metric_act_list.created_at <=",date('Y-m-d H:i:s', strtotime($filter->finish_at)));
        $this->havingIn('group_type',$filter->user_group);
        $this->groupBy('metric_id,act_id');

        $this->orderBy('session_start DESC, metric_act_list.created_at ASC');
        if($filter->order_only){
            $this->having('user_orders > 0');
        }
        return $this->get()->getResultArray();
    }
}