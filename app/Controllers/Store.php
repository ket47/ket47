<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Store extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit')
        ];
        $StoreModel=model('StoreModel');
        $store_list=$StoreModel->listGet($filter);
        
        
        
        q($StoreModel);
        
        
        if( $StoreModel->errors() ){
            return $this->failValidationError(json_encode($StoreModel->errors()));
        }
        return $this->respond($store_list);
    }
    
    public function itemCreate(){
        $name=$this->request->getVar('name');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemCreate($name);
        if( $result ){
            return $this->respondCreated($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $data= json_decode($this->request->getVar('data'));
        
        $StoreModel=model('StoreModel');
        $ok=$StoreModel->itemUpdate($data);
        if( $ok ){
            return $this->respondUpdated('item_update_ok');
        }
        if( $StoreModel->errors() ){
            return $this->failValidationError(json_encode($StoreModel->errors()));
        }
        return $this->fail('item_update_error');
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
