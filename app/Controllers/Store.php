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
        if( $StoreModel->errors() ){
            return $this->failValidationError(json_encode($StoreModel->errors()));
        }
        return $this->respond($store_list);
    }
    
    public function itemCreate(){
        $name=$this->request->getVar('name');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemCreate($name);
        if( is_numeric($result) ){
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
    
    public function itemGroupUpdate(){
        $store_id=$this->request->getVar('store_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemGroupUpdate($store_id,$group_id,$is_joined);
        if( $result ){
            return $this->respondUpdated('item_group_update_ok');
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
    
    public function itemUpdateGroup(){
        $store_id=$this->request->getVar('store_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemUpdateGroup($store_id,$group_id,$is_joined);
        
        if(is_bool($result) && $result ){
            return $this->respondUpdated(1);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationError(json_encode($UserModel->errors()));
        }
        return $this->fail($result);
    }
    
    public function itemDisable(){
        $store_id=$this->request->getVar('store_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemDisable($store_id,$is_disabled);
        
        if( is_bool($result) && $result ){
            return $this->respondUpdated(1);
        }
        return $this->fail($result);
    }
    
    
    public function fieldApprove(){
        $store_id=$this->request->getVar('store_id');
        $field_name=$this->request->getVar('field_name');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->fieldApprove( $store_id, $field_name );
        if( is_bool($result) && $result ){
            return $this->respondUpdated('field_approve_ok');
        }
        return $this->failForbidden('field_approve_error');
    }

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
                $newName = $file->getRandomName();
                $file->move(WRITEPATH.'uploads', $newName);
                $this->fileStoreImage($newName);
            }
        }
    }
    
    private function fileStoreImage( $file_name ){
        \Config\Services::image('imagick')
        ->withFile(WRITEPATH.'uploads/'.$file_name)
        ->resize(1024, 1024, true, 'height')
        ->save('/path/to/new/image.jpg');
        
        
        
        
        echo $file_name;
    }
}
