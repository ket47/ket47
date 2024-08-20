<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Promo extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        if( !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $owner_id=$this->request->getPost('owner_id');
        $promo_name=$this->request->getPost('promo_name');
        $promo_share=$this->request->getPost('promo_share');
        $promo_value=$this->request->getPost('promo_value');
        $promo_lifetime=$this->request->getPost('promo_lifetime');
        $PromoModel=model('PromoModel');
        $PromoModel->setLifetime($promo_lifetime);
        $PromoModel->setShare($promo_share);
        $result=$PromoModel->itemCreate($owner_id,$promo_value,$promo_name, null);
        return $this->respond($result);
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }

    public function itemLink(){
        $promo_id=$this->request->getVar('promo_id');
        $order_id=$this->request->getVar('order_id');
        $PromoModel=model('PromoModel');
        $result=$PromoModel->itemLink($order_id,$promo_id);
        if($result=='forbidden'){
            return $this->failForbidden('forbidden');
        }
        return $this->respond($result);
    }

    public function itemLinkGet(){
        $order_id=$this->request->getVar('order_id');
        $PromoModel=model('PromoModel');
        $result=$PromoModel->itemLinkGet($order_id);
        return $this->respond($result);
    }


    
    public function listGet(){
        $user_id=$this->request->getVar('user_id');
        $type=$this->request->getVar('type');
        $mode=$this->request->getVar('mode');
        $PromoModel=model('PromoModel');
        $result=$PromoModel->listGet($user_id,$type,$mode);
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
