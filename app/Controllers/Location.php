<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Location extends \App\Controllers\BaseController{

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
    
    public function pickerModal(){
        $loc_longitude=$this->request->getVar('longitude');
        $loc_altitude=$this->request->getVar('altitude');
        
        $data=[
            'loc_longitude'=>$loc_longitude,
            'loc_altitude'=>$loc_altitude
        ];
        
        return view('common/location_picker');
    }
    
    public function pickerTest(){
        return view('common/location_picker_test');
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
