<?php

namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;

class Tariff extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $tariff_id=$this->request->getVar('tariff_id');
        $TariffModel=model('TariffModel');
        $result=$TariffModel->itemGet($tariff_id);
        if($result=='forbidden'){
            return $this->failForbidden($result);
        }
        if($result=='noid'){
            return $this->fail($result);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $data=$this->request->getJSON();
        if(!$data){
            return $this->fail('nodata');
        }
        $TariffModel=model('TariffModel');
        $result=$TariffModel->itemCreate($data);
        if($result=='forbidden'){
            return $this->failForbidden($result);
        }
        return $this->respondCreated($result);
    }
    
    public function itemUpdate(){
        $data=$this->request->getJSON();
        $TariffModel=model('TariffModel');
        $result=$TariffModel->itemUpdate($data);
        if($result=='forbidden'){
            return $this->failForbidden($result);
        }
        if($result=='noid'){
            return $this->fail($result);
        }
        return $this->respondUpdated($result);
    }
    
    public function itemDelete(){
        $tariff_id=$this->request->getVar('tariff_id');
        $TariffModel=model('TariffModel');
        $result=$TariffModel->itemDelete($tariff_id);
        if($result==='forbidden'){
            return $this->failForbidden($result);
        }
        return $this->respondDeleted($result);
    }
    
    public function listGet(){
        $TariffModel=model('TariffModel');
        $result=$TariffModel->listGet();
        if( $result=='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->respond($result);
    }

    public function storeTariffListGet(){
        $store_id=$this->request->getVar('store_id');
        $TariffMemberModel=model('TariffMemberModel');
        $result=$TariffMemberModel->listGet(null,$store_id);
        if( !$result ){
            return $this->failNotFound();
        }
        return $this->respond($result);
    }

    public function storeTariffAdd(){
        $tariff_id=$this->request->getVar('tariff_id');
        $store_id=$this->request->getVar('store_id');
        $start_at=$this->request->getVar('start_at');
        $finish_at=$this->request->getVar('finish_at');
        
        $TariffMemberModel=model('TariffMemberModel');
        $result=$TariffMemberModel->itemCreate($tariff_id,$store_id,$start_at,$finish_at);
        if( $result=='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->respond($result);
    }

    public function storeTariffDelete(){
        $tariff_id=$this->request->getVar('tariff_id');
        $store_id=$this->request->getVar('store_id');
        $TariffMemberModel=model('TariffMemberModel');
        $result=$TariffMemberModel->itemDelete($tariff_id,$store_id);
        if( $result=='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->respond($result);
    }

}
