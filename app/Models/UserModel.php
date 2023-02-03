<?php
namespace App\Models;
use CodeIgniter\Model;

class UserModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'user_list';
    protected $primaryKey = 'user_id';
    protected $allowedFields = [
        'user_name',
        'user_surname',
        'user_middlename',
        'user_phone',
        'user_email',
        'user_pass',
        'user_avatar_name',
        'user_data',
        'deleted_at'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];
    
    protected $validationRules    = [
        'user_name'     => [
            //'label' =>'user_name',
            'rules' =>'required|min_length[3]',
            'errors'=>[
                'required'=>'required',
                'min_length'=>'short'
            ]
        ],
        'user_phone'    => [
            //'label' =>'user_phone',
            'rules' =>'required|numeric|exact_length[11]|is_unique[user_list.user_phone]',
            'errors'=>[
                'required'=>'required',
                'numeric'=>'invalid',
                'exact_length'=>'short',
                'is_unique'=>'notunique'
            ]
        ],
        'user_email'    => [
            //'label' =>'user_email',
            'rules' =>'if_exist|valid_email|is_unique[user_list.user_email]',
            'errors'=>[
                'valid_email'=>'invalid',
                'is_unique'=>'notunique'
            ]
        ],
        'user_pass'     => [
            'label' =>'user_pass',
            'rules' =>'required|min_length[4]',
            'errors'=>[
                'required'=>'required',
                'min_length'=>'short'
            ]
        ],
        'user_pass_confirm'     => [
            'label' =>'user_pass',
            'rules' =>'required_with[user_pass]|matches[user_pass]',
            'errors'=>[
                'required_with'=>'required',
                'matches'=>'notmatches'
            ]
        ]
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
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    private $itemCache=[];
    public function itemGet( $user_id, $mode='all' ){
        if( $user_id==-1 ){
            return $this->itemGetGuest();
        }
        if( $user_id==-100 ){
            return (object)[
                'user_id'=>-100,
                'user_name'=>'SYSTEM',
                'user_phone'=>'-',
                'member_of_groups'=>(object)[
                    'group_types'=>'admin'
                ],
                'location_main'=>model('LocationModel')->itemMainGet('default_location','-1')
            ];
        }
        if( $this->itemCache[$mode.$user_id]??0 ){
            return $this->itemCache[$mode.$user_id];
        }
        $this->permitWhere('r');
        $user= $this->where('user_id',$user_id)->get()->getRow();
        if( !$user ){
            return 'notfound';
        }
        unset($user->user_pass);
        if( $mode=='basic' ){
            $this->itemCache[$mode.$user_id]=$user;
            return $user;
        }

        $LocationModel=model('LocationModel');
        $UserGroupMemberModel=model('UserGroupMemberModel');
        $user->member_of_groups=$UserGroupMemberModel->memberOfGroupsGet($user_id);
        
        $user->location_main=$LocationModel->itemMainGet('user', $user_id);
        if(!$user->location_main){
            $user->location_main=$LocationModel->itemMainGet('default_location','-1');
        }
        if( $mode=='full' ){
            $CourierModel=model('CourierModel');
            $StoreModel=model('StoreModel');
            $user->storeList=$StoreModel->listGet([
                'owner_id'=>$user_id,
                'owner_ally_ids'=>$user_id
            ]);
            $user->courier=$CourierModel->itemGet();
        }
        $this->itemCache[$mode.$user_id]=$user;
        return $user;
    }

    private function itemGetGuest(){
        $LocationModel=model('LocationModel');
        $default_location=$LocationModel->itemMainGet('default_location','-1');
        return (object)[
            'user_id'=>-1,
            'user_name'=>'Guest',
            'user_phone'=>'-',
            'member_of_groups'=>(object)[
                'group_types'=>'guest'
            ],
            'location_main'=>$default_location
        ];
    }
    
    public function itemCreate( $user_data ){
        $this->transBegin();
            $user_id=$this->insert($user_data,true);
            if( $user_id ){
                $UserGroupMemberModel=model('UserGroupMemberModel');
                $UserGroupMemberModel->tableSet('user_group_member_list');
                $UserGroupMemberModel->joinGroupByType($user_id,'customer');
                $this->allowedFields[]='owner_id';
                $this->update($user_id,['owner_id'=>$user_id]);
            }
        $this->transCommit();
        return $user_id;        
    }
    
    public function itemUpdate( $data ){
        if( sudo() ){
            $this->protect(false);//allow all fields to be updated
        }
        if( isset($data->user_phone) ){
            $this->allowedFields[]='user_phone_verified';
            $data->user_phone_verified=0;
        }
        if( isset($data->user_email) ){
            $this->allowedFields[]='user_email_verified';
            $data->user_email_verified=0;
        }
        if( !$this->permit($data->user_id,'w') ){
            return 'forbidden';
        }
        $this->update(['user_id'=>$data->user_id],$data);
        $this->protect(true);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUpdateGroup($user_id,$group_id,$is_joined){
        if( !$this->permit($user_id,'w') ){
            return 'forbidden';
        }
        $GroupModel=model('UserGroupModel');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'notfound';
        }
        
        $allowed_group_types=['supplier','courier'];
        if( !in_array($target_group->group_type, $allowed_group_types) && !sudo() ){
            return 'forbidden';
        }
        if( $target_group->group_type=='courier' ){
            $CourierModel=model('CourierModel');
            if( $is_joined ){
                $CourierModel->itemCreate($user_id);
            } else {
                $CourierModel->itemDelete(null,$user_id);
            }
        }
        $UserGroupMemberModel=model('UserGroupMemberModel');
        $UserGroupMemberModel->itemUpdate( $user_id, $group_id, $is_joined );
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemDisable( $user_id, $is_disabled ){
        if( !$this->permit($user_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['user_id'=>$user_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( $id ){
        $this->permitWhere('w');
        return $this->delete(['user_id'=>$id]);
    }
    
    public function itemUnDelete( $user_id ){
        if( !$this->permit($user_id, 'w') ){
            return 'forbidden';
        }
        $this->update($user_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->orderBy('created_at','DESC');
        $this->permitWhere('r');
        $this->select("
            user_id,
            user_name,
            user_surname,
            user_middlename,
            user_phone,
            user_phone_verified,
            user_email,
            user_email_verified,
            user_avatar_name,
            is_disabled,
            signed_in_at,
            signed_out_at,
            created_at,
            updated_at,
            deleted_at");
        $user_list= $this->get()?->getResult();
        $UserGroupMemberModel=model('UserGroupMemberModel');
        foreach($user_list as $user){
            if($user){
                $user->member_of_groups=$UserGroupMemberModel->memberOfGroupsGet($user->user_id);
            }
        }
        return $user_list;        
    }
    
    public function listPurge( $olderThan=7 ){
        $olderStamp= new \CodeIgniter\I18n\Time((-1*$olderThan)." hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }


    /////////////////////////////////////////////////////
    //SYSTEM LOGIN SECTION
    /////////////////////////////////////////////////////
    private $systemUserPrecededId=-1;
    public function systemUserLogin(){
        $current_user_id=session()->get('user_id');
        if( $current_user_id==-100 ){
            return true;
        }
        $this->systemUserPrecededId=$current_user_id;
        $user=$this->itemGet( -100 );
        session_unset();
        session()->set('user_id',$user->user_id);
        session()->set('user_data',$user);
        if($user){
            $PermissionModel=model('PermissionModel');
            $PermissionModel->listFillSession();
        }
        return true;
    }
    public function systemUserLogout(){
        $current_user_id=session()->get('user_id');
        if( $current_user_id!=-100 ){
            return true;
        }
        $user=$this->itemGet( $this->systemUserPrecededId );
        session_unset();
        session()->set('user_id',$user->user_id??0);
        session()->set('user_data',$user);
        if($user){
            $PermissionModel=model('PermissionModel');
            $PermissionModel->listFillSession();
        }
        return true;
    }
    /////////////////////////////////////////////////////
    //USER HANDLING SECTION
    /////////////////////////////////////////////////////
    public function signUp($user_phone_cleared,$user_name,$user_pass,$user_pass_confirm,$user_email){
        if( $this->getUnverifiedUserIdByPhone($user_phone_cleared) ){
            return 'user_phone_unverified';
        }
        $user_data=[
            'user_phone'=>$user_phone_cleared,
            'user_name'=>$user_name,
            'user_pass'=>$user_pass,
            'user_pass_confirm'=>$user_pass_confirm,
            'is_disabled'=>0
            ];
        if( $user_email && strlen($user_email)>5 ){
            $user_data['user_email']=$user_email;
        }
        $user_id=$this->itemCreate($user_data);









        $user_sms=(object)[
            'message_transport'=>'message',
            'message_reciever_id'=>$user_id,
            'template'=>'messages/signup_welcome_sms.php',
            'context'=>$user_data
        ];
        $notification_task=[
            'task_name'=>"signup_welcome_sms",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$user_sms]]]
                ]
        ];
        jobCreate($notification_task);

        return $user_id;
    }
    
    public function signOut($user_id){
        if($user_id){
            $this->protect(false);
            $ok=$this->update($user_id,['signed_out_at'=>\CodeIgniter\I18n\Time::now()]);
            $this->protect(true);
            return $ok;
        }
        return false;
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
        if( $user->deleted_at ){
            return 'user_is_deleted';
        }

        $PermissionModel=model('PermissionModel');
        $PermissionModel->listFillSession();
        $this->protect(false)
                ->update($user->user_id,['signed_in_at'=>\CodeIgniter\I18n\Time::now()]);
        $this->protect(true);
        session()->set('user_id',$user->user_id);
        return 'ok' ;
    }
    
    public function getSignedUser(){
        return $this->itemGet( session()->get('user_id') );
    }
    
    public function getUnverifiedUserIdByPhone($user_phone_cleared){
        $this->where('user_phone',$user_phone_cleared);
        $this->where('user_phone_verified IS NULL OR user_phone_verified=0');
        $user_id = $this->get()->getRow('user_id');
        return $user_id;
    }
    
    public function verifyUser( $user_id ){
        $this->allowedFields[]='user_phone_verified';
        $ok=$this->update(['user_id'=>$user_id],['user_phone_verified'=>1]);
        if( $ok ){
            return 'verification_completed';
        }
        return 'verification_error';
    }
    
    public function passRecoveryCheckPhone($user_phone,$user_name){
         //should we send pass to only verified phone or it could be mechanism to generate pass?
         if(!$user_phone||!$user_name){
            return null;
        }
        return $this->where('user_phone',$user_phone)
                ->where('user_name',$user_name)
                ->get()->getRow('user_id');
    }
    
    public function passRecoveryCheckEmail($user_email,$user_name){
        //should we send pass to only verified phone or it could be mechanism to generate pass?
        if(!$user_email||!$user_name){
            return null;
        }
        return $this->where('user_email',$user_email)
                ->where('user_name',$user_name)
                ->get()->getRow('user_id');
    }
}