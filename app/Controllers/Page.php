<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Page extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $page_id=$this->request->getVar('page_id');
        $page_name=$this->request->getVar('page_name');

        $PageModel=model('PageModel');
        $result = $PageModel->itemGet($page_id,$page_name);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $data = $this->request->getJSON();
        if(!$data){
            return $this->fail('malformed_request');
        }
        $PageModel=model('PageModel');
        $result = $PageModel->itemCreate($data);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->respondCreated($result);
    }
    
    public function itemUpdate(){
        $data = $this->request->getJSON();
        if(!$data){
            return $this->fail('malformed_request');
        }
        $PageModel=model('PageModel');
        $result = $PageModel->itemUpdate($data);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->respondUpdated($result);
    }
    
    public function itemDelete(){
        $page_id=$this->request->getVar('page_id');
        $page_name=$this->request->getVar('page_name');

        $PageModel=model('PageModel');
        $result = $PageModel->itemDelete($page_id,$page_name);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        return $this->respondDeleted($result);
    }
    
    public function listGet(){
        $PageModel=model('PageModel');
        $result = $PageModel->listGet();
        return $this->respond($result);
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
