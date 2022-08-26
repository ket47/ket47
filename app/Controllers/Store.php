<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Store extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
            'owner_id'=>$this->request->getVar('owner_id'),
            'owner_ally_ids'=>$this->request->getVar('owner_ally_ids'),
            'order'=>$this->request->getVar('order'),
            'reverse'=>$this->request->getVar('reverse'),
        ];
        $StoreModel=model('StoreModel');
        $store_list=$StoreModel->listGet($filter);
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respond($store_list);
    }
    public function listGroupGet(){
        $StoreGroupModel=model('StoreGroupModel');
        $result=$StoreGroupModel->listGet();
        return $this->respond($result);
    }

    public function listNearGet(){
        $location_id=$this->request->getVar('location_id');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->listNearGet(['location_id'=>$location_id]);
        if( !is_array($result) ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    public function primaryNearGet(){
        $location_id=$this->request->getVar('location_id');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->primaryNearGet(['location_id'=>$location_id]);
        if( $result=='not_found' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet(){
        $store_id=(int) $this->request->getVar('store_id');
        $mode=$this->request->getVar('mode');
        $distance_include=$this->request->getVar('distance_include');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemGet($store_id,$mode??'all',$distance_include);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }

    public function itemIsReady(){
        $store_id=$this->request->getVar('store_id');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemIsReady($store_id);
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $name=$this->request->getVar('name');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemCreate($name);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='limit_exeeded' ){
            return $this->failResourceExists($result);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respond($result);
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respondUpdated($result);
    }
    
    public function itemUpdateGroup(){
        $store_id=$this->request->getVar('store_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemUpdateGroup($store_id,$group_id,$is_joined);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemDelete(){
        $store_id=$this->request->getVar('store_id');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemDelete($store_id);        
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }
    
    public function itemUnDelete(){
        $store_id=$this->request->getVar('store_id');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemUnDelete($store_id);        
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }
    
    public function itemDisable(){
        $store_id=$this->request->getVar('store_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemDisable($store_id,$is_disabled);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    
    public function fieldApprove(){
        $store_id=$this->request->getVar('store_id');
        $field_name=$this->request->getVar('field_name');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->fieldApprove( $store_id, $field_name );
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respondUpdated($result);
    }

    /////////////////////////////////////////////////////
    //OWNER HANDLING SECTION
    /////////////////////////////////////////////////////
    public function ownerListGet(){
        $store_id=$this->request->getVar('store_id');

        $StoreModel=model('StoreModel');
        $result=$StoreModel->ownerListGet( $store_id );
        if( $result==='unauthorized' ){
            return $this->failUnauthorized($result);
        }
        if( $result==='nostore' ){
            return $this->fail($result);
        }
        return $this->respond($result);
    }
    public function ownerSave(){
        $store_id=$this->request->getVar('store_id');
        $action=$this->request->getVar('action');
        $owner_id=$this->request->getVar('owner_id');
        $owner_phone=$this->request->getVar('owner_phone');

        $StoreModel=model('StoreModel');
        $result=$StoreModel->ownerSave( $action, $store_id, $owner_id, $owner_phone );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='unauthorized' ){
            return $this->failUnauthorized($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
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
                $result=$this->fileSaveImage($image_holder_id,$file);                if( $result!==true ){
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
            'image_holder'=>'store',
            'image_holder_id'=>$image_holder_id
        ];
        $StoreModel=model('StoreModel');
        $image_hash=$StoreModel->imageCreate($image_data);
        if( !$image_hash ){
            return $this->failForbidden('forbidden');
        }
        if( $image_hash === 'limit_exeeded' ){
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH.'images/', $image_hash.'.webp');
        
        try{
            return \Config\Services::image()
            ->withFile(WRITEPATH.'images/'.$image_hash.'.webp')
            ->resize(1024, 1024, true, 'height')
            ->convert(IMAGETYPE_WEBP)
            ->save();
        }catch(\Exception $e){
            return $e->getMessage();
        }
    }
    
    public function imageDisable(){
        $image_id=$this->request->getVar('image_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->imageDisable( $image_id, $is_disabled );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    
    public function imageDelete(){
        $image_id=$this->request->getVar('image_id');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->imageDelete( $image_id );
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    
    public function imageOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    public function locationCreate(){
        $location_holder_id=$this->request->getVar('location_holder_id');
        $location_group_id=$this->request->getVar('location_group_id');
        $location_group_type=$this->request->getVar('location_group_type');
        $location_longitude=$this->request->getVar('location_longitude');
        $location_latitude=$this->request->getVar('location_latitude');
        $location_address=$this->request->getVar('location_address');

        $data=[
            'location_holder'=>'store',
            'location_holder_id'=>$location_holder_id,
            'location_group_id'=>$location_group_id,
            'location_group_type'=>$location_group_type,
            'location_longitude'=>$location_longitude,
            'location_latitude'=>$location_latitude,
            'location_address'=>$location_address,
            'is_disabled'=>0,
            //'owner_id'=>$location_holder_id   get userIds of store
        ];
        $StoreModel=model('StoreModel');
        $LocationModel=model('LocationModel');
        if( !$StoreModel->permit($location_holder_id,'w') ){
            return $this->failForbidden('forbidden');
        }
        $result= $LocationModel->itemCreate($data,1);
        if( $LocationModel->errors() ){
            return $this->failValidationErrors(json_encode($LocationModel->errors()));
        }
        return $this->respondCreated($result);
    }
    
    public function locationDelete(){
        $location_id=$this->request->getVar('location_id');
        $LocationModel=model('LocationModel');
        $result=$LocationModel->itemDelete($location_id);
        if( $result=='ok' ){
            return $this->respondDeleted('ok');
        }
        return $this->fail($result);
    }
}
