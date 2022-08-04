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
        if( $result==='forbidden_at_this_stage' ){
            return $this->failForbidden($result);
        }
        if( $EntryModel->errors() ){
            return $this->failValidationErrors( $EntryModel->errors() );
        }
        $EntryModel->listSumUpdate($order_id);
        return $this->respond($result);
    }
    
    public function itemUpdate(){
        $entry=$this->request->getJSON();
        $EntryModel=model('EntryModel');
        $result=$EntryModel->itemUpdate($entry);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='forbidden_at_this_stage' ){
            return $this->failForbidden($result);
        }
        if( $result==='noentryid' ){
            return $this->fail($result);
        }
        if( $EntryModel->errors() ){
            return $this->failValidationErrors( $EntryModel->errors() );
        }
        if( $result=='ok' && isset($entry->entry_quantity)){
            $EntryModel->listSumUpdate( $entry->order_id );
        }
        return $this->respond($result);
    }
    
    public function itemDelete(){
        $entry_id=$this->request->getVar('entry_id');
        $EntryModel=model('EntryModel');
        $result=$EntryModel->itemDelete($entry_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='forbidden_at_this_stage' ){
            return $this->failForbidden($result);
        }
        return $this->respond($result);
    }
    
    public function itemUnDelete(){
        $entry_id=$this->request->getVar('entry_id');
        $EntryModel=model('EntryModel');
        $result=$EntryModel->itemUnDelete($entry_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='forbidden_at_this_stage' ){
            return $this->failForbidden($result);
        }
        return $this->respond($result);
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
