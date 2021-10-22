<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Order extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        $store_id=$this->request->getJsonVar('store_id');
        $entry_list=$this->request->getJsonVar('entry_list');


        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemCreate($entry_list,$store_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='limit_exeeded' ){
            return $this->failResourceExists($result);
        }
        if( $OrderModel->errors() ){
            return $this->failValidationErrors(json_encode($OrderModel->errors()));
        }
        return $this->respond($result);
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
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
