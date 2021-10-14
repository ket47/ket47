<?php

namespace App\Controllers;

class Home extends BaseController {

    public function index() {
        if( session()->get('user_id') ) {
            return view('home/dashboard');
        }
        return view('user/signin_form');
    }
    
    public function product_manager(){
        return view('product/product_manager');
    }
    
    public function product_importer(){
        return view('product/product_importer');
    }
    
    public function product_importer_rows(){
        
    }
    
    public function product_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
            'offset'=>$this->request->getVar('offset'),
            'store_id'=>$this->request->getVar('store_id'),
            'group_id'=>$this->request->getVar('group_id'),
        ];
        $ProductModel=model('ProductModel');
        $GroupModel=model('GroupModel');
        $product_list=$ProductModel->listGet($filter);
        //die($ProductModel->getLastQuery());
        $GroupModel->tableSet('product_group_list');
        $product_group_list=$GroupModel->listGet();
        $data=[
            'product_list' => $product_list,
            'product_group_list'=>$product_group_list
            ];
        return view('product/product_list', $data);
    }
    
    
    
    
    
    
    
    public function store_manager(){
        return view('store/store_manager');
    }
    public function store_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit')
        ];
        $StoreModel=model('StoreModel');
        $GroupModel=model('GroupModel');
        $GroupModel->tableSet('store_group_list');
        $store_list=$StoreModel->listGet($filter);
        $store_group_list=$GroupModel->listGet();
        return view('store/store_list', [
            'store_list' => $store_list,
            'store_group_list'=>$store_group_list
                ]);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function user_login_form() {
        if( session()->get('user_id')>0 ) {
            $user_data = (array) session()->get('user_data');
            return view('user/signed_userdata', ['user' => $user_data]);
        }
        return view('user/signin_form');
    }
    
    public function user_data(){
        if( session()->get('user_id') ) {
            $user_data = (array) session()->get('user_data');
            return view('user/signed_userdata', ['user' => $user_data]);
        } else {
            echo "NOT SIGNED IN";
        }        
    }
    
    public function user_manager(){
        return view('user/user_manager');
    }
    
    public function user_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit')
        ];
        $UserModel=model('UserModel');
        $GroupModel=model('GroupModel');
        $user_list=$UserModel->listGet($filter);
        $GroupModel->tableSet('user_group_list');
        $user_group_list=$GroupModel->listGet();
        return view('user/list', [
            'user_list' => $user_list,
            'user_group_list'=>$user_group_list
                ]); 
    }

    public function user_register_form() {
        return view('user/register_form');
    }

    public function user_phone_verification() {
        return view('user/phone_verification');
    }

    public function user_password_reset() {
        return view('user/password_reset');
    }

}
