<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Order extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        $store_id=$this->request->getVar('store_id');
        $entry_list_json=$this->request->getVar('entry_list');
        $entry_list=[];
        if($entry_list_json){
            $entry_list=json_decode($entry_list_json);
        }
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemCreate($entry_list,$store_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $OrderModel->errors() ){
            return $this->failValidationErrors( $OrderModel->errors() );
        }
        return $this->respond($result);
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
