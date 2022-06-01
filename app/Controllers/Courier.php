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
        $phone_raw=$this->request->getVar('courier_name');//didn't cahnged default name for field
        if($phone_raw){
            helper('phone_number');
            $courier_phone_number= clearPhone($phone_raw);
            $UserModel=model('UserModel');
            $user_id=$UserModel->where('user_phone',$courier_phone_number)->get()->getRow('user_id');
        } else {
            $user_id=session()->get('user_id');
        }


        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemCreate($user_id);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        if( $result==='exists' ){
            return $this->fail($result,409);
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

    public function itemUpdateStatus(){//similar to above function
        $courier_id=$this->request->getVar('courier_id');
        $group_type=$this->request->getVar('group_type');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemUpdateStatus($courier_id,$group_type);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notactive' ){
            return $this->fail($result,409);
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

    public function itemPurge(){
        $courier_id=$this->request->getVar('courier_id');
        
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemPurge($courier_id);        
        if( $result==='ok' ){
            return $this->respondDeleted($result);
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

    
    
    
    
    
    
    public function listJobGet(){
        $courier_id=$this->request->getVar('courier_id');
        $CourierModel=model('CourierModel');
        $result=$CourierModel->listJobGet($courier_id);
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        if( $result==='notready' ){
            return $this->fail($result);
        }
        return $this->respond($result);
    }

    public function itemJobGet(){
        $order_id=$this->request->getVar('order_id');
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemJobGet($order_id);
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        if( $result==='notready' ){
            return $this->fail($result);
        }
        return $this->respond($result);
    }

    public function itemJobStart(){
        $order_id=$this->request->getVar('order_id');
        $courier_id=$this->request->getVar('courier_id');
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemJobStart($order_id,$courier_id);
        if( $result==='ok' ){
            return $this->respond($result);
        }
        return $this->fail($result);
    }

    public function itemJobTrack(){
        $order_id=$this->request->getVar('order_id');
        $CourierModel=model('CourierModel');
        $result=$CourierModel->itemJobTrack($order_id);
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        if( $result==='notready' ){
            return $this->fail($result);
        }
        return $this->respond($result);
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
        if ( !(int) $image_holder_id ) {
            return $this->fail('no_holder_id');
        }
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
    

    private function imagePurgeCurrent($courier_id){
        $CourierModel=model('CourierModel');
        $courier=$CourierModel->itemGet($courier_id);
        $courierImageId=$courier->images[0]->image_id??0;
        if($courierImageId){
            $CourierModel->imageDelete($courierImageId);
        }
    }

    private function fileSaveImage( $image_holder_id, $file ){
        $image_data=[
            'image_holder'=>'courier',
            'image_holder_id'=>$image_holder_id
        ];
        $this->imagePurgeCurrent($image_holder_id);

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

    public function locationAdd(){
        $location_holder_id=$this->request->getVar('location_holder_id');
        $location_longitude=$this->request->getVar('location_longitude');
        $location_latitude=$this->request->getVar('location_latitude');
        $location_address=$this->request->getVar('location_address');
        
        $data=[
            'location_holder'   =>'courier',
            'location_holder_id'=>$location_holder_id,
            'location_longitude'=>$location_longitude,
            'location_latitude' =>$location_latitude,
            'location_address'  =>$location_address
        ];
        $CourierModel=model('CourierModel');
        $LocationModel=model('LocationModel');
        if( !$CourierModel->permit($location_holder_id,'w') ){
            return $this->failForbidden('forbidden');
        }
        $result= $LocationModel->itemAdd($data);
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
        return $this->fail('idle');
    }
}
