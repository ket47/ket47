<?php
namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;

class PrefManager extends \App\Controllers\BaseController {
    use ResponseTrait;

    public function itemCreate(){
        $pref_name= $this->request->getVar('pref_name');
        
        $PrefModel=model('PrefModel');
        $result=$PrefModel->itemCreate($pref_name);
        if( $result==='ok' ){
            return $this->respondCreated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        
        $PrefModel=model('PrefModel');
        $result=$PrefModel->itemUpdate($data);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemDelete(){
        $pref_name=$this->request->getVar('pref_name');
        $PrefModel=model('PrefModel');
        $result=$PrefModel->itemDelete($pref_name);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function index(){
        if( !sudo() ){
            die('Access denied!');
        }
        $PrefModel=model('PrefModel');
        $pref_list=$PrefModel->listGet();
        return view('admin/preferences.php',['pref_list'=>$pref_list]);
    }
}