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
        $post=(object)[
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

    public function itemCreatedGet(){
        $user_id=session()->get('user_id');
        $PostModel=model('PostModel');
        $PostModel->where('is_published',0);
        $PostModel->permitWhere('w');//get only owned post
        $PostModel->where('owner_id',$user_id);
        $result=$PostModel->select('post_id')->get()->getRow('post_id');
        if( is_numeric($result) ){
            return $this->respond($result);
        }
        return $this->itemCreate();
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        if( !$data ){
            return $this->fail('empty');
        }
        $PostModel=model('PostModel');
        $result=$PostModel->itemUpdate($data);
        if( in_array($result,['ok']) ){
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
        if( in_array($result,['ok','idle']) ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function itemPublish(){
        $post_id=$this->request->getPost('post_id');
        $PostModel=model('PostModel');
        $result=$PostModel->itemPublish($post_id);
        if( in_array($result,['ok','idle']) ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function itemUnpublish(){
        $post_id=$this->request->getPost('post_id');
        $PostModel=model('PostModel');
        $result=$PostModel->itemUnpublish($post_id);
        if( in_array($result,['ok','idle']) ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }

    public function listGet(){
        if( session()->get('chameleonMode')=='on' ){
            return $this->failNotfound('notfound');
        }
        $filter=[
            'name_query'=>$this->request->getPost('name_query'),
            'name_query_fields'=>$this->request->getPost('name_query_fields'),
            'is_disabled'=>$this->request->getPost('is_disabled'),
            'is_deleted'=>$this->request->getPost('is_deleted'),
            'is_active'=>$this->request->getPost('is_active'),
            'is_actual'=>$this->request->getPost('is_actual'),
            'is_promoted'=>$this->request->getPost('is_promoted'),
            'offset'=>$this->request->getPost('offset'),
            'limit'=>$this->request->getPost('limit'),
            'post_holder_id'=>$this->request->getPost('post_holder_id'),
            'post_holder'=>$this->request->getPost('post_holder'),
            'post_type'=>$this->request->getPost('post_type'),
            'reverse'=>$this->request->getPost('reverse'),
        ];

        if($filter['post_type']=='homeslide'){
            //tmpfix
            $filter['post_type']='slide';
            $filter['is_promoted']=1;
        }
        $PostModel=model('PostModel');
        $posts=$PostModel->listGet($filter);
        foreach($posts as $post){
            $post->meta=$this->itemMetaGet($post);
        }
        return $this->respond([
            'post_list'=>$posts
        ]);
    }

    private function itemMetaGet( $post ){
        if( $post->post_holder=='store' ){
            $StoreModel=model('StoreModel');
            $StoreModel->where('store_id',$post->post_holder_id);
            $StoreModel->join('image_list il1',"image_holder='store_avatar' AND image_holder_id='{$post->post_holder_id}'");
            $StoreModel->select('store_name holder_name,il1.image_hash avatar_hash');
            return $StoreModel->get()->getRow();
        }
        return null;
    } 

    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload(){
        $image_holder_id=$this->request->getPost('image_holder_id');
        $post_type=$this->request->getPost('post_type');
        if ( !(int) $image_holder_id ) {
            return $this->fail('no_holder_id');
        }
        if( $post_type=='slide' ){
            $image_height=700;
            $image_width=1920;
        } else {
            $image_height=1920;
            $image_width=1080;
            $post_type='story';
        }
        $PostModel=model('PostModel');
        $PostModel->itemUpdate((object)['post_id'=>$image_holder_id,'post_type'=>$post_type]);

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
                $result=$this->fileSaveImage($image_holder_id,$file,$image_width,$image_height);
                
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

    
    private function fileSaveImage( $image_holder_id, $file, $image_width, $image_height ){
        $image_data=[
            'image_holder'=>'post',
            'image_holder_id'=>$image_holder_id
        ];
        $PostModel=model('PostModel');
        $image_hash=$PostModel->imageCreate($image_data,1);
        if( !$image_hash ){
            return 'forbidden';
        }
        if( $image_hash === 'limit_exeeded' ){
            return 'limit_exeeded';
        }
        $file->move(WRITEPATH.'images/', $image_hash.'.webp');
        
        return \Config\Services::image()
        ->withFile(WRITEPATH.'images/'.$image_hash.'.webp')
        ->fit($image_width, $image_height,'center')
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
