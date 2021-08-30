<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
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
        'signed_in_at',
        'signed_out_at'
        ];
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    
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
    
    public function signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm){
        $row=[
            'user_phone'=>$user_phone_cleared,
            'user_name'=>$user_name,
            'user_pass'=>$user_pass,
            'user_pass_confirm'=>$user_pass_confirm,
            'is_active'=>true
            ];
        $ok=$this->insert($row,true);
        return $ok;
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
        
        $this->update($user->user_id,['signed_in_at'=>\CodeIgniter\I18n\Time::now()]);
        $this->signed_user_id=$user->user_id;
        return 'ok' ;
    }
    
    public function getSignedUser(){
        if( !$this->signed_user_id ){
            return null;
        }
        $user= $this->where('user_id',$this->signed_user_id)->get()->getRow();
        return $user;
    }
    
    public function getUnverifiedUserIdByPhone($user_phone_cleared){
        $sql="SELECT 
                `user_id` 
            FROM 
                `user_list` 
            WHERE 
                `user_phone` = '79787288233' 
                AND COALESCE(`user_phone_verified`,0) = 0";
        $user_id = $this->query($sql)->getRow('user_id');
        return $user_id;
    }
}