<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Courier extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $courier_id=$this->request->getVar('courier_id');
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemGet($courier_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        helper('phone_number');
        $courier_phone_number= clearPhone($this->request->getVar('courier_name'));//didn't cahnged default name for field
        $UserModel=model('UserModel');
        $user_id=$UserModel->where('user_phone',$courier_phone_number)->get()->getRow('user_id');

        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemCreate($user_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        if( $CourierModel->errors() ){
            return $this->failValidationErrors(json_encode($CourierModel->errors()));
        }
        return $this->respond($result);
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $CourierModel->errors() ){
            return $this->failValidationErrors(json_encode($CourierModel->errors()));
        }
        return $this->respondUpdated($result);
    }
    
    public function itemUpdateGroup(){
        $courier_id=$this->request->getVar('courier_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemUpdateGroup($courier_id,$group_id,$is_joined);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemDelete(){
        $courier_id=$this->request->getVar('courier_id');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemDelete($courier_id);        
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }
    
    public function itemUnDelete(){
        $courier_id=$this->request->getVar('courier_id');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemUnDelete($courier_id);        
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }
    
    public function itemDisable(){
        $courier_id=$this->request->getVar('courier_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemDisable($courier_id,$is_disabled);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }

    
    
    
    
    
    
    
    
    
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
        ];
        $CourierModel=model('CourierModel');
        $courier_list=$CourierModel->listGet($filter);
        if( $CourierModel->errors() ){
            return $this->failValidationErrors(json_encode($CourierModel->errors()));
        }
        return $this->respond($courier_list);
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
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload(){
        $image_holder_id=$this->request->getVar('image_holder_id');
        $items = $this->request->getFiles();
        if(!$items){
            return $this->failResourceGone('no_files_uploaded');
        }
        foreach($items['files'] as $file){
            $type = $file->getClientMimeType();
            if(!str_contains($type, 'image')){
                continue;
            }
            if ($file->isValid() && ! $file->hasMoved()) {
                $result=$this->fileSaveImage($image_holder_id,$file);
                if( $result!==true ){
                    return $result;
                }
            }
        }
        return $this->respondCreated('ok');
    }
    
    private function fileSaveImage( $image_holder_id, $file ){
        $image_data=[
            'image_holder'=>'courier',
            'image_holder_id'=>$image_holder_id
        ];
        $CourierModel=model('CourierModel');
        $image_hash=$CourierModel->imageCreate($image_data);
        if( !$image_hash ){
            return $this->failForbidden('forbidden');
        }
        if( $image_hash === 'limit_exeeded' ){
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH.'images/', $image_hash.'.webp');
        
        return \Config\Services::image()
        ->withFile(WRITEPATH.'images/'.$image_hash.'.webp')
        ->resize(1024, 1024, true, 'height')
        ->convert(IMAGETYPE_WEBP)
        ->save();
    }
    
    public function imageDisable(){
        $image_id=$this->request->getVar('image_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->imageDisable( $image_id, $is_disabled );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    
    public function imageDelete(){
        $image_id=$this->request->getVar('image_id');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->imageDelete( $image_id );
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    
    public function imageOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
 
}
