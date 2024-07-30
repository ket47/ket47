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
                'ymapSuggestionApiKey'=>getenv('location.ymapSuggestionApiKey'),
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
                'recurrentPaymentAllow'=>1//sudo()?1:0,
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
        if( $result=='user_pass_wrong' ){
            return $this->failValidationErrors(json_encode(['user_pass'=>'wrong']));
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
        $user_id=$this->request->getPost('user_id');
        $user_pass=$this->request->getPost('user_pass');

        $UserModel=model('UserModel');
        $user_pass_hash=$UserModel->where('user_id',$user_id)->get()->getRow('user_pass');
        if( !password_verify($user_pass,$user_pass_hash) ){
            return $this->failForbidden('wrong pass');
        }

        $UserModel->transStart();
        $UserModel->fieldUpdateAllow('is_disabled');
        $user=(object)[
            'user_id'=>$user_id,
            'user_name'=>'удаленный пользователь',
            'user_email'=>null,
            'is_disabled'=>1
        ];
        $UserModel->itemUpdate($user);
        $ok=$UserModel->itemDelete($user_id);
        if( !$ok || $UserModel->errors() ){
            $UserModel->transRollback();
            return $this->failValidationErrors(json_encode($UserModel->errors()));
        }
        $UserModel->transComplete();
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

        // if( $user_ota_code ){
        //     $UserVerificationModel=model('UserVerificationModel');
        //     $verification=$UserVerificationModel->itemFind($user_phone,'phone',$user_ota_code);
        //     if( !$verification ){
        //         return $this->failNotFound('verification_not_found');
        //     }
        //     $UserModel->verifyUser($new_user_id,'phone');
        // }
    public function signUp() {
        $user_phone=$this->request->getPost('user_phone');
        $user_name=$this->request->getPost('user_name');
        $user_email=$this->request->getPost('user_email');
        $user_pass=$this->request->getPost('user_pass');
        $user_pass_confirm=$this->request->getPost('user_pass_confirm');
        $user_ota_code=$this->request->getPost('user_ota_code');

        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $UserModel=model('UserModel');
        /**
         * Old api. user signedup but not verified phone yet
         */
        if( !$user_ota_code && $UserModel->getUnverifiedUserIdByPhone($user_phone_cleared) ){
            return $this->fail('user_phone_unverified');
        }

        $this->signOut();
        $result=$UserModel->signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm,$user_email);
        if( $UserModel->errors() ){
            madd('auth','up','error');
            return $this->failValidationErrors(json_encode($UserModel->errors()));
        }
        if( !is_numeric($result) ){
            madd('auth','up','error',null,$result);
            return $this->fail($result);            
        }
        madd('auth','up','ok');

        /**
         * Automatically signin new user so no need to extra signin request
         */
        if( $user_ota_code ){
            $this->signInByCode();
        } else {
            $this->signIn();
        }
        $new_user_id=$result;
        $this->signUpExtradata($new_user_id);
        $this->signUpPromoCreate($new_user_id);

        return $this->respond($new_user_id);
    }

    private function signUpPromoCreate($new_user_id){
        $inviter_user_id=$this->request->getPost('inviter_user_id');
        $PromoModel=model('PromoModel');
        $PromoModel->listCreate($new_user_id,$inviter_user_id??0);        
    }

    private function signUpExtradata($new_user_id){
        $user_avatar_name=$this->request->getPost('user_avatar_name');
        $user_birthday=$this->request->getPost('user_birthday');
        if(!$user_avatar_name && !$user_birthday){
            return;
        }
        $user_update=(object)[
            'user_id'=>$new_user_id,
        ];
        if($user_avatar_name){
            $user_update->user_avatar_name=$user_avatar_name;
        }
        if($user_birthday){
            $user_update->user_birthday=$user_birthday;
        }

        $UserModel=model('UserModel');
        $UserModel->fieldUpdateAllow('user_birthday');
        $UserModel->itemUpdate($user_update);
    }
    
    public function signIn(){
        $user_phone=$this->request->getPost('user_phone');
        $user_pass=$this->request->getPost('user_pass');
        if( !$user_phone || !$user_pass ){
            return $this->fail('empty_phone_or_pass');
        }
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $UserModel=model('UserModel');
        $result=$UserModel->signIn($user_phone_cleared,$user_pass);
        if( $result=='user_not_found' ){
            madd('auth','in','error',null,$result);
            return $this->failNotFound('user_not_found');
        }
        if( $result=='user_pass_wrong' ){
            madd('auth','in','error',null,$result);
            return $this->failUnauthorized('user_pass_wrong');
        }
        if( $result=='user_phone_unverified' ){
            madd('auth','in','error',null,$result);
            return $this->failForbidden('user_phone_unverified');
        }
        if( $result=='user_not_found' ){
            madd('auth','in','error',null,$result);
            return $this->failNotFound('user_not_found');
        }
        if( $result=='user_is_disabled' ){
            madd('auth','in','error',null,$result);
            return $this->failUnauthorized('user_is_disabled');
        }
        if( $result=='ok' ){
            $user=$UserModel->getSignedUser();
            if( empty($user->user_id) ){
                madd('auth','in','error',null,'user_data_fetch_error');
                return $this->fail('user_data_fetch_error');
            }
            madd('auth','in','ok');
            $this->signInMetric( $user->user_id );
            $this->signInCourier($user->user_id);
            $this->signInTokenSave($user->user_id);
            return $this->respond($user->user_id);
        }
        return $this->fail($result);
    }

    public function signInByCode(){
        $user_phone=$this->request->getPost('user_phone');
        $user_ota_code=$this->request->getPost('user_ota_code');
        if( !$user_phone || !$user_ota_code ){
            return $this->fail('empty_phone_or_pass');
        }

        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $UserModel=model('UserModel');
        $result=$UserModel->signInByOta($user_phone_cleared,$user_ota_code);
        if( $result=='user_not_found' ){
            madd('auth','in','error',null,$result);
            return $this->failNotFound('user_not_found');
        }
        if( $result=='user_is_disabled' ){
            madd('auth','in','error',null,$result);
            return $this->failUnauthorized('user_is_disabled');
        }
        if( $result=='ok' ){
            $user=$UserModel->getSignedUser();
            if( empty($user->user_id) ){
                madd('auth','in','error',null,'user_data_fetch_error');
                return $this->fail('user_data_fetch_error');
            }
            madd('auth','in','ok');
            $this->signInMetric( $user->user_id );
            $this->signInCourier( $user->user_id );
            $this->signInTokenSave( $user->user_id );
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

    private function signInCourier($user_id){//????????????????????????????
        $CourierModel=model('CourierModel');
        $courier_id=$CourierModel->where('owner_id',$user_id)->get()->getRow('courier_id');
        session()->set('courier_id',$courier_id);
    }

    private function signInTokenSave( $owner_id ){
        $agent = $this->request->getUserAgent();

        $TokenModel=model('TokenModel');

        $token_holder='user';
        $token_holder_id=$owner_id;
        $token_device=$agent->getPlatform();
        $token_hash_raw=session_id();
        $TokenModel->itemCreate($owner_id,$token_holder,$token_holder_id,$token_device,$token_hash_raw);
    }

    public function signInOptionsGet(){
        $user_phone=$this->request->getPost('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $user_signed_id=(int) session()->get('user_id');


        $UserModel=model('UserModel');
        $UserModel->where('user_phone',$user_phone_cleared);
        $UserModel->select('1 by_code');
        $UserModel->select('IF(user_pass IS NOT NULL AND user_phone_verified=1,1,0) by_pass');
        $UserModel->select('IF(deleted_at IS NOT NULL,1,0) is_deleted');
        $UserModel->select('is_disabled');
        $UserModel->select("IF($user_signed_id=user_id,1,0) is_signedin");

        $result =$UserModel->get()->getRow();
        if( !$result ){
            return $this->failNotFound('notfound');
        }
        return $this->respond($result);
    }
    

    // private function signOutCourier(){
    //     $user_id=session()->get('user_id');
    //     $CourierModel=model('CourierModel');
    //     if( $CourierModel->isIdle(null,$user_id) ){
    //         return 'ok';
    //     }
    //     $courier_id=$CourierModel->where('owner_id',$user_id)->get()->getRow('courier_id');
    //     return $CourierModel->itemUpdateStatus($courier_id,'idle');
    // }

    public function signOut(){
        $user_id=session()->get('user_id');

        $UserModel=model('UserModel');
        $TokenModel=model('TokenModel');

        $UserModel->signOut($user_id);
        $TokenModel->itemDelete(null,null,session_id());

        session_unset();//clear all session variables
        madd('auth','out','ok');
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
            madd('auth','forgot','error',null,'user_notfound');
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
            $UserModel->fieldUpdateAllow('user_pass');
            $update_ok=$UserModel->update($phone_user_id,['user_pass'=>$new_password,'user_pass_confirm'=>$new_password]);
            if( $update_ok ){
                madd('auth','forgot','ok');
                return $this->respondUpdated('password_updated');
            }
            if( $UserModel->errors() ){
                madd('auth','forgot','error');
                return $this->failValidationErrors(json_encode($UserModel->errors()));
            }
            madd('auth','forgot','error',null,'not_updated');
            return $this->fail('not_updated');
        } else {
            madd('auth','forgot','error',null,'was_not_sent');
            return $this->fail('was_not_sent');
        }
    }
    ///////////////////////////////////////////////
    //VERIFICATION SECTION
    ///////////////////////////////////////////////
    public function phoneVerificationSend(){
        $user_phone=$this->request->getVar('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);
        $debounce_elapsed=session()->get("verificationDebounce$user_phone_cleared");

        if( $debounce_elapsed && $debounce_elapsed>time() ){
            return $this->fail('verification_already_sent');
        }
        $debounce_timeout=2*60-10;//2min -10 sec
        session()->set("verificationDebounce$user_phone_cleared",time()+$debounce_timeout);

        $UserVerificationModel=model('UserVerificationModel');
        $verification=$UserVerificationModel->itemGet($user_phone_cleared,'phone');
        if( !$verification || $verification=='verification_target_invalid' ){
            return $this->fail('verification_target_invalid');
        }
        $msg_data=[
            'verification_code'=>$verification->verification_value
        ];
        $Sms=\Config\Services::sms();
        $ok=$Sms->send($user_phone_cleared,view('messages/phone_verification_sms.php',$msg_data));
        if( $ok ){
            return $this->respond('ok');
        }
        return $this->fail('verification_send_failed');
    }

    /**
     * @deprecated use verificationCheck
     */
    public function phoneVerificationCheck(){
        $verification_value=$this->request->getPost('verification_code');
        $user_phone=$this->request->getPost('user_phone');
        helper('phone_number');
        $verification_type='phone';
        $verification_target= clearPhone($user_phone);

        $UserVerificationModel=model('UserVerificationModel');
        $verification=$UserVerificationModel->itemFind($verification_target,$verification_type);

        if( !$verification || $verification->verification_value!==$verification_value ){
            return $this->failNotFound('verification_not_found');
        }

        //$UserModel=model('UserModel');
        //$UserModel->verifyUser($verification->user_id);
        return $this->respond('verification_completed');
    }

    public function verificationCheck(){
        $verification_type=  $this->request->getPost('verification_type');
        $verification_target=$this->request->getPost('verification_target');
        $verification_value= $this->request->getPost('verification_value');

        $UserVerificationModel=model('UserVerificationModel');
        $verification=$UserVerificationModel->itemFind($verification_target,$verification_type,$verification_value);

        if( !$verification ){
            return $this->failNotFound('verification_not_found');
        }
        if( $verification_type=='phone' && $verification->user_id ){
            $UserModel=model('UserModel');
            $UserModel->verifyUser($verification->user_id);
        }
        return $this->respond('ok');
    }

    // public function phoneVerificationCreate(){
    //     $user_phone=$this->request->getPost('user_phone');
    //     helper('phone_number');
    //     $user_phone_cleared= clearPhone($user_phone);

    //     $UserVerificationModel=model('UserVerificationModel');
    //     $verification=$UserVerificationModel->itemGet($user_phone_cleared,'phone',session_id());
    //     if( !$verification || $verification=='verification_target_invalid' ){
    //         return $this->fail('verification_target_invalid');
    //     }

    //     $UserModel=model('UserModel');
    //     $UserModel->where('user_phone',$user_phone_cleared)->select('user_id');
    //     $user_id=$UserModel->get()->getRow('user_id');
    //     if( $user_id ){
    //         return $this->respond('verification_created_signin');
    //     }
    //     return $this->respond('verification_created_signup');
    // }

    public function phoneVerificationNeeded(){
        $user_phone=$this->request->getPost('user_phone');
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);

        $UserVerificationModel=model('UserVerificationModel');
        $result=$UserVerificationModel->itemGet($user_phone_cleared);
        if( $result=='unverified_phone_not_found' ){
            return $this->failNotFound('unverified_phone_not_found');
        }
        return $this->respond('verification_needed');
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
        $location_comment=$this->request->getVar('location_comment');
        
        $data=[
            'location_holder'=>'user',
            'location_holder_id'=>$location_holder_id,
            'location_group_id'=>$location_group_id,
            'location_longitude'=>$location_longitude,
            'location_latitude'=>$location_latitude,
            'location_address'=>$location_address,
            'location_comment'=>$location_comment,
            'is_disabled'=>0,
            'owner_id'=>$location_holder_id
        ];
        $UserModel=model('UserModel');
        $LocationModel=model('LocationModel');
        if( !$UserModel->permit($data['owner_id'],'w') ){
            return $this->failForbidden('forbidden');
        }
        $result= $LocationModel->itemCreate($data,10);
        if( $result=='ok' ){
            return $this->respondCreated($result);
        }
        if( $LocationModel->errors() ){
            return $this->failValidationErrors(json_encode($LocationModel->errors()));
        }
        return $this->fail($result);
    }

    public function locationSetMain(){
        $location_id=$this->request->getPost('location_id');
        $LocationModel=model('LocationModel');
        $result=$LocationModel->itemMainSet($location_id);
        if( $result=='ok' ){
            madd('location','switch','ok',$location_id);
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
