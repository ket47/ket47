<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Store extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'limit'=>$this->request->getVar('limit')
        ];
        $StoreModel=model('StoreModel');
        $store_list=$StoreModel->listGet($filter);
        if( $StoreModel->errors() ){
            return $this->failValidationError(json_encode($StoreModel->errors()));
        }
        return $this->respond($store_list);
    }
    
    public function itemCreate(){
        $name=$this->request->getVar('name');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemCreate($name);
        if( $result=='ok' ){
            return $this->respondCreated();
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $store_id=$this->request->getVar('store_id');
        $field_name=$this->request->getVar('name');
        $field_value=$this->request->getVar('value');
        $StoreModel=model('StoreModel');
        $ok=$StoreModel->itemUpdate($store_id,[$field_name=>$field_value]);
        if( $ok ){
            return $this->respondUpdated(1);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationError(json_encode($StoreModel->errors()));
        }
        return $this->fail(0);
    }
    
    public function itemDelete(){
        $store_id=$this->request->getVar('store_id');
        $StoreModel=model('StoreModel');
        $ok=$StoreModel->itemDelete($store_id);
        if( $StoreModel->errors() ){
            return $this->failValidationError(json_encode($StoreModel->errors()));
        }
        return $this->respondDeleted($ok);        
    }
}
