<?php
namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Group extends BaseController {
    use ResponseTrait;
    
    public function itemGet(){
        $group_table=$this->request->getVar('group_table');
        $group_id=$this->request->getVar('group_id');
        
        $GroupModel=$this->model('GroupModel');
        $GroupModel->tableSet($group_table);
        $result=$GroupModel->itemGet( $group_id );
        if( is_object($result) ){
            return $this->respond($result);
        }
        return $this->fail($result);
    }
    
    public function itemCreate(){
        $group_table=$this->request->getVar('group_table');
        $group_name=$this->request->getVar('group_name');
        $group_type=$this->request->getVar('group_type');
        $group_parent_id=$this->request->getVar('group_parent_id');
        $GroupModel=$this->model('GroupModel');
        $GroupModel->tableSet($group_table);
        $result=$GroupModel->itemCreate( $group_parent_id, $group_name, $group_type);
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        return $this->fail($result);
    }
    
    public function itemUpdate(){
        $group_table=$this->request->getVar('group_table');
        $group_id=$this->request->getVar('group_id');
        $field=$this->request->getVar('field');
        $value=$this->request->getVar('value');
        
        $GroupModel=$this->model('GroupModel');
        $GroupModel->tableSet($group_table);
        $result=$GroupModel->itemUpdate( $group_id, $field, $value);
        if( is_numeric($result) ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);        
    }
    
    public function itemDelete(){
        $group_table=$this->request->getVar('group_table');
        $group_id=$this->request->getVar('group_id');
        
        $GroupModel=$this->model('GroupModel');
        $GroupModel->tableSet($group_table);
        $result=$GroupModel->itemDelete( $group_id );
        if( is_numeric($result) ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);        
    }
    
    
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit')
        ];
        
    }
    
}