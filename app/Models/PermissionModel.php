<?php
namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model{
    protected $table      = 'user_role_permission_list';
    protected $primaryKey = 'permission_id';
    protected $allowedFields = [
        'permited_class',
        'permited_method',
        'owner',
        'ally',
        'other'
        ];
    
    public function permissionListGet(){
        return $this->get()->getResult();
    }
    
    public function permissionSave($permited_owner,$permited_class,$permited_method,$permited_rights){
        $permission_id=$this
                ->where('permited_class',$permited_class)
                ->where('permited_method',$permited_method)->get()->getRow('permission_id');
        if( !$permission_id ){
            $data=[
                'permited_class'=>$permited_class,
                'permited_method'=>$permited_method
            ];
            $permission_id=$this->insert($data);
        }
        return $this->update($permission_id,[$permited_owner=>$permited_rights]);
    }
}