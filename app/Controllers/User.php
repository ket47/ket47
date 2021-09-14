<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class User extends \App\Controllers\BaseController{
    use ResponseTrait;
    /////////////////////////////////////////////
    //USER OPERATIONS SECTION
    /////////////////////////////////////////////
    public function itemCreate(){
        return $this->signUp();
    }
    
    public function itemUpdate(){
        $user_id=$this->request->getVar('user_id');
        $field_name=$this->request->getVar('name');
        $field_value=$this->request->getVar('value');
        $UserModel=model('UserModel');
        $ok=$UserModel->itemUpdate($user_id,[$field_name=>$field_value]);
        if( $ok ){
            return $this->respondUpdated(1);
        }
        if( $UserModel->errors() ){
            return $this->failValidationError(json_encode($UserModel->errors()));
        }
        return $this->fail(0);
    }
    
    public function itemDelete(){
        $user_id=$this->request->getVar('user_id');
        $UserModel=model('UserModel');
        $ok=$UserModel->itemDelete($user_id);
        if( $UserModel->errors() ){
            return $this->failValidationError(json_encode($UserModel->errors()));
        }
        return $this->respondDeleted($ok);        
    }
    /////////////////////////////////////////////
    //LOGIN SECTION
    /////////////////////////////////////////////
    public function signUp() {
        $user_phone=$this->request->getVar('user_phone');
        $user_name=$this->request->getVar('user_name');
        $user_pass=$this->request->getVar('user_pass');
        $user_pass_confirm=$this->request->getVar('user_pass_confirm');
        
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $UserModel=model('UserModel');
        $user_id=$UserModel->signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm);
        
        if( $UserModel->errors() ){
            return $this->failValidationError(json_encode($UserModel->errors()));
        }
        return $this->respondCreated($user_id);
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
        if( $result=='user_is_disabled' ){
            return $this->failUnauthorized('User exists but blocked!','user_is_disabled');
        }
        if( $result=='user_phone_unverified' ){
            return $this->failForbidden('It is needed to send confirmation SMS to user.','user_phone_unverified');
        }
        if( $result=='ok' ){
            $user=$UserModel->getSignedUser();
            if( !$user ){
                return $this->fail('user_data_fetch_error');
            }
            $this->session->set('user_id',$user->user_id);
            $this->session->set('user_data',$user);
            return $this->respond($user->user_id);
        }
        return $this->fail($result);
    }
    
    public function signOut(){
        $user_id=$this->session->get('user_id');
        $UserModel=model('UserModel');
        $UserModel->signOut($user_id);
        session_unset();//clear all session variables
        return $this->respond(1);
    }
    
    public function passwordReset(){
        $user_phone=$this->request->getVar('user_phone');
        $user_email=$this->request->getVar('user_email');
        $user_phone_cleared= '7'.substr(preg_replace('/[^\d]/', '', $user_phone),-10);
        
        $UserModel=model('UserModel');
        helper('hash_generate');
        $new_password=generate_hash(6);
        
        $phone_user_id=$UserModel->passRecoveryCheckPhone($user_phone_cleared);
        if( $user_phone_cleared && $phone_user_id ){
            $msg_data=[
                'new_pass'=>$new_password
            ];
            $devinoSenderName=getenv('devinoSenderName');
            $devinoUserName=getenv('devinoUserName');
            $devinoPassword=getenv('devinoPassword');
            $Sms=new \App\Libraries\DevinoSms($devinoUserName,$devinoPassword,$devinoSenderName);
            $sms_send_ok=$Sms->send($user_phone_cleared,view('messages/password_reset_sms.php',$msg_data));
        }
        
        $email_user_id=$UserModel->passRecoveryCheckEmail($user_email);
        if( $user_email && $email_user_id ){
            $msg_data=[
                'new_pass'=>$new_password
            ];
            
            $email = \Config\Services::email();
            $config=[
                'SMTPHost'=>getenv('email_server'),
                'SMTPUser'=>getenv('email_username'),
                'SMTPPass'=>getenv('email_password')
            ];
            $email->initialize($config);
            $email->setFrom(getenv('email_from'), getenv('email_sendername'));
            $email->setTo($user_email);
            $email->setSubject('Сброс пароля сервиса TEZ');
            $email->setMessage(view('messages/password_reset_email.php',$msg_data));
            $email_send_ok=$email->send();
            if( !$email_send_ok ){
                return $this->fail($email->printDebugger(['headers']));
            }
        }
        
        if( $sms_send_ok || $email_send_ok ){
            $update_ok=$UserModel->update($phone_user_id,['user_pass'=>$new_password,'user_pass_confirm'=>$new_password]);
            if( $update_ok ){
                return $this->respondUpdated('password_updated');
            }
            if( $UserModel->errors() ){
                return $this->failValidationError(json_encode($UserModel->errors()));
            }
            return $this->fail('password_reset_not_updated');
        } else {
            return $this->fail('password_reset_was_not_sent');
        }
        return $this->failNotFound('password_reset_user_not_found');
    }
    ///////////////////////////////////////////////
    //VERIFICATION SECTION
    ///////////////////////////////////////////////
    public function phoneVerificationSend(){
        $user_phone=$this->request->getVar('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        
        $UserModel=model('UserModel');
        $unverified_user_id=$UserModel->getUnverifiedUserIdByPhone($user_phone_cleared);
        if( !$unverified_user_id ){
            $this->error(404,'unverified_phone_not_found','No such user phone or it is already verified');
        }
        
        helper('hash_generate');
        $verification_code=generate_hash(4,'numeric');
        $data=[
            'user_id'=>$unverified_user_id,
            'verification_type'=>'phone',
            'verification_value'=>$verification_code
        ];

        $UserVerificationModel=model('UserVerificationModel');
        $UserVerificationModel->insert($data);
        $msg_data=[
            'verification_code'=>$verification_code
        ];
        
        $devinoSenderName=getenv('devinoSenderName');
        $devinoUserName=getenv('devinoUserName');
        $devinoPassword=getenv('devinoPassword');
        $Sms=new \App\Libraries\DevinoSms($devinoUserName,$devinoPassword,$devinoSenderName);
        $ok=$Sms->send($user_phone_cleared,view('messages/phone_verification_sms.php',$msg_data));
        if( $ok ){
            return $this->respond('sms_sent_ok');
        }
        else {
            return $this->fail('sms_send_fail');
        }
    }
    
    public function phoneVerificationCheck(){
        $verification_code=$this->request->getVar('verification_code');
        $user_phone=$this->request->getVar('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);

        $UserVerificationModel=model('UserVerificationModel');
        $result=$UserVerificationModel->phoneVerify($user_phone_cleared,$verification_code);
        if( $result=='verification_completed' ){
            return $this->respond('verified');
        }
        if( $result=='verification_not_found' ){
            return $this->failNotFound();
        }
        return $this->fail('unverified');
    }
    
    
    
    
    
}
