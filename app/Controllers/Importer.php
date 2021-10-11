<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Importer extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        
        return false;
    }
    
    public function itemUpdate(){
        $data=$this->request->getJSON();
        $ImporterModel=model('ImporterModel');
        $result=$ImporterModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $ImporterModel->errors() ){
            return $this->failValidationError(json_encode($ImporterModel->errors()));
        }
        return $this->respondUpdated($result);
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'limit'=>$this->request->getVar('limit'),
            'offset'=>$this->request->getVar('offset'),
        ];
        $ImporterModel=model('ImporterModel');
        $rows=$ImporterModel->listGet($filter);
        return $this->respond($rows);
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        $ids=$this->request->getVar('ids');
        $ImporterModel=model('ImporterModel');
        $result=$ImporterModel->listDelete($ids);
        return $this->respondDeleted($result);
    }
    
    public function listAnalyse(){
        $target=$this->request->getJsonVar('target');
        $holder_id=$this->request->getJsonVar('holder_id');
        $colconfig=$this->request->getJsonVar('columns');
        $ImporterModel=model('ImporterModel');
        $result=$ImporterModel->listAnalyse($colconfig,$target,$holder_id);
        if( $result==='no_required_fields' ){
            return $this->respond(null,204,'Not all required fields are defined');
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $ImporterModel->errors() ){
            return $this->failValidationError(json_encode($ImporterModel->errors()));
        }
        return $this->respond($result);
    }
    
    public function fileUpload(){
        $target=$this->request->getVar('target');
        $holder=$this->request->getVar('holder');
        $holder_id=$this->request->getVar('holder_id');
        $items =$this->request->getFiles();
        if(!$items){
            return $this->failResourceGone('no_files_uploaded');
        }
        foreach($items['files'] as $file){
            if ($file->isValid() && ! $file->hasMoved()) {
                $result=$this->fileParse( $file,$holder,$holder_id,$target );
                if( $result!==true ){
                    return $result;
                }
            }
        }
        return $this->respondCreated('ok');
    }
    
    private function fileParse( $file,$holder,$holder_id,$target ){
        helper('text');
        $tmp_dir=sys_get_temp_dir();
        $tmp_name=random_string('alnum').'.xlsx';
        $file->move($tmp_dir, $tmp_name);
        $xlsx = \App\Libraries\SimpleXLSX::parse("$tmp_dir/$tmp_name");
        if ( !$xlsx ) {
             SimpleXLSX::parseError();
        }
        $rows=$xlsx->rows();
        $ImporterModel=model('ImporterModel');
        
        foreach ($rows as $item){
            $ImporterModel->itemCreate( $item, $holder, $holder_id, $target );
        }
        return true;
    }
}
