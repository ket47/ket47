<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class PromoCode extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $promo_code_id=$this->request->getPost('promo_code_id');
        $PromoCodeModel=model('PromoCodeModel');
        $result=$PromoCodeModel->itemGet($promo_code_id);
        if( $result=='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        if( !sudo() ){
            return $this->failForbidden();
        }
        $item['promo_code']=$this->request->getPost('promo_code');
        $item['promo_sum']=$this->request->getPost('promo_sum');
        $item['promo_description']=$this->request->getPost('promo_description');
        $PromoCodeModel=model('PromoCodeModel');
        $result=$PromoCodeModel->itemCreate($item);
        return $this->respond($result);
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        $PromoCodeModel=model('PromoCodeModel');
        $result=$PromoCodeModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $PromoCodeModel->errors() ){
            return $this->failValidationErrors(json_encode($PromoCodeModel->errors()));
        }
        return $this->respondUpdated($result);
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        if( !sudo() ){
            return $this->failNotFound();
        }
        $PromoCodeModel=model('PromoCodeModel');
        $result=$PromoCodeModel->listGet();
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
