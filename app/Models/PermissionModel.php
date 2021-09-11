<?php
namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model{
    
    use PermissionTrait;
    
    protected $table      = 'user_role_permission_list';
    protected $primaryKey = 'permission_id';
    protected $allowedFields = [
        'permited_class',
        'permited_method',
        'owner',
        'ally',
        'other'
        ];
    
    public function listGet(){
        $this->listFillSession();
        if( sudo() ){
            return $this->get()->getResult();
        }
        return [];
    }
    
    public function listFillSession(){
        $permission_list=$this->get()->getResult();
        $permissions=[];
        foreach($permission_list as $perm){
            $permissions[$perm->permited_class]=[
                'owner'=>$perm->owner,
                'ally'=>$perm->ally,
                'other'=>$perm->other,
            ];
        }
        session()->set('permissions',$permissions);
    }
    
    public function itemCreate($permited_owner,$permited_class,$permited_method,$permited_rights){
        if( !sudo() ){
            return false;
        }
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