<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Product extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    
    
    public function listGet(){
        
    }
    
    public function listCreate(){
//        $store_id=$this->request->getVar('store_id');
//        $product_list=$this->request->getVar('product_list');
//        $ProductModel=model('ProductModel');
//        $result=$ProductModel->listCreate($store_id,$product_list);
//        if( $ProductModel->errors() ){
//            return $this->failValidationError(json_encode($ProductModel->errors()));
//        }
//        if( $result=='ok' ){
//            return $this->respondCreated();
//        }
//        return $this->fail($result);        
    }
    
    public function listUpdate(){
        
    }
    
    public function listDelete(){
        
    }



    
    public function itemGet(){
        
    }
    
    public function itemCreate(){
        $store_id=$this->request->getVar('store_id');
        $product=[
            'store_id'=>$store_id,
            'product_name'=>$this->request->getVar('product_name'),
            'product_price'=>$this->request->getVar('product_price')
        ];
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemCreate($product);
        if( $ProductModel->errors() ){
            return $this->failValidationError(json_encode($ProductModel->errors()));
        }
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $data= json_decode($this->request->getVar('data'));
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemUpdate($data);
        if( $result==='item_update_ok' ){
            return $this->respondUpdated('item_update_ok');
        }
        if( $ProductModel->errors() ){
            return $this->failValidationError(json_encode($ProductModel->errors()));
        }
        return $this->fail($result);
    }
    
    public function itemUpdateGroup(){
        $store_id=$this->request->getVar('store_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemUpdateGroup($store_id,$group_id,$is_joined);
        
        if( is_bool($result) && $result ){
            return $this->respondUpdated('item_update_ok');
        }
        return $this->fail($result);
    }
    
    public function itemDelete(){
        $product_id=$this->request->getVar('product_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemDelete($product_id);
        if( $result==='item_delete_ok' ){
            return $this->respondUpdated('item_update_ok');
        }
        return $this->fail($result);
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
        return $this->respondCreated('file_upload_register_ok');
    }
    
    private function fileSaveImage( $image_holder_id, $file ){
        $image_data=[
            'image_holder'=>'product',
            'image_holder_id'=>$image_holder_id
        ];
        $ProductModel=model('ProductModel');
        $image_hash=$ProductModel->itemCreateImage($image_data);
        if( !$image_hash ){
            return $this->failForbidden('file_upload_register_forbidden');
        }
        if( $image_hash === 'image_create_limit_exeeded' ){
            return $this->fail('image_create_limit_exeeded');
        }
        $file->move(WRITEPATH.'images/', $image_hash.'.webp');
        
        return \Config\Services::image()
        ->withFile(WRITEPATH.'images/'.$image_hash.'.webp')
        ->resize(1024, 1024, true, 'height')
        ->convert(IMAGETYPE_WEBP)
        ->save();
    }
    
    public function imageApprove(){
        $image_id=$this->request->getVar('image_id');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->imageApprove( $image_id );
        if( $result==='image_approve_ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    
    public function imageDelete(){
        $image_id=$this->request->getVar('image_id');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->imageDelete( $image_id );
        if( $result==='image_delete_ok' ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    
    public function imageOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->imageOrder( $image_id, $dir );
        if( $result==='image_order_ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
}
