<?php
namespace App\Models;
use CodeIgniter\Model;

class MessageSubModel extends Model{
    protected $table      = 'message_sub_list';
    protected $primaryKey = 'sub_id';
    protected $allowedFields = [
            'sub_user_id',
            'sub_registration_id',
            'sub_type',
            'sub_device'
        ];
    protected $useSoftDeletes = false;

    public function itemCreate($registration_id,$type,$user_agent){
        $user_id=session()->get('user_id');
        if(!$user_id){
            return 'notauthorized';
        }
        $sub=[
            'sub_user_id'=>$user_id,
            'sub_registration_id'=>$registration_id,
            'sub_type'=>$type,
            'sub_device'=>$user_agent
        ];
        try{
            $this->insert($sub);
        } catch(\Exception $e){
            return 'duplicate';
        }
        return $this->affectedRows()>0?'ok':'idle';
    }

    public function listGet($user_id){
        $this->select('sub_registration_id');
        return $this->where('sub_user_id',$user_id)->get()->getResult();
    }
    
}