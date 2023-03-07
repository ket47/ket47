<?php
namespace App\Models;
use CodeIgniter\Model;

class MetricModel extends Model{
    
    protected $table      = 'metric_list';
    protected $primaryKey = 'metric_id';
    protected $allowedFields = [
        'come_referrer',
        'come_url',
        'come_media_id',
        'come_inviter_id',
        'user_id',
        'device_is_mobile',
        'device_platform'
        ];

    protected $useSoftDeletes = false;
    protected $createdField=    'created_at';
    protected $updatedField=    'updated_at';
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( $metric ){
        return $this->insert($metric,true);
    }
    
    public function itemUpdate( $metric ){
        if( empty($metric->metric_id) ){
            return 'noid';
        }
        $this->update($metric->metric_id,$metric);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        if(!sudo()){
            return [];
        }
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