<?php

namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;

class Mailing extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        $mailing_id=$this->request->getPost('mailing_id');
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
    
    public function itemUpdate(){
        $mailing=$this->request->getJSON();
        $MailingModel=model('MailingModel');
        $result=$MailingModel->itemUpdate($mailing);
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
        $MailingModel=model('MailingModel');
        $result=$MailingModel->itemStart($mailing_id);
        
        $MailingModel=model('MailingModel');
        $mailing=$MailingModel->itemGet($mailing_id);
        if(!$mailing){
            return $this->failNotFound('notfound');
        }

        $MailingMessageModel=model('MailingMessageModel');
        $MailingMessageModel->where('willsend_at<NOW()');
        $MailingMessageModel->where('is_sent',0);
        $messages=$MailingMessageModel->listGet($mailing_id);
        if( !is_array($messages) || !count($messages) ){
            return $this->failNotFound('notfound');
        }

        $all_messages=[];
        foreach($messages as $message){
            $all_messages[]=(object)[
                'message_subject'=>$mailing->subject_template,
                'message_text'=>$mailing->text_template,
                'message_transport'=>$mailing->transport,
                'message_reciever_id'=>$message->reciever_id,
                'message_data'=>(object)[
                    'link'=>$mailing->link,
                    'image'=>$mailing->image??'',
                    'sound'=>$mailing->sound??''
                ]
            ];
            //should set is_sent=1
        }

        $batched_messages=array_chunk($all_messages,10);
        foreach($batched_messages as $batch){
            $mailing_task=[
                'task_name'=>"send mailing",
                'task_priority'=>'low',
                'task_programm'=>[
                        ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$batch]]
                    ]
            ];
            jobCreate($mailing_task);
        }



        
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
        $image_id = $this->request->getVar('image_id');

        $MailingModel = model('MailingModel');
        $result = $MailingModel->imageDelete($image_id);
        if ($result === 'ok') {
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }

    public function imageOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $MailingModel=model('MailingModel');
        $result=$MailingModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
}
