<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Product extends \App\Controllers\BaseController{
    use ResponseTrait;
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'offset'=>$this->request->getVar('offset'),
            'limit'=>$this->request->getVar('limit'),
            'store_id'=>$this->request->getVar('store_id'),
            'group_id'=>$this->request->getVar('group_id'),
            'order'=>$this->request->getVar('order'),
        ];



        if($filter['limit']==200){
            $filter['limit']=500;//tmp fix for storeview
        }




        $grouptree_include=$this->request->getVar('grouptree_include');
        $ProductModel=model('ProductModel');

        $data['product_list']=$ProductModel->listGet($filter);
        if( $grouptree_include ){
            $data['group_tree']=$ProductModel->groupTreeGet(['store_id'=>$filter['store_id'],'limit'=>50]);
        }
        return $this->respond($data);
    }

    public function listGroupGet(){
        $ProductGroupModel=model('ProductGroupModel');
        $ProductGroupModel->select("IF(group_parent_id=0,1,0) is_parent");
        $ProductGroupModel->orderBy('is_parent DESC',false);
        $ProductGroupModel->where('group_name<>""');
        $result=$ProductGroupModel->listGet();
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
    
    public function itemGet(){
        $product_id=$this->request->getVar('product_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemGet($product_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        if($result){
            $ReactionModel=model('ReactionModel');
            $result->reactionSummary=$ReactionModel->summaryGet("product:$product_id");
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $store_id=$this->request->getVar('store_id');
        $product=[
            'store_id'=>$store_id,
            'product_name'=>$this->request->getVar('product_name'),
            'product_price'=>$this->request->getVar('product_price'),
            'product_parent_id'=>$this->request->getVar('product_parent_id'),
            'product_option'=>$this->request->getVar('product_option'),
        ];
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemCreate($product);
        if( $ProductModel->errors() ){
            return $this->failValidationErrors(json_encode($ProductModel->errors()));
        }
        if( is_numeric($result) ){
            $ProductModel->listUpdateValidity(null,$result);
            return $this->respondCreated($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemUpdate($data);
        if( $result==='ok' ){
            $ProductModel->listUpdateValidity(null,$data->product_id);
            return $this->respondUpdated('ok');
        }
        if( $ProductModel->errors() ){
            return $this->failValidationErrors(json_encode($ProductModel->errors()));
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdateGroup(){
        $product_id=$this->request->getVar('product_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemUpdateGroup($product_id,$group_id,$is_joined);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemDelete(){
        $product_id=$this->request->getVar('product_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemDelete($product_id);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemUnDelete(){
        $product_id=$this->request->getVar('product_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemUnDelete($product_id);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemDisable(){
        $product_id=$this->request->getVar('product_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemDisable($product_id,$is_disabled);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function itemOptionGet(){
        $product_parent_id=$this->request->getVar('product_parent_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemOptionGet($product_parent_id);
        if( !$result ){
            return $this->failNotFound();
        }
        return $this->respond($result);
    }

    public function itemOptionSave(){
        $product_id=$this->request->getVar('product_id');
        $product_parent_id=$this->request->getVar('product_parent_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemOptionSave($product_id,$product_parent_id);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function itemOptionDelete(){
        $product_id=$this->request->getVar('product_id');
        $ProductModel=model('ProductModel');
        $result=$ProductModel->itemOptionDelete($product_id);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload(){
        $image_holder_id=$this->request->getVar('image_holder_id');
        if ( !(int) $image_holder_id ) {
            return $this->fail('no_holder_id');
        }
        $items = $this->request->getFiles();
        if(!$items){
            return $this->failResourceGone('no_files_uploaded');
        }
        $result=false;
        foreach($items['files'] as $file){
            $type = $file->getClientMimeType();
            if(!str_contains($type, 'image')){
                continue;
            }
            if ($file->isValid() && ! $file->hasMoved()) {
                $result=$this->fileSaveImage($image_holder_id,$file);
                if( $result!==true ){
                    return $this->fail($result);
                }
            }
        }
        if($result===true){
            model('ProductModel')->listUpdateValidity(null,$image_holder_id);
            return $this->respondCreated('ok');
        }
        return $this->fail('no_valid_images');
    }

    
    private function fileSaveImage( $image_holder_id, $file ){
        $image_data=[
            'image_holder'=>'product',
            'image_holder_id'=>$image_holder_id
        ];
        $ProductModel=model('ProductModel');
        $image_hash=$ProductModel->imageCreate($image_data);
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
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->imageDisable( $image_id, $is_disabled );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    
    public function imageDelete(){
        $image_id=$this->request->getVar('image_id');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->imageDelete( $image_id );
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    
    public function imageOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $ProductModel=model('ProductModel');
        $result=$ProductModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    /////////////////////////////////////////////////////
    //PRODUCT CATEGORIES SECTION
    /////////////////////////////////////////////////////
    public function groupTreeGet(){
        $filter=[
            'limit'=>'100',
            'store_id'=>$this->request->getVar('store_id'),
        ];
        $ProductModel=model('ProductModel');
        $group_list=$ProductModel->groupTreeGet($filter);
        return $this->respond($group_list);
    }
}
