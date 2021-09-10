<?php

namespace App\Controllers;

class Home extends BaseController {

    public function index() {
        return view('home/dashboard');
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
        return view('user/list_manager');
    }
    
    public function user_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
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
