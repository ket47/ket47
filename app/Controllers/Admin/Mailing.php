<?php

namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;

class Mailing extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $mailing_id=$this->request->getPost('mailing_id');
        if( !$mailing_id ){
            return $this->fail('noid');
        }
        $MailingModel=model('MailingModel');
        $result=$MailingModel->itemGet($mailing_id);
        if(is_object($result)){
            return $this->respond($result);
        }
        return $this->fail($result);
    }
    
    public function itemCreate(){
        $title_template=$this->request->getPost('subject_template');
        $mailing=[
            'subject_template'=>$title_template,
            'ringtone'=>'default',
            'transport'=>'push'
        ];
        $MailingModel=model('MailingModel');
        $result=$MailingModel->itemCreate($mailing);
        if(is_numeric($result)){
            return $this->respond($result);
        }
        return $this->fail($result);
    }
    public function itemCopy(){
        $mailing_id=$this->request->getPost('mailing_id');
        if( !$mailing_id ){
            return $this->fail('noid');
        }
        $MailingModel=model('MailingModel');
        
        $result=$MailingModel->itemCopy($mailing_id);

        if(is_numeric($result)){
            return $this->respond($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $mailing=$this->request->getJSON();
        $MailingModel=model('MailingModel');
        $result=$MailingModel->itemUpdate($mailing);

        // $MailingMessageModel=model('MailingMessageModel');
        // $MailingMessageModel->listDelete($mailing->mailing_id);
        // $MailingMessageModel->listRecieverFill($mailing->mailing_id);

        if($result=='ok'){
            return $this->respond($result);
        }
        return $this->fail($result);
    }
    
    public function itemDelete(){
        $mailing_id=$this->request->getPost('mailing_id');
        $MailingModel=model('MailingModel');
        $result=$MailingModel->itemDelete($mailing_id);
        if($result=='ok'){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }

    public function itemStart(){
        $mailing_id=$this->request->getPost('mailing_id');
        $mode=$this->request->getPost('mode');

        $MailingModel=model('MailingModel');
        $mailing=$MailingModel->itemGet($mailing_id);
        if( !$mailing || $mailing=='forbidden' ){
            return $this->failNotFound('notfound');
        }
        $MailingModel->itemStart($mailing_id);
        $MailingMessageModel=model('MailingMessageModel');

        $MailingMessageModel->where('mailing_id',$mailing_id);
        if( $mode!='restart' ){
            $MailingMessageModel->where('is_sent',0);
            $MailingMessageModel->where('is_failed',0);            
        }
        //$MailingMessageModel->select('reciever_id');
        $ids=$MailingMessageModel->findColumn('reciever_id');
        if(!$ids){
            return $this->fail('empty_recievers');
        }
        //$ids=explode(',',$row[0]->reciever_ids);
        $result = $MailingModel->itemJobCreate($ids, $mailing);
        if($result=='ok'){
            return $this->respond($result);
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
        ];
        $MailingModel=model('MailingModel');
        $result=$MailingModel->listGet($filter);
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

    public function recieverListCreate(){
        $mailing_id=$this->request->getPost('mailing_id');
        if( !$mailing_id ){
            return $this->fail('noid');
        }
        $MailingMessageModel=model('MailingMessageModel');
        $MailingMessageModel->listDelete($mailing_id);
        $result=$MailingMessageModel->listRecieverFill($mailing_id);
        if(is_numeric($result)){
            return $this->respond($result);
        }
        return $this->fail($result);
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


    private function fileSaveImage($image_holder_id, $file) {
        $image_data = [
            'image_holder' => 'mailing',
            'image_holder_id' => $image_holder_id
        ];
        $MailingModel = model('MailingModel');
        $image_hash = $MailingModel->imageCreate($image_data);
        if (!$image_hash) {
            return $this->failForbidden('forbidden');
        }
        if ($image_hash === 'limit_exeeded') {
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH . 'images/', $image_hash . '.webp');

        return \Config\Services::image()
                        ->withFile(WRITEPATH . 'images/' . $image_hash . '.webp')
                        ->resize(1024, 1024, true, 'height')
                        ->convert(IMAGETYPE_WEBP)
                        ->save();
    }

    public function imageDelete() {
        $image_id = $this->request->getPost('image_id');

        $MailingModel = model('MailingModel');
        $result = $MailingModel->imageDelete($image_id);
        if ($result === 'ok') {
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }

    public function imageOrder(){
        $image_id=$this->request->getPost('image_id');
        $dir=$this->request->getPost('dir');
        
        $MailingModel=model('MailingModel');
        $result=$MailingModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }


    public function locationCreate(){
        $location_holder_id=$this->request->getPost('location_holder_id');
        $location_group_id=$this->request->getPost('location_group_id');
        $location_group_type=$this->request->getPost('location_group_type');
        $location_longitude=$this->request->getPost('location_longitude');
        $location_latitude=$this->request->getPost('location_latitude');
        $location_address=$this->request->getPost('location_address');

        $data=[
            'location_holder'=>'mailing',
            'location_holder_id'=>$location_holder_id,
            'location_group_id'=>$location_group_id,
            'location_group_type'=>$location_group_type,
            'location_longitude'=>$location_longitude,
            'location_latitude'=>$location_latitude,
            'location_address'=>$location_address,
            'is_disabled'=>0,
            //'owner_id'=>$location_holder_id   get userIds of store
        ];
        $LocationModel=model('LocationModel');
        if( !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $result= $LocationModel->itemCreate($data,1);
        if( $LocationModel->errors() ){
            return $this->failValidationErrors(json_encode($LocationModel->errors()));
        }
        return $this->respondCreated($result);
    }
    
    public function locationDelete(){
        if( !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $location_id=$this->request->getPost('location_id');
        $LocationModel=model('LocationModel');

        $result=$LocationModel->itemDelete($location_id);
        if( $result=='ok' ){
            return $this->respondDeleted('ok');
        }
        return $this->fail($result);
    }
}
