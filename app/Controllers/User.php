<?php

namespace App\Controllers;

class User extends BaseController {

    public function index() {
        return $this->login_form();
    }
    
    public function login_form(){
        $this->signIn();
        if( $this->isSignedIn() ){
            $user_data=(array) $this->session->get('user_data');
            return $this->respond(view('user/signedin',['user'=>$user_data]));
        }
        return $this->respond(view('user/signin_form'));
    }
    
    public function register_form(){
        return $this->respond(view('user/register_form'));
    }
    
    public function phone_verification(){
        return $this->respond(view('user/phone_verification'));
    }
    
    private function isSignedIn(){
        return $this->session->get('user_id');
    }
    
    
    
    
    
    /////////////////////////////////////////////
    //LOGIN SECTION
    /////////////////////////////////////////////
    public function signUp() {
        $user_phone=$this->request->getVar('user_phone');
        $user_name=$this->request->getVar('user_name');
        $user_pass=$this->request->getVar('user_pass');
        $user_pass_confirm=$this->request->getVar('user_pass_confirm');
        
        helper('phoneNumber');
        $user_phone_cleared= clearPhone($user_phone);
        $UserModel=model('UserModel');
        $UserModel->signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm);
        
        if( $UserModel->errors() ){
            return $this->failValidationError(json_encode($UserModel->errors()));
        }
        return $this->responseCreated();
    }
    
    public function signIn(){
        $user_phone=$this->request->getVar('user_phone');
        $user_pass=$this->request->getVar('user_pass');
        if( !$user_phone || !$user_pass ){
            return $this->failValidationError();
        }
        $this->signOut();
        
        $user_phone_cleared= '7'.substr(preg_replace('/[^\d]/', '', $user_phone),-10);
        $UserModel=model('UserModel');
        $result=$UserModel->signIn($user_phone_cleared,$user_pass);
        if( $result=='user_not_found' ){
            return $this->failNotFound('There is no user with such phone number','user_not_found');
        }
        if( $result=='user_pass_wrong' ){
            return $this->failUnauthorized('User exists but password is wrong!','user_pass_wrong');
        }
        if( $result=='user_phone_unverified' ){
            return $this->failForbidden('It is needed to send confirmation SMS to user.','user_phone_unverified');
        }
        if( $result=='ok' ){
            $user=$UserModel->getSignedUser();
            $this->session->set('user_id',$user->user_id);
            $this->session->set('user_data',$user);
            return $this->respond(1);
        }
        return $this->fail(0);
    }
    public function signOut(){
        $user_id=$this->session->get('user_id');
        $UserModel=model('UserModel');
        $UserModel->signOut($user_id);
        $this->session->destroy();
        return $this->respond(1);
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
    
    public function phoneVerificationSend(){
        $user_phone=$this->request->getVar('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        
        $UserVerificationModel=model('UserVerificationModel');
        $UserModel=model('UserModel');
        $unverified_user_id=$UserModel->getUnverifiedUserIdByPhone($user_phone_cleared);
        if( !$unverified_user_id ){
            $this->error(404,'unverified_phone_not_found','No such user phone or it is already verified');
        }
        
        helper('hash_generate');
        $confirm_value=generate_hash(4,'numeric');
        $data=[
            'user_id'=>$unverified_user_id,
            'confirm_type'=>'phone',
            'confirm_value'=>$confirm_value
        ];
        $UserVerificationModel->insert($data);
        
        $msg_data=[
            'confirm_value'=>$confirm_value
        ];
        
        
        
        
        
        
        
        $Sms=new \App\Libraries\DevinoSms();
        $Sms->send($user_phone,view('user/userPhoneConfirmSms.php',$msg_data));
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
