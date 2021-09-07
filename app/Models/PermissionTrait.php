<?php

trait PermissionTrait{
    
    public function userIsOwner( $item_id ){
        $user_role= $this->userRole($item_id);
        return $user_role=='owner'?1:0;
    }
    
    public function userIsAlly( $item_id ){
        $user_role= $this->userRole($item_id);
        return $user_role=='ally'?1:0;        
    }
    
    public function userIsOther( $item_id ){
        $user_role= $this->userRole($item_id);
        return $user_role=='other'?1:0;
    }
    
    private $roleCache=[];
    private function userRole($item_id){
        if( !isset($this->roleCache[$item_id]) ){
            $session=session();
            $is_admin=$session->get('is_admin');
            if( $is_admin ){
                return $this->roleCache[$item_id]='owner';
            }
            $user_id=$session->get('user_id')??0;
            $sql="
                SELECT
                    IF(owner_id=$user_id
                        ,'owner',
                    IF('$user_id' IN(owner_ally_ids)
                        ,'ally'
                        ,'other'
                    )) user_role
                FROM
                    $this->table
                WHERE
                    $this->primaryKey='$item_id'
                ";
            $this->roleCache[$item_id]=$this->query($sql)->getRow('user_role');            
        }
        return $this->roleCache[$item_id];
    }
    
}