<?php
namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;

class GroupManager extends \App\Controllers\BaseController {
    use ResponseTrait;
    
//    public function itemGet(){
//        $group_table=$this->request->getVar('group_table');
//        $group_id=$this->request->getVar('group_id');
//        
//        $GroupModel=$this->model('GroupModel');
//        $GroupModel->tableSet($group_table);
//        $result=$GroupModel->itemGet( $group_id );
//        if( is_object($result) ){
//            return $this->respond($result);
//        }
//        return $this->fail($result);
//    }
//    
    public function itemCreate(){
        $group_table=$this->request->getVar('group_table');
        $group_name=$this->request->getVar('group_name');
        $group_parent_id=$this->request->getVar('group_parent_id');
        
        if($group_table=='product_group_list'){
            $GroupModel=model('ProductGroupModel');
        } else if($group_table=='store_group_list'){
            $GroupModel=model('StoreGroupModel');
        } else if($group_table=='user_group_list'){
            $GroupModel=model('UserGroupModel');
        }
        $result=$GroupModel->itemCreate( $group_parent_id, $group_name, '');
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        return $this->fail($result);
    }
//    
//    public function itemUpdate(){
//        return $this->failResourceExists();
//    }
//    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        if($data->group_table=='product_group_list'){
            $GroupModel=model('ProductGroupModel');
        } else if($data->group_table=='store_group_list'){
            $GroupModel=model('StoreGroupModel');
        } else if($data->group_table=='user_group_list'){
            $GroupModel=model('UserGroupModel');
        }
        $result=$GroupModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $GroupModel->errors() ){
            return $this->failValidationError(json_encode($GroupModel->errors()));
        }
        return $this->respondUpdated($result);     
    }
    
    public function itemDelete(){
        $group_id=$this->request->getVar('group_id');
        $group_table=$this->request->getVar('group_table');
        if($group_table=='product_group_list'){
            $GroupModel=model('ProductGroupModel');
        } else if($group_table=='store_group_list'){
            $GroupModel=model('StoreGroupModel');
        } else if($group_table=='user_group_list'){
            $GroupModel=model('UserGroupModel');
        }
        $result=$GroupModel->itemDelete($group_id);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    
    
    public function index(){
        if( !sudo() ){
            die('Access denied!');
        }
        $ProductGroupModel=model('ProductGroupModel');
        $StoreGroupModel=model('StoreGroupModel');
        $UserGroupModel=model('UserGroupModel');
        
        $tables=[];
        $tables[]=(object)[
                'name'=>'Product groups',
                'type'=>'product',
                'entries'=>$ProductGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'Store groups',
                'type'=>'store',
                'entries'=>$StoreGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'User groups',
                'type'=>'user',
                'entries'=>$UserGroupModel->listGet()
                ];
        return view('admin/group_manager.php',['tables'=>$tables]);
    }
    
    public function listGet(){
        $group_table=$this->request->getVar('group_table');
        $GroupModel=model('GroupModel');
        $GroupModel->tableSet($group_table);
        $group_list=$GroupModel->listGet($filter);
        if( $GroupModel->errors() ){
            return $this->failValidationError(json_encode($GroupModel->errors()));
        }
        return $this->respond($group_list);
    }
    
    
    
}