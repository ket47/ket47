<?php
namespace App\Models;
use CodeIgniter\Model;

class LocationModel extends Model{
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'location_list';
    protected $primaryKey = 'location_id';
    protected $allowedFields = [
        'location_vehicle',
        'location_tax_num',
        'current_order_id',
        'deleted_at'
    ];
    protected $validationRules    = [
        'location_tax_num'   => 'exact_length[0,10,12]',
        'owner_id'          => 'is_unique[location_list.owner_id]'
    ];

    protected $useSoftDeletes = true;
    protected $selectList="
            location_id,
            user_id,
            user_name,
            user_phone,
            user_avatar_name,
            location_list.is_disabled,
            location_list.deleted_at,
            group_name,
            status_icon.image_hash group_image_hash,
            location_photo.image_hash location_photo_image_hash,
            current_order_id";
   
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $location_id ){
        $this->permitWhere('r');
        if( !$this->permit($location_id,'r') ){
            return 'forbidden';
        }
        $this->select('location_list.*,user_list.user_id');
        $this->where('location_id',$location_id);
        $this->join('user_list','user_id=location_list.owner_id');
        $location = $this->get()->getRow();
        $LocationGroupMemberModel=model('LocationGroupMemberModel');
        $location->member_of_groups=$LocationGroupMemberModel->memberOfGroupsGet($location_id);
        unset($location->user_pass);
        
        if( !$location ){
            return 'notfound';
        }
        $filter=[
            'image_holder'=>'location',
            'image_holder_id'=>$location->location_id,
            'is_disabled'=>1,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>30
        ];
        $ImageModel=model('ImageModel');
        $location->images=$ImageModel->listGet($filter);
        return $location;  
    }
    
    public function itemCreate($user_id){
        if(!$user_id){
            return 'notfound';
        }
        
        $UserModel=model('UserModel');
        $UserGroupMemberModel=model('UserGroupMemberModel');
        if( !$UserModel->permit($user_id,'w') || !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $this->allowedFields[]='owner_id';
        $location=[
            'owner_id'=>$user_id
        ];
        
        $this->transStart();
        $UserGroupMemberModel->joinGroupByType($user_id,'location');
        $location_id=$this->insert($location,true);
        $this->transComplete();
        return $location_id;
    }
    
    public function itemUpdate( $location ){
        if( empty($location->location_id) ){
            return 'noid';
        }
        $this->permitWhere('w');
        $this->update($location->location_id,$location);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUpdateGroup($location_id,$group_id,$is_joined){
        if( !$this->permit($location_id,'w') ){
            return 'forbidden';
        }
        $GroupModel=model('LocationGroupModel');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'not_found';
        }
        $LocationGroupMemberModel=model('LocationGroupMemberModel');
        $leave_other_groups=true;
        $ok=$LocationGroupMemberModel->itemUpdate( $location_id, $group_id, $is_joined, $leave_other_groups );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    
    public function itemDelete($location_id=null,$user_id=null){
        $this->permitWhere('w');
        if($location_id){
            $this->where('location_id',$location_id);
        }
        if($user_id){
            $this->where('owner_id',$user_id);
        }
        $this->transStart();
        $UserGroupMemberModel=model('UserGroupMemberModel');
        $UserGroupMemberModel->leaveGroupByType($user_id,'location');
        $this->delete();
        $this->transComplete();
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUnDelete( $location_id ){
        if( !$this->permit($location_id,'w') ){
            return 'forbidden';
        }

        $this->update($location_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDisable( $location_id, $is_disabled ){
        if( !$this->permit($location_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['location_id'=>$location_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter ){
        $this->filterMake( $filter );
        if( $filter['status']??0 ){
            $this->where('group_type',$filter['status']);
        }
        $this->permitWhere('r');
        $this->select($this->selectList);
        $this->join('user_list','user_id=location_list.owner_id');
        $this->join('location_group_member_list','member_id=location_id','left');
        $this->join('location_group_list','group_id','left');
        $this->join('image_list status_icon',"status_icon.image_holder='user_group_list' AND status_icon.image_holder_id=group_id AND status_icon.is_main=1",'left');
        $this->orderBy("group_type='busy' DESC,group_type='ready' DESC,location_group_member_list.created_at DESC");
        $this->join('image_list location_photo',"location_photo.image_holder='location' AND location_photo.image_holder_id=location_id AND location_photo.is_main=1",'left');
        $location_list= $this->get()->getResult();
        return $location_list;  
    }
    
    public function listNotify( $location_status ){
        
    }
    
    
    
    
    
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $data ){
        $data['is_disabled']=1;
        $data['owner_id']=session()->get('user_id');
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemCreate($data,1);
        }
        return 0;
    }

    public function imageUpdate( $data ){
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemUpdate($data);
        }
        return 0;
    }
    
    public function imageDisable( $image_id, $is_disabled ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');
        $ok=$ImageModel->itemDisable( $image_id, $is_disabled );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }    
    
    public function imageDelete( $image_id ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $location_id=$image->image_holder_id;
        if( !$this->permit($location_id,'w') ){
            return 'forbidden';
        }
        $ImageModel->itemDelete( $image_id );
        $ok=$ImageModel->itemPurge( $image_id );
        if( $ok ){
            return 'ok';
        }
        return 'idle';
    }
    
    public function imageOrder( $image_id, $dir ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $location_id=$image->image_holder_id;
        if( !$this->permit($location_id,'w') ){
            return 'forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
}