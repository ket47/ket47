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
        if( !$owner_id ){
            $owner_id=session()->get('user_id');
        }
        $token_holder=$this->request->getVar('token_holder');
        $token_holder_id=$this->request->getVar('token_holder_id');

        $TokenModel=model('TokenModel');
        $result = $TokenModel->itemActiveGet($owner_id,$token_holder,$token_holder_id);

        if(!$result){
            $agent = $this->request->getUserAgent();
            $token_device=$agent->getPlatform()." ".$agent->getBrowser()." ".$agent->getMobile();
            $new_token=$TokenModel->itemCreate($owner_id,$token_holder,$token_holder_id,$token_device);
            if( $new_token=='forbidden' ){
                return $this->failForbidden($new_token);
            }
            $result=$TokenModel->itemGet($new_token['token_id']);
            $result->token_hash_raw=$new_token['token_hash_raw'];
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $owner_id=$this->request->getVar('owner_id');
        $token_holder=$this->request->getVar('token_holder');
        $token_holder_id=$this->request->getVar('token_holder_id');

        if(!$owner_id){
            $owner_id=session()->get('user_id');
        }
        $TokenModel=model('TokenModel');
        $result = $TokenModel->itemCreate($owner_id,$token_holder,$token_holder_id);
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
