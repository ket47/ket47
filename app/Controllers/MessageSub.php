<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Messagesub extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }

    public function itemCreate(){
        $registration_id=$this->request->getVar('registration_id');
        $type=$this->request->getVar('type');
        $user_agent=$this->request->getVar('user_agent');
        $MessageSubModel=model('MessageSubModel');
        $result=$MessageSubModel->itemCreate($registration_id,$type,$user_agent);
        if( $result=='notauthorized' ){
            return $this->failUnauthorized('notauthorized');
        }
        return $this->respond($result);
    }

    public function listGet($user_id){
        $user_id=$this->request->getVar('user_id');
        $MessageSubModel=model('MessageSubModel');

        $result=$MessageSubModel->listGet($user_id);
        return $this->respond($result);
    }
}
