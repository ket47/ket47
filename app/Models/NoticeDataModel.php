<?php
namespace App\Models;
class NoticeDataModel extends SecureModel{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'notice_data_list';
    protected $primaryKey = 'notice_id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'notice_data',
        'notice_status',
        ];

    protected $useSoftDeletes = true;

    protected function initialize(){
        $this->query("SET character_set_results = utf8mb4, character_set_client = utf8mb4, character_set_connection = utf8mb4, character_set_database = utf8mb4, character_set_server = utf8mb4");
    }
    
    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( object $notice ){
        $this->fieldUpdateAllow('notice_holder_id');
        $this->fieldUpdateAllow('notice_type');
        $this->fieldUpdateAllow('owner_id');

        $notice->owner_id=session()->get('user_id');
        if( $notice->owner_id<1 ){
            return 'unathorized';
        }
        $notice_id=$this->insert($notice,true);


        $NoticeTargetModel=model('NoticeTargetModel');
        //$NoticeTargetModel->listCreate($notice_id,$target_user_ids,$expired_at=null);

        return $notice_id;

    }
    
    public function itemUpdate( object $notice_update ){
        if( empty($notice_update->notice_id) ){
            return 'noid';
        }
        $this->update($notice_update->notice_id,$notice_update);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete(){
        return false;
    }
    

    public function listGet( int $target_user_id, int $last_notice_id ){
        $batch_limit=100;
        $history_depth_days=180;
        $history_from_date=date('Y-m-d H:i:s', strtotime("now - $history_depth_days days"));
        $this->where('created_at>',$history_from_date);

        if( !sudo() ){
            $this->join('notice_target_list','notice_id');
            $this->where('delivery_status','created');
            $this->where('target_user_id',$target_user_id);
        }
        $this->join('user_list','notice_data_list.owner_id=user_id');
        $this->where('notice_data_list.notice_id>',$last_notice_id);
        $this->orderBy('notice_data_list.notice_id');
        $this->select('notice_id,notice_target_id,notice_holder_id,notice_type,notice_status,notice_data,user_name');
        $this->limit($batch_limit);
        $notices=$this->findAll();
        $end_of_list=1;
        if( count($notices) == $batch_limit ){
            $end_of_list=0;
        }
        return [
            'notices'=>$notices,
            'end_of_list'=>$end_of_list,
        ];
    }

    public function chatListGet( int $notice_holder_id, int $last_notice_id ){
        $this->where("notice_id>{$last_notice_id}");
        $this->where("notice_type",'chat');


        $user_id=session()->get('user_id');

        $this->join("notice_target_list","notice_id");
        $this->where('notice_target_list.notice_holder_id',$notice_holder_id);
        $this->select("sender_user_id='{$user_id}' is_owner",false);

        $this->select("notice_text,updated_at");



        return $this->findAll();
    }










    public function chatRoomListGet( int $target_user_id ){
        
    }
    
}