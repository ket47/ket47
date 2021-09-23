<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Image extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        $files = $this->request->getFiles();
        $holder=$this->request->getVar('image_holder');
        $holder_id=$this->request->getVar('image_holder_id');
        
        $ImageModel=model("ImageModel");
        
        
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet( $filter ){
        return [
            
        ];
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
