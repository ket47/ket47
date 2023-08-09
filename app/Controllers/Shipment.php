<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Shipment extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $ship_id=$this->request->getPost('ship_id');
        $ShipmentModel=model('ShipmentModel');
        $result=$ShipmentModel->itemGet($ship_id);
        if( is_object($result) ){
            return $this->respond($result);
        }
        if( $result=='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->fail($result);
    }
    
    public function itemCreate(){
        $is_shopping=$this->request->getPost('is_shopping');
        $ShipmentModel=model('ShipmentModel');
        $result=$ShipmentModel->itemCreate($is_shopping?1:0);
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        if( $result=='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
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
