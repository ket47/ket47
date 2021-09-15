<?php

namespace App\Controllers;

class Home extends BaseController {

    public function index() {
        if( $this->session->get('user_id') ) {
            return view('home/dashboard');
        }
        return view('user/signin_form');
    }
    
    public function product_manager(){
        return view('product/product_manager');
    }
    
    public function product_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'limit'=>$this->request->getVar('limit')
        ];
        $ProductModel=model('ProductModel');
        $ProductGroupModel=model('ProductGroupModel');
        $product_list=$ProductModel->listGet($filter);
        $product_group_list=$ProductGroupModel->listGet();
        return view('product/product_list', [
            'product_list' => $product_list,
            'product_group_list'=>$product_group_list
            ]);
    }
    
    
    
    
    
    
    
    public function store_manager(){
        return view('store/store_manager');
    }
    public function store_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'limit'=>$this->request->getVar('limit')
        ];
        $StoreModel=model('StoreModel');
        $StoreGroupModel=model('StoreGroupModel');
        $store_list=$StoreModel->listGet($filter);
        $store_group_list=$StoreGroupModel->listGet();
        return view('store/store_list', [
            'store_list' => $store_list,
            'store_group_list'=>$store_group_list
                ]);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function user_login_form() {
        if( $this->session->get('user_id') ) {
            return "SIGNED IN";
        }
        return view('user/signin_form');
    }
    
    public function user_data(){
        if( $this->session->get('user_id') ) {
            $user_data = (array) $this->session->get('user_data');
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
            'limit'=>$this->request->getVar('limit')
        ];
        $UserModel=model('UserModel');
        $UserGroupModel=model('UserGroupModel');
        $user_list=$UserModel->listGet($filter);
        $user_group_list=$UserGroupModel->listGet();
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
