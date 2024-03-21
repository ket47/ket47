<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class DeliveryJob extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        $user_id=session()->get('user_id');
        if( !courdo() && !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $CourierModel=model('CourierModel');
        $courier_id=$CourierModel->where('owner_id',$user_id)->select('courier_id')->get()->getRow('courier_id');
        if( !$courier_id ){
            return $this->failForbidden('forbidden');
        }
        

        $DeliveryJobModel=model('DeliveryJobModel');
        $deliveryJobs=$DeliveryJobModel->listGet();
        return $this->respond($deliveryJobs);
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