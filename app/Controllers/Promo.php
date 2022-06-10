<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Promo extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        $user_id=$this->request->getVar('user_id');
        $PromoModel=model('PromoModel');
        $result=$PromoModel->listGet($user_id);
        if($result=='notfound'){
            return $this->failNotFound('notfound');
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
