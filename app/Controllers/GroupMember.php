<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class GroupMember extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    
    public function itemUpdate(){
        $table=$this->request->getVar('table');
        $member_id=$this->request->getVar('member_id');
        $group_id=$this->request->getVar('group_id');
        $value=$this->request->getVar('value');
        
        $GroupMemberModel=model('GroupMemberModel');
        $GroupMemberModel->tableSet($table);
        $ok=$GroupMemberModel->itemUpdate( $member_id, $group_id, $value );

        if( $ok=='group_join_forbidden' ){
            return $this->failForbidden($ok);
        }
        if( $GroupMemberModel->errors() ){
            return $this->failValidationError(json_encode($GroupMemberModel->errors()));
        }
        return $this->respondUpdated($ok);
    }
}
