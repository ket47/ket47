<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Metric extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        $ua=$this->request->getUserAgent();
        $metricsHeader=(object)[];
        $metricsHeader->come_media_id=    $this->request->getPost('come_media_id');
        $metricsHeader->come_inviter_id=  $this->request->getPost('come_inviter_id');
        $metricsHeader->come_referrer=    $this->request->getPost('come_referrer');
        $metricsHeader->come_url=         $this->request->getPost('come_url');
        $metricsHeader->device_is_mobile= $ua->isMobile();
        $metricsHeader->device_platform=  $ua->getPlatform();

        $MetricModel=model('MetricModel');
        $metricsHeaderId=$MetricModel->itemSave($metricsHeader);
        if($metricsHeaderId??0){
            return $this->respondUpdated($metricsHeaderId);
        }
        return $this->fail(0);
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
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
 
}
