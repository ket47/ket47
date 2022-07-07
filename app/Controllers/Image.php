<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Image extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }

    public function itemUpdateOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $ImageModel=model('ImageModel');
        $result=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $result ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    
    public function itemDelete() {
        $image_id = $this->request->getVar('image_id');

        $ImageModel = model('ImageModel');
        $result = $ImageModel->itemDelete($image_id);
        if ($result === 'ok') {
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }

    public function itemUnDelete() {
        $image_id = $this->request->getVar('image_id');

        $ImageModel = model('ImageModel');
        $result = $ImageModel->itemUnDelete($image_id);
        if ($result === 'ok') {
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function itemDisable(){
        $image_id=$this->request->getVar('image_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $ImageModel=model('ImageModel');
        $result=$ImageModel->itemDisable($image_id,$is_disabled);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function listGet() {
        $filter=[
            'image_holder'=>$this->request->getVar('image_holder'),
            'image_holder_id'=>$this->request->getVar('image_holder_id'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
            'offset'=>$this->request->getVar('offset'),
            'order'=>$this->request->getVar('order'),
        ];
        $ImageModel=model('ImageModel');
        $image_list=$ImageModel->listGet($filter);
        return $this->respond($image_list);
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

    public function listPurge(){
        $ImageModel=model('ImageModel');
        $ImageModel->listPurge();
    }
}
