<?php
namespace App\Models;
class NoticeHolderModel extends SecureModel{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'notice_holder_list';
    protected $primaryKey = 'notice_holder_id';
    protected $returnType = 'object';
    protected $allowedFields = [
        ];

    protected $useSoftDeletes = false;

    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }
    
    public function itemGet( int $notice_holder_id ){
        $this->select('notice_holder_id,notice_holder_name,updated_at');
        $this->select("notice_holder_data->>'$.preview_text' preview_text");
        $room=$this->find($notice_holder_id);
        return $room;
    }
    
    public function itemCreate( object $holder ){
        $this->fieldUpdateAllow('notice_holder_name');
        $this->fieldUpdateAllow('notice_holder_data');
        $this->fieldUpdateAllow('owner_id');

        $holder->owner_id=session()->get('user_id');
        if( $holder->owner_id<1 ){
            return 'unathorized';
        }
        $notice_holder_id=$this->insert($holder,true);
        return $notice_holder_id;

    }
    
    public function itemUpdate( object $holder_update ){
        if( empty($holder_update->notice_holder_id) ){
            return 'noid';
        }
        $this->update($holder_update->notice_holder_id,$holder_update);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete(){
        return false;
    }
    

    public function listGet( int $target_user_id, int $last_notice_id=null ){
        $this->select("SUM(IF(delivery_status='created',1,0)) new_notice_count");
        $this->join('notice_target_list','notice_holder_id','left');
        $this->where("delivery_status='created'");
        $this->groupBy('notice_holder_id');
        $this->orderBy('new_notice_count DESC');
        $this->orderBy('updated_at DESC');

        $this->select('notice_holder_id,notice_holder_name,updated_at');
        $this->select("notice_holder_data->>'$.preview_text' preview_text");
        return $this->findAll();
    }










    public function chatRoomListGet( int $target_user_id ){
        
    }
    
}