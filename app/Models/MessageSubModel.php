<?php
namespace App\Models;
use CodeIgniter\Model;

class MessageSubModel extends Model{

    use PermissionTrait;

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
        if(!$user_id || $user_id<1){
            return 'forbidden';//if notauthorized then login form kicks on
        }
        // $is_registered=$this->where('sub_registration_id',$registration_id)->get()->getRow('sub_registration_id');
        // if($is_registered){
        //     return 'ok';
        // }
        $sub=[
            'sub_user_id'=>$user_id,
            'sub_registration_id'=>$registration_id,
            'sub_type'=>$type,
            'sub_device'=>$user_agent
        ];
        $this->ignore()->insert($sub,true);
        $is_inserted=$this->affectedRows()>0?'ok':'idle';
        if( $is_inserted=='ok' ){
            return 'ok';
        }
        /**
         * sub_user_id may change for same device. so update sub_user_id
         */
        $this->where('sub_registration_id',$registration_id);
        $this->update(null,$sub);
        return $this->affectedRows()>0?'ok':'idle';
    }

    public function listGet($user_id){
        $this->permitWhere('r');
        return $this->where('sub_user_id',$user_id)->get()->getResult();
    }
    
}