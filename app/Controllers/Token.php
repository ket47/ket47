<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Token extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $token_id=$this->request->getVar('token_id');
        $token_hash=$this->request->getVar('token_hash');

        $TokenModel=model('TokenModel');
        $result = $TokenModel->itemGet($token_id,$token_hash);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }

    public function itemActiveGet(){
        $owner_id=$this->request->getVar('owner_id');
        $token_holder=$this->request->getVar('token_holder');

        $TokenModel=model('TokenModel');
        $result = $TokenModel->itemActiveGet($owner_id,$token_holder);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        if(!$result){
            $TokenModel->itemCreate($owner_id,$token_holder);
            $result=$TokenModel->itemActiveGet($owner_id,$token_holder);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $owner_id=$this->request->getVar('owner_id');
        $token_holder=$this->request->getVar('token_holder');

        if(!$owner_id){
            $owner_id=session()->get('user_id');
        }
        $TokenModel=model('TokenModel');
        $result = $TokenModel->itemCreate($owner_id,$token_holder);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->respond($result);
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        $token_id=$this->request->getVar('token_id');
        $token_hash=$this->request->getVar('token_hash');

        $TokenModel=model('TokenModel');
        $result = $TokenModel->itemDelete($token_id,$token_hash);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function listGet(){
        $token_holder=$this->request->getVar('token_holder');
        $TokenModel=model('TokenModel');
        $result = $TokenModel->listGet($token_holder);
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
 
}
