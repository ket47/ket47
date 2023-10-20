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
        'token_device',
        'expired_at',
        'accessed_at'
        ];

    protected $useSoftDeletes = false;
    protected $beforeInsert = ['hashToken'];
    protected $beforeUpdate = ['hashToken'];
    protected function hashToken(array $data){
        if ( isset($data['data']['token_hash']) ){
            $data['data']['token_hash'] = hash('sha256',$data['data']['token_hash']);
        }
        if ( isset($data['id']['token_hash']) ){
            $data['id']['token_hash'] = hash('sha256',$data['id']['token_hash']);
        }
        return $data;
    }
    
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

    public function itemAuth($token_hash,$token_holder=null){
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
    
    public function itemCreate($owner_id,$token_holder,$token_holder_id,$token_device=null,$token_hash_raw=null){
        if( !($owner_id>0) ){
            return 'forbidden';
        }
        if($token_holder=='store'){
            $StoreModel=model('StoreModel');
            if( !$StoreModel->permit($token_holder_id,'w') ){
                return 'forbidden';
            }
        }
        if($token_holder=='user'){
            //
        } else {
            return 'forbidden';
        }

        if( $token_hash_raw ){
            $token_hash=hash('sha256',$token_hash_raw);
            $token_id=$this->where('token_hash',$token_hash)->select('token_id')->get()->getRow('token_id');
            if($token_id){
                return [
                    'token_id'=>$token_id,
                    'token_hash_raw'=>$token_hash_raw
                ];
            }
        }
        if( !$token_hash_raw ){
            $token_hash_raw = bin2hex(random_bytes(16));
        }
        $validity_time=2*365*24*60*60;//1year
        $expired_at=date('Y-m-d H:i:s',time()+$validity_time);

        $this->allowedFields[]='owner_id';
        $token=[
            'token_hash'=>$token_hash_raw,
            'token_holder'=>$token_holder,
            'token_holder_id'=>$token_holder_id,
            'token_device'=>$token_device,
            'expired_at'=>$expired_at,
            'owner_id'=>$owner_id,
        ];
        $token_id=$this->insert($token,true);
        return [
            'token_id'=>$token_id,
            'token_hash_raw'=>$token_hash_raw
        ];
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

    // public function listDiscard($user_id,$token_holder){
    //     $this->where('token_holder',$token_holder);
    //     $this->where('owner_id',$user_id);
    //     $this->permitWhere('w');
    //     $this->delete();
    // }
    
}