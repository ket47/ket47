<?php

namespace App\Controllers\Admin;

class Permission extends \App\Controllers\BaseController {
   
    public function index(){
        $this->permission_table();
    }
    
    public function permission_table(){
        if( !sudo() ){
            die("Access denied");
        }
        $method_list=$this->getMethodList();
        $PermissionModel=model('PermissionModel');
        $permission_list= $PermissionModel->listGet();
        $data=[
            'permission_list'=>$permission_list,
            'method_list'=>$method_list,
            'permission_role_list'=>[
                'owner',
                'ally',
                'other'
            ],
            'permission_right_list'=>[
                'r',
                'w'
            ]
        ];
        echo  view('admin/permission_table',$data);
    }
    
    private function getMethodList(){
        $method_list=[];
        $model_files = glob(__DIR__ .'/../../Models/*Model.php');
        foreach($model_files as $filename){
            preg_match('/(\w+).php/', $filename, $classes);
            $modelName=$classes[1];
            //$method_list[$modelName][]="list";"Models\\".
            $method_list[$modelName][]="item";
            $method_list[$modelName][]="disabled";
        }
        return $method_list;
    }
    
    public function permissionSave(){
        $permited_owner=$this->request->getVar('permited_owner');
        $permited_class=$this->request->getVar('permited_class');
        $permited_method=$this->request->getVar('permited_method');
        $permited_rights=$this->request->getVar('permited_rights');
        $PermissionModel=model('PermissionModel');
        return $PermissionModel->itemCreate($permited_owner,$permited_class,$permited_method,$permited_rights);
    }
}