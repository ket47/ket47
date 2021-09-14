<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Product extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    
    
    public function listGet(){
        
    }
    
    public function listCreate(){
        $store_id=$this->request->getVar('store_id');
        $product_list=$this->request->getVar('product_list');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->listCreate($store_id,$product_list);
        if( $result=='ok' ){
            return $this->respondCreated();
        }
        return $this->fail($result);        
    }
    
    public function listUpdate(){
        
    }
    
    public function listDelete(){
        
    }



    
    public function itemGet(){
        
    }
    
    public function itemCreate(){
        $store_id=$this->request->getVar('store_id');
        $product_name=$this->request->getVar('product_name');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemCreate($store_id,$product_name);
        if( $result=='ok' ){
            return $this->respondCreated();
        }
        return $this->fail($result);        
        
    }
    
    public function itemUpdate(){
        
    }
    
    public function itemDelete(){
        
    }
    
}
