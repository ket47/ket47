<?php
namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;

class PrefManager extends \App\Controllers\BaseController {
    use ResponseTrait;

    public function itemCreate( $pref_name=null ){
        if( !$pref_name ){
            $pref_name= $this->request->getVar('pref_name');
        }

        $PrefModel=model('PrefModel');
        $result=$PrefModel->itemCreate($pref_name);
        if( $result==='ok' ){
            return $this->respondCreated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        
        $PrefModel=model('PrefModel');
        $result=$PrefModel->itemUpdate($data);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }

    public function itemSave(){
        $data= $this->request->getJSON();
        
        $PrefModel=model('PrefModel');
        $PrefModel->itemCreate($data->pref_name);
        return $this->itemUpdate($data);
    }

    
    public function itemDelete(){
        $pref_name=$this->request->getVar('pref_name');
        $PrefModel=model('PrefModel');
        $result=$PrefModel->itemDelete($pref_name);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }

    public function locationDefaultSave(){
        $location_latitude=$this->request->getVar('location_latitude');
        $location_longitude=$this->request->getVar('location_longitude');
        $location_address=$this->request->getVar('location_address');

        $LocationModel=model('LocationModel');
        $LocationGroupModel=model('LocationGroupModel');
        $default_location=$LocationModel->itemMainGet('default_location','-1');
        if( $default_location ){
            $LocationModel->itemDelete($default_location->location_id);
        }
        $group=$LocationGroupModel->itemGet(null,'location_current');
        $data=[
            'location_holder'=>'default_location',
            'location_holder_id'=>'-1',
            'location_latitude'=>$location_latitude,
            'location_longitude'=>$location_longitude,
            'location_address'=>$location_address,
            'location_group_id'=>$group->group_id
        ];
        return $LocationModel->itemCreate($data,1);
    }

    public function locationDefaultDelete(){
        $LocationModel=model('LocationModel');
        $default_location=$LocationModel->itemMainGet('default_location','-1');
        if( $default_location ){
            $LocationModel->itemDelete($default_location->location_id);
            return $this->respondDeleted();
        }
        return $this->failNotFound();
    }

    
    public function index(){
        if( !sudo() ){
            die('Access denied!');
        }
        $PrefModel=model('PrefModel');
        $LocationModel=model('LocationModel');
        $pref_list=$PrefModel->listGet();
        $default_location=$LocationModel->itemMainGet('default_location','-1');
        return view('admin/preferences.php',[
            'pref_list'=>$pref_list,
            'default_location'=>$default_location
        ]);
    }
}