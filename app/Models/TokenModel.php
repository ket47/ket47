<?php
namespace App\Models;
use CodeIgniter\Model;

class TokenModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'token_list';
    protected $primaryKey = 'token_id';
    protected $allowedFields = [
        'token_holder_id',
        'token_holder',
        'token_hash',
        'expired_at',
        'accessed_at'
        ];

    protected $useSoftDeletes = false;
    
    public function itemGet($token_id=null,$token_hash=null){
        if($token_id){
            $this->where('token_id',$token_id);
        } elseif($token_hash){
            $this->where('token_hash',$token_hash);
        } else {
            return 'notfound';
        }
        $expired_at=date('Y-m-d H:i:s',time());
        $this->where('expired_at>',$expired_at);
        $this->where('is_disabled',0);
        $this->permitWhere('r');
        return $this->get()->getRow();
    }

    public function itemActiveGet($owner_id,$token_holder,$token_holder_id){
        $expired_at=date('Y-m-d H:i:s',time());
        $this->where('expired_at>',$expired_at);
        $this->where('is_disabled',0);
        $this->where('token_holder',$token_holder);
        $this->where('token_holder_id',$token_holder_id);
        $this->permitWhere('r');
        return $this->limit(1)->get()->getRow();
    }

    public function itemAuth($token_hash,$token_holder){
        if($token_holder){
            $this->where('token_holder',$token_holder);
        }
        $this->where('token_hash',$token_hash);
        $expired_at=date('Y-m-d H:i:s',time());
        $this->where('expired_at>',$expired_at);
        $this->where('is_disabled',0);
        $token=$this->get()->getRow();
        if( $token ){
            $this->update($token->token_id,['accessed_at'=>date('Y-m-d H:i:s')]);
        }
        return $token;
    }
    
    public function itemCreate($owner_id,$token_holder,$token_holder_id){
        if( !($owner_id>0) ){
            return 'forbidden';
        }
        if($token_holder=='store'){
            $StoreModel=model('StoreModel');
            if( !$StoreModel->permit($token_holder_id,'w') ){
                return 'forbidden';
            }
        } else {
            return 'forbidden';
        }
        $validity_time=1*365*24*60*60;//1year
        $expired_at=date('Y-m-d H:i:s',time()+$validity_time);
        $token_hash = bin2hex(random_bytes(16));

        $this->allowedFields[]='owner_id';
        $token=[
            'token_hash'=>$token_hash,
            'token_holder'=>$token_holder,
            'token_holder_id'=>$token_holder_id,
            'expired_at'=>$expired_at,
            'owner_id'=>$owner_id,
        ];
        return $this->insert($token,true);
    }
    
    public function itemDelete($token_id=null,$token_hash=null){
        if($token_id){
            $this->where('token_id',$token_id);
        } elseif($token_hash){
            $this->where('token_hash',$token_hash);
        } else {
            return 'notfound';
        }
        $this->permitWhere('w');
        return $this->delete();
    }
    
    public function listGet($user_id=null,$token_holder=null){
        if($user_id){
            $this->where('owner_id',$user_id);
        } elseif($token_holder){
            $this->where('token_holder',$token_holder);
        }
        $this->permitWhere('r');
        return $this->get()->getResult();
    }
    
}