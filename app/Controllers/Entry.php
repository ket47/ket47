<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Entry extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        $order_id=$this->request->getVar('order_id');
        $product_id=$this->request->getVar('product_id');
        $product_quantity=$this->request->getVar('product_quantity');
        $EntryModel=model('EntryModel');
        $result=$EntryModel->itemCreate($order_id,$product_id,$product_quantity);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='nostore' ){
            return $this->fail($result);
        }
        if( $EntryModel->errors() ){
            return $this->failValidationErrors( $EntryModel->errors() );
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
