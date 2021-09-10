<?php
namespace App\Models;

use CodeIgniter\Model;
class PermissionLayer extends Model{
//    protected function userRole($item_id){
//        $session=session();
//        if( sudo() ){
//            return 'admin';
//        }
//        $user_id=$session->get('user_id')??0;
//        $sql="
//            SELECT
//                IF(owner_id=$user_id
//                    ,'owner',
//                IF('$user_id' IN(owner_ally_ids)
//                    ,'ally'
//                    ,'other'
//                )) user_role
//            FROM
//                $this->table
//            WHERE
//                $this->primaryKey='$item_id'
//            ";
//        return $this->query($sql)->getRow('user_role');
//    }
//    
//    protected function permit( $item_id, $right ){
//        $class_name=(new \ReflectionClass($this))->getShortName();
//        $permission_name="permission.{$class_name}.{$item_id}.{$right}";
//        $permission=session()->get($permission_name);
//        if( !isset($permission) ){
//            if( $item_id ){
//                $user_role=$this->userRole($item_id);
//            } else {
//                $user_role='owner';
//            }
//            $permission=0;
//            if($user_role=='admin'){
//                $permission=1;//grant all permissions to admin
//            } else
//            if( isset($this->permissions[$class_name][$user_role]) ){
//                $rights=$this->permissions[$class_name][$user_role];
//                $permission=str_contains($rights,$right)?1:0;
//            }
//            session()->set($permission_name,$permission);
//        }
//        if(!$permission){
//            header("X-permission-info: Access denied for {$class_name}.{$item_id}.{$right}");
//        }
//        return $permission;
//    }
    
    public function permitWhere( $right ){
        $permission_filter=$this->permitWhereGet($right);
        if($permission_filter!=""){
            $this->where($permission_filter);
        }
    }
    
    public function permitWhereGet( $right ){
        if( sudo() ){
            return "";//All granted
        }
        $user_id=session()->get('user_id');
        $permited_class_name=(new \ReflectionClass($this))->getShortName();
        $permission_name="permitWhere.{$permited_class_name}.{$user_id}.{$right}";
        
        $cached_permission=session()->get($permission_name);
        if( isset($cached_permission) ){
            return $cached_permission;
        }
        $permissions=session()->get('permissions');
        $permission_filter="1=2";//All denied
        if( isset($permissions[$permited_class_name]) ){
            $permission_filter=$this->permitWhereCompose($permissions[$permited_class_name],$right);
        }
        session()->set($permission_name,$permission_filter);
        return $permission_filter;
    }
    
    private function permitWhereCompose($modelPerm,$right){
        $owner_has=str_contains($modelPerm['owner'],$right);
        $ally_has=str_contains($modelPerm['ally'],$right);
        $other_has=str_contains($modelPerm['owner'],$right);
        if( $owner_has && $ally_has && $other_has ){
            $permission_filter="";//All granted
        } else
        if( !$owner_has && !$ally_has && !$other_has ){
            $permission_filter="0";//All denied
        } else
        if( $owner_has && $ally_has ){//!$other_has
            $permission_filter="(owner_id='$user_id' OR '$user_id' IN(owner_ally_ids))";
        } else
        if( $other_has ){
            $permission_filter="owner_id<>'$user_id' AND '$user_id' NOT IN(owner_ally_ids)";
        } else
        if( $owner_has && $other_has ){//!$ally_has
            $permission_filter="'$user_id' NOT IN(owner_ally_ids)";
        } else
        if( $ally_has ){
            $permission_filter="'$user_id' IN(owner_ally_ids)";
        } else
        if( $ally_has && $other_has ){//!$owner_has
            $permission_filter="owner_id<>'$user_id'";
        } else
        if( $owner_has ){
            $permission_filter="owner_id='$user_id'";
        }
        return $permission_filter;
    }
}