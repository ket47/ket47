<?php
namespace App\Models;
use CodeIgniter\Model;

class UserVerificationModel extends Model{
    protected $table      = 'user_verification_list';
    protected $primaryKey = 'user_verification_id';

    
    protected $allowedFields = [
        'user_id',
        'verification_type',
        'verification_target',
        'verification_value',
        'verification_session',
        'expired_at',
        'is_verified',
        ];
    
    private function clearVerifications(){
        $this->where("expired_at<=NOW()");
        $this->delete();
    }
    
    public function phoneVerify( $user_phone, $verification_code ){
        $this->clearVerifications();
        helper('phone_number');
        $user_phone_cleared= clearPhone($user_phone);

        $UserModel=model('UserModel');
        $user=$UserModel->where('user_phone',$user_phone_cleared);
        if( $user->user_phone_verified==1 ){
            return 'verification_completed';
        }
        
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

    public function itemGet( int $user_verification_id ){
        $this->where('user_verification_id',$user_verification_id);
        return $this->get()->getRow();
    }

    /**
     * Searches for existing verification that not expired yet
     */
    private $expiration_timeout=30*60;
    public function itemFind( string $verification_target, string $verification_type, ?string $verification_value=null, ?int $expiration_offset=null ){
        if( !$expiration_offset ){
            $expiration_offset=$this->expiration_timeout;
        }
        $expired_at=date('Y-m-d H:i:s',time()+$expiration_offset);
        $this->where("expired_at<'$expired_at' AND expired_at>NOW()");

        if( $verification_value ){
            $this->where('verification_value',"'$verification_value'",false);
        }
        $this->where('verification_type',$verification_type);
        $this->where('verification_target',$verification_target);
        return $this->get()->getRow();
    }

    public function itemCreate( string $verification_target, string $verification_type ){
        $is_valid=false;
        if( $verification_type=='phone' && preg_match("/^[0-9]{11}$/", $verification_target) ){
            $is_valid=true;
        } else 
        if( $verification_type=='email' && filter_var($verification_target, FILTER_VALIDATE_EMAIL) ){
            $is_valid=true;
        }
        if( $is_valid==false ){
            return 'verification_target_invalid';
        }
        $this->clearVerifications();
        helper('hash_generate');
        $verification_code=generate_hash(4,'numeric');
        $verification=[
            'verification_type'=>$verification_type,
            'verification_target'=>$verification_target,
            'verification_value'=>$verification_code,
            'expired_at'=>date('Y-m-d H:i:s',time()+$this->expiration_timeout)
        ];
        $verification['user_verification_id']=$this->insert($verification,true);
        return (object) $verification;
    }

    public function itemMarkVerified( int $user_verification_id ){
        //no permission check
        $this->update($user_verification_id,['is_verified'=>1]);

    }

    // /**
    //  * Checks if such verification record exists in db
    //  */
    // public function itemVerify( string $verification_target, string $verification_type, string $unverified_value ):string{
    //     $this->where('verification_target',$verification_target);
    //     $this->where('verification_type',$verification_type);
    //     $this->where('verification_value',"'$unverified_value'",false);//if starts with zeros escaping omits them
    //     $this->where('expired_at>NOW()');
    //     $verification = $this->select('user_verification_id,user_id')->get()->getRow();
    //     if( empty($verification->user_verification_id) ){
    //         return 'fail';//notfound
    //     }
    //     if( $verification_type=='phone' ){
    //         $UserModel=model('UserModel');
    //         $UserModel->verifyPhone($verification->user_id);
    //     } else {
    //         return 'fail';
    //     }
    //     //$this->delete($verification->user_verification_id);
    //     return 'ok';
    // }
}