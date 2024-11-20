<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Post extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $post_id = (int) $this->request->getPost('post_id');
        $PostModel=model('PostModel');
        $result=$PostModel->itemGet($post_id);
        if( $result=='notfound' ){
            return $this->failNotFound();
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        if( !sudo() ){
            return $this->failForbidden();
        }
        $post=[
            'post_title'=>$this->request->getPost('post_title'),
            'post_description'=>$this->request->getPost('post_description'),
            'post_type'=>$this->request->getPost('post_type'),
        ];
        $PostModel=model('PostModel');
        $result=$PostModel->itemCreate($post);
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        if( !sudo() ){
            return $this->failForbidden();
        }
        $data= $this->request->getJSON();
        if( !$data ){
            return $this->fail('empty');
        }
        $PostModel=model('PostModel');
        $result=$PostModel->itemUpdate($data);
        if( $result==='ok' ){
            return $this->respondUpdated('ok');
        }
        if( $PostModel->errors() ){
            return $this->failValidationErrors(json_encode($PostModel->errors()));
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }

    public function itemDelete(){
        $post_id=$this->request->getPost('post_id');
        $PostModel=model('PostModel');
        $result=$PostModel->itemDelete($post_id);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemUnDelete(){
        $post_id=$this->request->getPost('post_id');
        $PostModel=model('PostModel');
        $result=$PostModel->itemUnDelete($post_id);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }

    public function itemDisable(){
        $post_id=$this->request->getPost('post_id');
        $is_disabled=$this->request->getPost('is_disabled');
        
        $PostModel=model('PostModel');
        $result=$PostModel->itemDisable($post_id,$is_disabled);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getPost('name_query'),
            'name_query_fields'=>$this->request->getPost('name_query_fields'),
            'is_disabled'=>$this->request->getPost('is_disabled'),
            'is_deleted'=>$this->request->getPost('is_deleted'),
            'is_active'=>$this->request->getPost('is_active'),
            'offset'=>$this->request->getPost('offset'),
            'limit'=>$this->request->getPost('limit'),
            'store_id'=>$this->request->getPost('store_id'),
            'post_type'=>$this->request->getPost('group_id'),
            'reverse'=>$this->request->getPost('reverse'),
        ];

        $PostModel=model('PostModel');
        $data['post_list']=$PostModel->listGet($filter);
        return $this->respond($data);
    }

    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload(){
        $image_holder_id=$this->request->getPost('image_holder_id');
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

                pl($result);
                if( $result!==true ){
                    return $this->fail($result);
                }
            }
        }
        if($result===true){
            return $this->respondCreated('ok');
        }
        return $this->fail('no_valid_images');
    }

    
    private function fileSaveImage( $image_holder_id, $file ){
        $image_data=[
            'image_holder'=>'post',
            'image_holder_id'=>$image_holder_id
        ];
        $PostModel=model('PostModel');
        $image_hash=$PostModel->imageCreate($image_data,1);
        if( !$image_hash ){
            return $this->failForbidden('forbidden');
        }
        if( $image_hash === 'limit_exeeded' ){
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH.'images/', $image_hash.'.webp');
        
        return \Config\Services::image()
        ->withFile(WRITEPATH.'images/'.$image_hash.'.webp')
        ->resize(3200, 3200, true, 'height')
        ->convert(IMAGETYPE_WEBP)
        ->save();
    }
    
    public function imageDisable(){
        $image_id=$this->request->getPost('image_id');
        $is_disabled=$this->request->getPost('is_disabled');
        
        $PostModel=model('PostModel');
        $result=$PostModel->imageDisable( $image_id, $is_disabled );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    
    public function imageDelete(){
        $image_id=$this->request->getPost('image_id');
        
        $PostModel=model('PostModel');
        $result=$PostModel->imageDelete( $image_id );
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    
    public function imageOrder(){
        $image_id=$this->request->getPost('image_id');
        $dir=$this->request->getPost('dir');
        
        $PostModel=model('PostModel');
        $result=$PostModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

}
