<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class User extends \App\Controllers\BaseController{
    use ResponseTrait;
    /////////////////////////////////////////////
    //USER OPERATIONS SECTION
    /////////////////////////////////////////////
    public function itemGet(){
        $mode=$this->request->getVar('mode');
        $user_id=$this->request->getVar('user_id');
        if( !$user_id ){
            $user_id=session()->get('user_id');
        }
        $UserModel=model('UserModel');
        $user=$UserModel->itemGet($user_id,$mode);
        return $this->respond($user);
    }

    public function itemSettingsGet(){
        $settings=[
            'app_title'=>getenv('app.title'),
            'app'=>[
                'frontendUrl'=>getenv('app.frontendUrl'),
                'backendUrl'=>getenv('app.backendUrl'),
            ],
            'location'=>[
                'mapBoundaries'=>[getenv('location.mapBoundaries')],
                'mapCenter'=>getenv('location.mapCenter'),
                'ymapApiKey'=>getenv('location.ymapApiKey'),
                'addressErase'=>getenv('location.addressErase')
            ],
            'delivery'=>[
                'speed'=>getenv('delivery.speed'),
                'radius'=>getenv('delivery.radius'),
                'fee'=>getenv('delivery.fee'),
                'timeDelta'=>getenv('delivery.timeDelta'),
                'timePreparationDefault'=>getenv('delivery.timePreparationDefault'),
            ],
            'firebase'=>[
                'apiKey'=>getenv('firebase.apiKey'),
                'authDomain'=>getenv('firebase.authDomain'),
                'projectId'=>getenv('firebase.projectId'),
                'storageBucket'=>getenv('firebase.storageBucket'),
                'messagingSenderId'=>getenv('firebase.messagingSenderId'),
                'appId'=>getenv('firebase.appId'),
                'vapidKey'=>getenv('firebase.vapidKey'),
            ],
            'other'=>[
                'recurrentPaymentAllow'=>getenv('uniteller.recurrentAllow'),
            ]
        ];
        return $this->respond($settings);
    }
    
    public function itemCreate(){
        return $this->signUp();
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        
        $UserModel=model('UserModel');
        $result=$UserModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $UserModel->errors() ){
            return $this->failValidationErrors(json_encode($UserModel->errors()));
        }
        return $this->respondUpdated($result);
    }
    
    public function itemUpdateGroup(){
        $user_id=$this->request->getVar('user_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $UserModel=model('UserModel');
        $result=$UserModel->itemUpdateGroup($user_id,$group_id,$is_joined);
        
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        if( $UserModel->errors() ){
            return $this->failValidationErrors(json_encode($UserModel->errors()));
        }
        return $this->respondUpdated($result);
    }
    
    public function itemDisable(){
        $user_id=$this->request->getVar('user_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $UserModel=model('UserModel');
        $result=$UserModel->itemDisable($user_id,$is_disabled);
        
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->respondUpdated($result);
    }
    
    public function itemDelete(){
        $user_id=$this->request->getVar('user_id');
        $UserModel=model('UserModel');
        $ok=$UserModel->itemDelete($user_id);
        if( $UserModel->errors() ){
            return $this->failValidationErrors(json_encode($UserModel->errors()));
        }
        return $this->respondDeleted($ok);        
    }
    
    public function itemUnDelete(){
        $user_id=$this->request->getVar('user_id');
        $UserModel=model('UserModel');
        $result=$UserModel->itemUnDelete($user_id);        
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }
    /////////////////////////////////////////////
    //LOGIN SECTION
    /////////////////////////////////////////////
    public function signUp() {
        $user_phone=$this->request->getVar('user_phone');
        $user_name=$this->request->getVar('user_name');
        $user_pass=$this->request->getVar('user_pass');
        $user_pass_confirm=$this->request->getVar('user_pass_confirm');
        $user_email=$this->request->getVar('user_email');
        $inviter_user_id=$this->request->getVar('inviter_user_id');
        $this->signOut();
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $UserModel=model('UserModel');

        $new_user_id=$UserModel->signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm,$user_email);
        if( $new_user_id=='user_phone_unverified' ){
            return $this->failForbidden('user_phone_unverified');
        }
        if( $UserModel->errors() ){
            return $this->failValidationErrors(json_encode($UserModel->errors()));
        }
        if( $new_user_id ){
            $PromoModel=model('PromoModel');
            $PromoModel->listCreate($new_user_id,$inviter_user_id??0);
        }

        $this->signInMetric( $new_user_id );
        
        $Messenger=new \App\Libraries\Messenger;
        $Messenger->itemSend((object)[
            'message_reciever_id'=>-100,
            'message_transport'=>'telegram',
            'message_text'=>"Новый пользователь: $user_name",
        ]);
        return $this->respondCreated($new_user_id);
    }
    
    public function signIn(){
        $user_phone=$this->request->getVar('user_phone');
        $user_pass=$this->request->getVar('user_pass');
        if( !$user_phone || !$user_pass ){
            return $this->fail('empty_phone_or_pass');
        }
        $this->signOutUser();
        
        $user_phone_cleared= '7'.substr(preg_replace('/[^\d]/', '', $user_phone),-10);
        $UserModel=model('UserModel');







        $result=$UserModel->signIn($user_phone_cleared,$user_pass);
        if( $result=='user_not_found' ){
            return $this->failNotFound('user_not_found');
        }
        if( $result=='user_pass_wrong' ){
            return $this->failUnauthorized('user_pass_wrong');
        }
        if( $result=='user_is_disabled' ){
            return $this->failUnauthorized('user_is_disabled');
        }
        if( $result=='user_phone_unverified' ){
            return $this->failForbidden('user_phone_unverified');
        }
        if( $result=='ok' ){
            $user=$UserModel->getSignedUser();
            if( !$user ){
                return $this->fail('user_data_fetch_error');
            }
            $this->signInMetric( $user->user_id );
            session()->set('user_id',$user->user_id);
            session()->set('user_data',$user);
            $this->signInCourier($user->user_id);
            return $this->respond($user->user_id);
        }
        return $this->fail($result);
    }

    private function signInMetric( $user_id ){
        $metric_id=$this->request->getPost('metric_id');
        if($metric_id){
            $MetricModel=model('MetricModel');
            $MetricModel->itemUpdate((object)[
                'metric_id'=>$metric_id,
                'user_id'=>$user_id
            ]);
        }
    }

    private function signInCourier($user_id){
        $CourierModel=model('CourierModel');
        $courier_id=$CourierModel->where('owner_id',$user_id)->get()->getRow('courier_id');
        session()->set('courier_id',$courier_id);
    }
    
    private function signOutUser(){
        $user_id=session()->get('user_id');
        $UserModel=model('UserModel');
        $UserModel->signOut($user_id);
        session_unset();//clear all session variables
    }

    private function signOutCourier(){
        $user_id=session()->get('user_id');
        $CourierModel=model('CourierModel');
        if( $CourierModel->isIdle(null,$user_id) ){
            return 'ok';
        }
        $courier_id=$CourierModel->where('owner_id',$user_id)->get()->getRow('courier_id');
        return $CourierModel->itemUpdateStatus($courier_id,'idle');
    }

    public function signOut(){
        $this->signOutUser();
        if (session_status() === PHP_SESSION_ACTIVE){
            session_destroy();
        }
        return $this->respond('ok');
    }

    
    public function passwordReset(){
        $user_phone=$this->request->getVar('user_phone');
        $user_email=$this->request->getVar('user_email');
        $user_name=$this->request->getVar('user_name');
        $user_phone_cleared= '7'.substr(preg_replace('/[^\d]/', '', $user_phone),-10);
        
        $UserModel=model('UserModel');
        helper('hash_generate');
        $new_password=generate_hash(4);
        
        $Messenger=new \App\Libraries\Messenger();
        $phone_user_id=$UserModel->passRecoveryCheckPhone($user_phone_cleared,$user_name);
        $email_user_id=$UserModel->passRecoveryCheckEmail($user_email,$user_name);
        $user_id=$phone_user_id??$email_user_id;

        if(!$user_id){
            return $this->failNotFound('user_notfound');
        }

        $context=[
            'new_pass'=>$new_password
        ];
        $sms_send_ok=$Messenger->itemSend((object)[
            'message_transport'=>'sms',
            'message_reciever_id'=>$user_id,
            'template'=>'messages/password_reset_sms.php',
            'context'=>$context
        ]);
        $email_send_ok=$Messenger->itemSend((object)[
            'message_transport'=>'email',
            'message_reciever_id'=>$user_id,
            'template'=>'messages/password_reset_email.php',
            'context'=>$context
        ]);
        
        if( $sms_send_ok || $email_send_ok ){
            $update_ok=$UserModel->update($phone_user_id,['user_pass'=>$new_password,'user_pass_confirm'=>$new_password]);
            if( $update_ok ){
                return $this->respondUpdated('password_updated');
            }
            if( $UserModel->errors() ){
                return $this->failValidationErrors(json_encode($UserModel->errors()));
            }
            return $this->fail('not_updated');
        } else {
            return $this->fail('was_not_sent');
        }
    }
    ///////////////////////////////////////////////
    //VERIFICATION SECTION
    ///////////////////////////////////////////////
    public function phoneVerificationNeeded(){
        $user_phone=$this->request->getVar('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);

        $UserVerificationModel=model('UserVerificationModel');
        $result=$UserVerificationModel->itemGet($user_phone_cleared);
        if( $result=='unverified_phone_not_found' ){
            return $this->failNotFound('unverified_phone_not_found');
        }
        return $this->respond('verification_needed');
    }

    public function phoneVerificationSend(){
        $user_phone=$this->request->getVar('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);

        $UserVerificationModel=model('UserVerificationModel');
        $result=$UserVerificationModel->itemGet($user_phone_cleared);
        if( $result=='unverified_phone_not_found' ){
            return $this->failNotFound('unverified_phone_not_found');
        }

        $msg_data=[
            'verification_code'=>$result
        ];
        $Sms=\Config\Services::sms();
        $ok=$Sms->send($user_phone_cleared,view('messages/phone_verification_sms.php',$msg_data));
        if( $ok ){
            return $this->respond('sms_sent_ok');
        }
        else {
            return $this->fail('sms_send_failed');
        }
    }
    
    public function phoneVerificationCheck(){
        $verification_code=$this->request->getVar('verification_code');
        $user_phone=$this->request->getVar('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $UserVerificationModel=model('UserVerificationModel');
        $result=$UserVerificationModel->phoneVerify($user_phone_cleared,$verification_code);
        if( $result==='verification_completed' ){
            return $this->respond('verification_completed');
        }
        if( $result==='verification_not_found' ){
            return $this->failNotFound('verification_not_found');
        }
        return $this->fail('verification_error');
    }
    
    
    public function locationListGet(){
        $incluideGroupList=$this->request->getVar('includeGroupList');
        $user_id=session()->get('user_id');
        $filter=[
            'is_disabled'=>0,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>10,
            'location_holder'=>'user',
            'location_holder_id'=>$user_id
        ];
        $LocationModel=model('LocationModel');

        $location_list=$LocationModel->listGet($filter);
        $response=[
            'location_list'=>$location_list
        ];
        if( $incluideGroupList ){
            $LocationGroupModel=model('LocationGroupModel');
            $filter=[
                'name_query'=>'address',
                'name_query_fields'=>'group_type'
            ];
            $response['location_group_list']=$LocationGroupModel->listGet($filter);
        }
        return $this->respond($response);
    }

    public function locationCreate(){
        $location_holder_id=$this->request->getVar('location_holder_id');
        $location_group_id=$this->request->getVar('location_group_id');
        $location_longitude=$this->request->getVar('location_longitude');
        $location_latitude=$this->request->getVar('location_latitude');
        $location_address=$this->request->getVar('location_address');
        
        $data=[
            'location_holder'=>'user',
            'location_holder_id'=>$location_holder_id,
            'location_group_id'=>$location_group_id,
            'location_longitude'=>$location_longitude,
            'location_latitude'=>$location_latitude,
            'location_address'=>$location_address,
            'is_disabled'=>0,
            'owner_id'=>$location_holder_id
        ];
        $UserModel=model('UserModel');
        $LocationModel=model('LocationModel');
        if( !$UserModel->permit($data['owner_id'],'w') ){
            return $this->failForbidden('forbidden');
        }
        $result= $LocationModel->itemCreate($data,5);
        if( $result=='ok' ){
            return $this->respondCreated($result);
        }
        if( $LocationModel->errors() ){
            return $this->failValidationErrors(json_encode($LocationModel->errors()));
        }
        return $this->fail($result);
    }

    public function locationSetMain(){
        $location_id=$this->request->getVar('location_id');
        $LocationModel=model('LocationModel');
        $result=$LocationModel->itemMainSet($location_id);
        if( $result=='ok' ){
            return $this->respondUpdated('ok');
        }
        return $this->fail('idle');
    }
    
    public function locationDelete(){
        $location_id=$this->request->getVar('location_id');
        $LocationModel=model('LocationModel');
        $result=$LocationModel->itemDelete($location_id);
        if( $result=='ok' ){
            return $this->respondDeleted('ok');
        }
        return $this->fail('idle');
    }
}
