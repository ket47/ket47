<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table      = 'user_list';
    protected $primaryKey = 'user_id';
    protected $allowedFields = ['user_name', 'user_phone', 'user_pass', 'user_email'];
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
            'user_pass_confirm'=>$user_pass_confirm
            ];
        $ok=$this->insert($row,true);
        return $ok;
    }
    
    public function signOut($user_id){
        $data=[
            'user_id'=>$user_id,
            'signed_out_at'=>'NOW()'
        ];
        $this->save($data);
    }
    
    public function signIn($user_phone,$user_pass){
        $data=[
            'user_phone'=>$user_phone,
            'user_pass'=>$user_pass
        ];
        $data= $this->hashPassword($data);
        $user=$this->select('user_id,')->where('user_phone',$user_phone)->get()->getResult();
        
        
        print_r($user);
    }
}