<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class UserCards extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemCreate(){
        
        return false;
    }
    
    public function itemMainSet(){
        $card_id=$this->request->getVar('card_id');
        $UserCardModel=model('UserCardModel');
        $result=$UserCardModel->itemMainSet($card_id);
        if($result=='notfound'){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function itemDelete(){
        $card_id=$this->request->getVar('card_id');
        $UserCardModel=model('UserCardModel');
        $result=$UserCardModel->itemDelete($card_id);
        if($result=='idle'){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function listGet(){
        $UserCardModel=model('UserCardModel');
        $result=$UserCardModel->listGet();
        if($result=='notfound'){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
 
}
