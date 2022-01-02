<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Location extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        return false;//at specialized controllers
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
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
            'location_holder'=>$this->request->getVar('location_holder'),
            'location_holder_id'=>$this->request->getVar('location_holder_id'),
        ];
        $LocationModel=model('LocationModel');
        $location_list=$LocationModel->listGet($filter);
        return $this->respond($location_list);
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
