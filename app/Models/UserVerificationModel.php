<?php
namespace App\Models;
use CodeIgniter\Model;

class UserVerificationModel extends Model{
    protected $table      = 'user_verification_list';
    protected $primaryKey = 'user_verification_id';

    
    protected $allowedFields = [
        'user_id',
        'verification_type',
        'verification_value'
        ];
    
    private function clearVerifications(){
        $last_week=new \CodeIgniter\I18n\Time('-1 week');
        $this->where("created_at<",$last_week)->delete();
    }
    
    
    public function phoneVerify( $user_phone, $verification_code ){
        $this->clearVerifications();
        $UserModel=model('UserModel');
        $unverified_user_id=$UserModel->getUnverifiedUserIdByPhone($user_phone);
        $verification=$this->where('user_id',$unverified_user_id)->where('verification_value',$verification_code)->get()->getRow();
        if( !$verification ){
            return 'verification_not_found';
        }
       
        $result=$UserModel->verifyUser($verification->user_id);
        if( $result==='verification_completed' ){
            $this->delete($verification->user_verification_id);
        }
        return $result;
    }

    public function itemGet( $user_phone_cleared ){
        $UserModel=model('UserModel');
        $unverified_user_id=$UserModel->getUnverifiedUserIdByPhone($user_phone_cleared);
        
        if(!$unverified_user_id){
            return 'unverified_phone_not_found';
        }

        $this->where('user_id',$unverified_user_id);
        $this->where('verification_type','phone');
        $verification_code=$this->get()->getRow('verification_code');
        if($verification_code){
            return $verification_code;
        }

        helper('hash_generate');
        $verification_code=generate_hash(4,'numeric');
        $data=[
            'user_id'=>$unverified_user_id,
            'verification_type'=>'phone',
            'verification_value'=>$verification_code
        ];
        $this->insert($data);

        return $verification_code;
    }
}