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

        $metrics=(object)[];
        $metrics->come_media_id=    $this->request->getPost('come_media_id');
        $metrics->come_inviter_id=  $this->request->getPost('come_inviter_id');
        $metrics->come_referrer=    $this->request->getPost('come_referrer');
        $metrics->come_url=         $this->request->getPost('come_url');
        $metrics->device_is_mobile= $ua->isMobile();
        $metrics->device_platform=  $ua->getPlatform();

        $MetricModel=model('MetricModel');
        $result=$MetricModel->itemCreate( $metrics );
        return $this->respondCreated($result);
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
