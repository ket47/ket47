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
            $this->insert($new_act);
        } catch(\Throwable $e){
            pl($e->getMessage());
            return false;
        }
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