<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Location extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){

    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function pickerModal(){
        return view('common/location_picker');
    }
    
    
    public function distanceGet(){
        $start_location_id=$this->request->getVar('start_location_id');
        $finish_location_id=$this->request->getVar('finish_location_id');
        
        $LocationModel=model('LocationModel');
        $distance=$LocationModel->distanceGet($start_location_id,$finish_location_id);
        return $this->respond($distance);
    }
    public function distanceListGet(){
        $center_location_id =$this->request->getVar('center_location_id');
        $point_distance=$this->request->getVar('point_distance');
        $point_holder=$this->request->getVar('point_holder');
        
        $LocationModel=model('LocationModel');
        $distance=$LocationModel->distanceListGet($center_location_id, $point_distance, $point_holder);
        
        //q($LocationModel);
        
        return $this->respond($distance);
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
