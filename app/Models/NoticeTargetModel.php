<?php
namespace App\Models;
class NoticeTargetModel extends SecureModel{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'notice_target_list';
    protected $primaryKey = 'notice_target_id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'notice_id',
        'target_user_id',
        'delivery_status',
        'expired_at'
        ];

    protected $useSoftDeletes = true;

    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( object $notice_target ){
        $predis = \Config\Services::predis();
        $predis->setEx("sse_hasupdate_{$notice_target->target_user_id}",300*60,1);//300 min
        return $this->insert($notice_target,true);
    }
    
    public function itemUpdate( object $notice_update ){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listCreate( int $notice_id, array $target_user_ids, $expired_at ){
        foreach( $target_user_ids as $target_user_id ){
            $notice_target=(object)[
                'notice_id'=>$notice_id,
                'target_user_id'=>$target_user_id,
                'expired_at'=>$expired_at,
            ];
            $this->itemCreate( $notice_target );
        }
        return false;
    }
    
}