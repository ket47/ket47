<?php

namespace App\Controllers\Admin;

class Permission extends \App\Controllers\BaseController {
    
    public function __construct() {
        
    }
    
    public function index(){
        $this->permission_table();
    }
    
    public function permission_table(){
        $method_list=$this->getMethodList();
        $PermissionModel=model('PermissionModel');
        $user_group_list= $PermissionModel->getUserGroups();
        $data=[
            'user_group_list'=>$user_group_list,
            'method_list'=>$method_list
        ];
        echo  view('admin/permission_table',$data);
    }
    
    private function getMethodList(){
        $method_list=[];
        $model_files = glob(__DIR__ .'/../../Models/*.php');
        foreach($model_files as $filename){
            $content= file_get_contents($filename);
            preg_match_all('/public\s+function\s+(\w+)/',$content,$methods);
            preg_match('/(\w+).php/', $filename, $classes);
            $modelName=$classes[1];
            foreach($methods[1] as $method){
                $method_list[$modelName][]="$method";
            }
        }
        return $method_list;
    }
    
    public function permissionSave(){
        $permited_group=$this->request->getVar('permited_group');
        $permited_class=$this->request->getVar('permited_class');
        $permited_method=$this->request->getVar('permited_method');
        $is_enabled=$this->request->getVar('is_enabled');
        
        $PermissionModel=model('PermissionModel');
        return $PermissionModel->permissionSave($permited_group,$permited_class,$permited_method,$is_enabled);
    }
}