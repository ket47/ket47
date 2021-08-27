<?php

namespace App\Controllers;

class User extends BaseController {

    public function index() {
        return $this->login_form();
    }
    
    public function login_form(){
        $this->signIn();
        if( $this->isSignedIn() ){
            $user_id=$this->session->get('user_id');
            $UserModel=model('UserModel');
            $user_data=$UserModel->get($user_id);
            return view('user/signedin',$user_data);
        }
        return view('user/signin_form');
    }
    
    public function register_form(){
        return view('user/register_form');
    }
    
    private function isSignedIn(){
        return $this->session->get('user_id');
    }    
    
    public function signUp() {
        $user_phone=$this->request->getVar('user_phone');
        $user_name=$this->request->getVar('user_name');
        $user_pass=$this->request->getVar('user_pass');
        $user_pass_confirm=$this->request->getVar('user_pass_confirm');
        
        $user_phone_cleared= '7'.substr(preg_replace('/[^\d]/', '', $user_phone),-10);
        $UserModel=model('UserModel');
        $UserModel->signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm);
        
        if( $UserModel->errors() ){
            $this->error(422,json_encode($UserModel->errors()),implode($UserModel->errors()));
            return false;
        }
        return true;
    }
    
    public function signIn(){
        $user_phone=$this->request->getVar('user_phone');
        $user_pass=$this->request->getVar('user_pass');
        if( !$user_phone || !$user_pass ){
            return false;
        }
        $this->signOut();
        
        $user_phone_cleared= '7'.substr(preg_replace('/[^\d]/', '', $user_phone),-10);
        $UserModel=model('UserModel');
        $user_id=$UserModel->signIn($user_phone,$user_phone_cleared);
        if( $user_id ){
            $this->session->set('user_id',$user_id);
            return true;
        }
        return false;
    }
    public function signOut(){
        $user_id=$this->session->get('user_id');
        $UserModel=model('UserModel');
        $UserModel->signOut($user_id);
        $this->session->destroy();
        return true;
    }
    
    public function resetPass(){
        $user_phone=$this->request->getVar('user_phone');
        $user_email=$this->request->getVar('user_email');
        $UserModel=model('UserModel');
        helper('generateHash');
        $ok=$UserModel->passCheckRecoveryPhone($user_phone);
        if( $ok ){
            $data=[
                'new_pass'=>generateHash()
            ];
            $Sms=library('DevinoSms');
            $Sms->send($user_phone,view('user/passResetSms.php',$data));
            return true;
        }
        
        $ok=$UserModel->passCheckRecoveryEmail($user_phone);
        if( $ok ){
            $data=[
                'new_pass'=>generateHash()
            ];
            $Sms=library('Email');
            $Sms->send($user_email,view('user/passResetEmail.php',$data));
            return true;
        }
        return false;
    }
    
    public function confirmPhoneSend(){
        $user_id=$this->session->get('user_id');
        $UserModel=model('UserModel');
        $user_data=$UserModel->get($user_id);
        if( !$user_data ){
            $this->error(404,'User not found');
        }
        
        helper('generateHash');
        $confirm_value=generateHash(4,'numeric');
        $UserConfirmationModel=model('UserConfirmationModel');
        $data=[
            'user_id'=>$user_id,
            'confirm_type'=>'phone',
            'confirm_value'=>$confirm_value
        ];
        $UserConfirmationModel->insert($data);
        $UserModel->setUnconfirmed($user_id);
        
        $data=[
            'confirm_value'=>$confirm_value
        ];
        $Sms=library('DevinoSms');
        $Sms->send($user_phone,view('user/userPhoneConfirmSms.php',$data));
    }
    public function confirmPhoneCheck(){
        $confirm_value=$this->request->getVar('confirm_value');
        $user_id=$this->session->get('user_id');
        $UserConfirmationModel=model('UserConfirmationModel');
        return $UserConfirmationModel->confirmPhone($user_id,$confirm_value);
    }
    
    
    
    
    
    
    
    
    

    public function get(){
        $user_id=$this->request->getVar('user_id');
        permit('userGet');
    }
    
    public function update(){
        $user_id=$this->request->getVar('user_id');
        $user_name=$this->request->getVar('user_name');
        $user_pass=$this->request->getVar('user_pass');
        $user_phone=$this->request->getVar('user_phone');
        $user_email=$this->request->getVar('user_email');
        
        permit('userUpdate');
    }
}
