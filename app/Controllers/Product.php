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
        if( $ProductModel->errors() ){
            return $this->failValidationError(json_encode($StoreModel->errors()));
        }
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
        $product=[
            'store_id'=>$store_id,
            'product_name'=>$this->request->getVar('product_name'),
            'product_price'=>$this->request->getVar('product_price')
        ];
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemCreate($product);
        if( $ProductModel->errors() ){
            return $this->failValidationError(json_encode($ProductModel->errors()));
        }
        if( $result==1 ){
            return $this->respondCreated();
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $product_id=$this->request->getVar('product_id');
        $field_name=$this->request->getVar('name');
        $field_value=$this->request->getVar('value');
        $ProductModel=model('ProductModel');
        $ok=$ProductModel->itemUpdate(['product_id'=>$product_id,$field_name=>$field_value]);
        if( $ok ){
            return $this->respondUpdated(1);
        }
        if( $ProductModel->errors() ){
            return $this->failValidationError(json_encode($ProductModel->errors()));
        }
        return $this->fail(0);
    }
    
    public function itemDelete(){
        $product_id=$this->request->getVar('product_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemDelete($product_id);
        if( $ProductModel->errors() ){
            return $this->failValidationError(json_encode($ProductModel->errors()));
        }
        if( $result==1 ){
            return $this->respondDeleted();
        }
        return $this->fail($result);
    }
    
}
