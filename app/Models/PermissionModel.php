<?php
namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model{
    protected $table      = 'user_group_permission_list';
    protected $primaryKey = 'permission_id';
    protected $allowedFields = [
        'user_group_id',
        'permited_class',
        'permited_method'
        ];
    
    public function getUserGroups(){
        $sql="SELECT * FROM user_group_list";
        $user_groups= $this->query($sql)->getResult();
        foreach($user_groups as $group){
            $group->permission_list=$this->table('user_group_permission_list')
                    ->where('user_group_id',$group->user_group_id)
                    ->get()
                    ->getResult();
        }
        return $user_groups;
    }
    
    public function permissionSave($permited_group_id,$permited_class,$permited_method,$is_enabled){
        $permission_id=$this->where('user_group_id',$permited_group_id)
                ->where('permited_class',$permited_class)
                ->where('permited_method',$permited_method)->get()->getRow('permission_id');
        if( !$permission_id && $is_enabled ){
            $data=[
                'user_group_id'=>$permited_group_id,
                'permited_class'=>$permited_class,
                'permited_method'=>$permited_method
            ];
            return $this->insert($data);
        }
        if($permission_id && !$is_enabled ){
            return $this->delete($permission_id);
        }
    }
}