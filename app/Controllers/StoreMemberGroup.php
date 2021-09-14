<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class StoreMemberGroup extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    
    public function itemUpdate(){
        $store_id=$this->request->getVar('store_id');
        $store_group_id=$this->request->getVar('store_group_id');
        $value=$this->request->getVar('value');
        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $ok=$StoreGroupMemberModel->itemUpdate( $store_id, $store_group_id, $value );
        if( $StoreGroupMemberModel->errors() ){
            return $this->failValidationError(json_encode($StoreGroupMemberModel->errors()));
        }
        return $this->respondUpdated($ok);
    }
}
