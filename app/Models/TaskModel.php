<?php
namespace App\Models;
use CodeIgniter\Model;

class TaskModel extends Model{
    
    protected $table      = 'task_list';
    protected $primaryKey = 'task_id';
    protected $allowedFields = [
        'task_name',
        'task_programm',
        'task_result',
        'task_interval_day',
        'task_interval_hour',
        'task_interval_min',
        'task_next_start',
        'task_last_start',
        'is_singlerun'
        ];

    protected $useSoftDeletes = false;
    protected $createdField=    'created_at';
    protected $updatedField=    'updated_at';
    
    public function tick(){
        
    }
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( $task ){
        if( $task['task_programm']??null && is_array($task['task_programm']) ){
            $task['task_programm']= json_encode($task['task_programm']);
        }
        $this->insert($task);
        return $this->db->insertID();
    }
    
    public function itemUpdate( $task ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( !$task || !isset($task->task_id) ){
            return 'error_empty';
        }
        $this->update($task->task_id,$task);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete($task_id){
        $this->delete($task_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function listGet( $filter=null ){
        if( $filter['is_pending']??0 ){
            $this->where('task_next_start<NOW() OR task_next_start IS NULL');
        }
        $this->orderBy('task_next_start');
        $tasks=$this->get()->getResult();
        return $tasks;
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