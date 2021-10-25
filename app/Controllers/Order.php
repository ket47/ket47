<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Order extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $order_id=$this->request->getVar('order_id');
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemGet($order_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $order_store_id=$this->request->getVar('order_store_id');
        $entry_list_json=$this->request->getVar('entry_list');
        $entry_list=[];
        if($entry_list_json){
            $entry_list=json_decode($entry_list_json);
        }
        $OrderModel=model('OrderModel');
        $result=$OrderModel->itemCreate($order_store_id,$entry_list);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='nostore' ){
            return $this->fail($result);
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
