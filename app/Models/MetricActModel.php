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
    
}