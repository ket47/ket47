<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class UserMemberGroup extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    
    public function itemUpdate(){
        $user_id=$this->request->getVar('user_id');
        $user_group_id=$this->request->getVar('user_group_id');
        $value=$this->request->getVar('value');
        $UserGroupMemberModel=model('UserGroupMemberModel');
        $ok=$UserGroupMemberModel->itemUpdate( $user_id, $user_group_id, $value );
        if( $UserGroupMemberModel->errors() ){
            return $this->failValidationError(json_encode($UserGroupMemberModel->errors()));
        }
        return $this->respondUpdated($ok);
    }
}
