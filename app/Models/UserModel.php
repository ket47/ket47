<?php

namespace App\Models;

class UserModel extends PermissionLayer{
    private $signed_user_id=0;
    protected $table      = 'user_list';
    protected $primaryKey = 'user_id';
    protected $allowedFields = [
        'user_name',
        'user_phone',
        'user_phone_verified',
        'user_pass',
        'user_email',
        'is_active',
        'owner_id',
        'ally_ids',
        'signed_in_at',
        'signed_out_at'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];
    
    protected $validationRules    = [
        'user_name'     => 'required|alpha_numeric_space|min_length[3]',
        'user_phone'    => 'required|numeric|exact_length[11]|is_unique[user_list.user_phone]',
        'user_email'    => 'if_exist|valid_email|is_unique[user_list.user_email]',
        'user_pass'     => 'required|min_length[6]',
        'user_pass_confirm' => 'required_with[user_pass]|matches[user_pass]'
    ];

    protected $validationMessages = [
        'user_email'        => [
            'is_unique' => 'Sorry. That email has already been taken. Please choose another.'
        ]
    ];
    
    protected function hashPassword(array $data){
        if ( isset($data['data']['user_pass']) ){
            $data['data']['user_pass'] = password_hash($data['data']['user_pass'],PASSWORD_BCRYPT);
        }
        return $data;
    }
    
    
    
    public function itemGet( $user_id ){
        $this->permitWhere('r');
        $user= $this->where('user_id',$user_id)->get()->getRow();
        if($user){
            $UserGroupMemberModel=model('UserGroupMemberModel');
            $user->member_of_groups=$UserGroupMemberModel->userMemberGroupsGet($user_id);
        }
        unset($user->user_pass);
        return $user;
    }
    
    public function itemCreate( $user_data ){
        $this->transStart();
        $user_id=$this->insert($user_data,true);
        if( $user_id ){
            $UserGroupMemberModel=model('UserGroupMemberModel');
            $UserGroupMemberModel->userGroupJoinByType($user_id,'customer');
            $this->update($user_id,['owner_id'=>$user_id]);
        }
        $this->transComplete();
        return $user_id;        
    }
    
    public function itemUpdate( $user_id, $user_data ){
         
    }
    
    public function itemDelete( $user_id ){
        
    }
    
    public function listGet( $filter=null ){
        $this->permitWhere('r');
        $this->select("
            user_name,
            user_phone,
            user_email,
            is_disabled,
            signed_in_at,
            signed_out_at");
        $user_list= $this->get()->getResult();
        return $user_list;        
    }
    
    public function listCreate( $user_list_data ){
        return false;
    }
    
    public function listUpdate( $user_list_data ){
        return false;
    }
    
    public function listDelete( $user_ids ){
        return false;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm){
        $user_data=[
            'user_phone'=>$user_phone_cleared,
            'user_name'=>$user_name,
            'user_pass'=>$user_pass,
            'user_pass_confirm'=>$user_pass_confirm,
            'is_disabled'=>0
            ];
        return $this->itemCreate($user_data);
    }
    
    public function signOut($user_id){
        $this->update($user_id,['signed_out_at'=>\CodeIgniter\I18n\Time::now()]);
    }
    
    public function signIn($user_phone,$user_pass){
        $user=$this->where('user_phone',$user_phone)->get()->getRow();
        if( !$user || !$user->user_id ){
            return 'user_not_found';//user_phone not found
        }
        if( !password_verify($user_pass, $user->user_pass) ){
            return 'user_pass_wrong';//password wrong
        }
        if( !$user->user_phone_verified ){
            return 'user_phone_unverified';
        }
        if( $user->is_disabled ){
            return 'user_is_disabled';
        }
        $PermissionModel=model('PermissionModel');
        $PermissionModel->listFillSession();
        $this->update($user->user_id,['signed_in_at'=>\CodeIgniter\I18n\Time::now()]);
        $this->signed_user_id=$user->user_id;
        return 'ok' ;
    }
    
    public function getSignedUser(){
        if( !$this->signed_user_id ){
            return null;
        }
        return $this->itemGet( $this->signed_user_id );
    }

    
    public function getUnverifiedUserIdByPhone($user_phone_cleared){
        $sql="SELECT 
                `user_id` 
            FROM 
                `user_list` 
            WHERE 
                `user_phone` = '$user_phone_cleared' 
                AND COALESCE(`user_phone_verified`,0) = 0";
        $user_id = $this->query($sql)->getRow('user_id');
        return $user_id;
    }
    
    public function passRecoveryCheckPhone($user_phone){//should we send pass to only verified phone or it could be mechanism to generate pass?
        return $this->where('user_phone',$user_phone)->get()->getRow('user_id');
    }
    
    public function passRecoveryCheckEmail($user_email){//should we send pass to only verified phone or it could be mechanism to generate pass?
        return $this->where('user_email',$user_email)->get()->getRow('user_id');
    }
}