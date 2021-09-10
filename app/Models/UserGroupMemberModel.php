<?php
namespace App\Models;

class UserGroupMemberModel extends PermissionLayer{
    
    protected $table      = 'user_group_member_list';
    protected $primaryKey = 'user_id';
    protected $allowedFields = [
        'user_id',
        'user_group_id'
        ];
    
    
    public function itemUpdate( $user_id, $user_group_id, $value ){
        if( $value ){
            return $this->userGroupJoin($user_id, $user_group_id);
        }
        return $this->userGroupLeave($user_id, $user_group_id);
    }
    
    
    public function userMemberGroupsGet($user_id){
        return $this->select('GROUP_CONCAT(user_group_list.user_group_id) user_group_ids,GROUP_CONCAT(user_group_type) user_group_types')
                ->where('user_id',$user_id)
                ->join('user_group_list', 'user_group_list.user_group_id = user_group_member_list.user_group_id')
                ->get()->getRow();
    }
    
    public function userGroupJoinByType($user_id,$user_group_type){
        $user_group_id=$this
                ->query("SELECT user_group_id FROM user_group_list WHERE user_group_type='$user_group_type'")
                ->getRow('user_group_id');
        return $this->userGroupJoin($user_id,$user_group_id);
    }
    
    public function userGroupJoin($user_id,$user_group_id){
        $this->permit(null,'w');
        return $this->insert(['user_id'=>$user_id,'user_group_id'=>$user_group_id]);
    }
    
    public function userGroupLeave($user_id,$user_group_id){
        $this->permitWhere('w');
        return $this
                ->where('user_id',$user_id)
                ->where('user_group_id',$user_group_id)
                ->delete();
    }
    
    
}