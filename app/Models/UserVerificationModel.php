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
}