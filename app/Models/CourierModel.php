<?php
namespace App\Models;
use CodeIgniter\Model;

class CourierModel extends Model{
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'courier_list';
    protected $primaryKey = 'courier_id';
    protected $allowedFields = [
        'courier_vehicle',
        'courier_tax_num',
        'current_order_id',
        'courier_comment',
        'deleted_at'
    ];
    protected $validationRules    = [
        'courier_tax_num'   => 'exact_length[0,10,12]'
    ];

    protected $useSoftDeletes = true;
    protected $selectList="
            courier_id,
            user_id,
            user_name,
            user_phone,
            user_avatar_name,
            courier_list.is_disabled,
            courier_list.deleted_at,
            group_name,
            status_icon.image_hash group_image_hash,
            courier_photo.image_hash courier_photo_image_hash,
            current_order_id,
            location_address";
   
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $courier_id=null ){
        if( $courier_id ){
            $this->where('courier_id',$courier_id);
        } else {
            $this->where('user_id',session()->get('user_id'));
        }
        $this->permitWhere('r');
        $this->select('courier_list.*,user_list.user_id,location_address,location_latitude,location_longitude');
        $this->join('user_list','user_id=courier_list.owner_id');
        $this->join('location_list','location_holder_id=courier_id AND is_main=1','left');
        $courier = $this->get()->getRow();
        
        if( !$courier ){
            return 'notfound';
        }
        $courier_id=$courier->courier_id;
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $courier->member_of_groups=$CourierGroupMemberModel->memberOfGroupsGet($courier_id);
        unset($courier->user_pass);
        $filter=[
            'image_holder'=>'courier',
            'image_holder_id'=>$courier->courier_id,
            'is_disabled'=>1,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>30
        ];
        $ImageModel=model('ImageModel');
        $courier->images=$ImageModel->listGet($filter);
        return $courier;  
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
        $courier=[
            'owner_id'=>$user_id
        ];
        
        $this->transStart();
        $UserGroupMemberModel->joinGroupByType($user_id,'courier');
        $courier_id=$this->insert($courier,true);
        $this->transComplete();
        return $courier_id;
    }
    
    public function itemUpdate( $courier ){
        if( empty($courier->courier_id) ){
            return 'noid';
        }
        $this->permitWhere('w');
        $this->update($courier->courier_id,$courier);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUpdateGroup($courier_id,$group_id,$is_joined){
        if( !$this->permit($courier_id,'w') ){
            return 'forbidden';
        }
        $GroupModel=model('CourierGroupModel');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'not_found';
        }
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $leave_other_groups=true;
        $ok=$CourierGroupMemberModel->itemUpdate( $courier_id, $group_id, $is_joined, $leave_other_groups );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }

    public function itemUpdateStatus($courier_id,$group_type){
        if( !$this->permit($courier_id,'w') ){
            return 'forbidden';
        }
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $leave_other_groups=true;
        $ok=$CourierGroupMemberModel->joinGroupByType( $courier_id, $group_type, $leave_other_groups );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }

    private function itemDeleteValidate($courier_id=null,$user_id=null){
        if($courier_id){
            $this->where('courier_id',$courier_id);
        }
        if($user_id){
            $this->where('owner_id',$user_id);
        }
        $courier = $this->get()->getRow();
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        if( $CourierGroupMemberModel->isMemberOf($courier->courier_id,'idle') ){
            return true;
        }
        return false;
    }
    
    public function itemDelete($courier_id=null,$user_id=null){
        $can_delete=$this->itemDeleteValidate($courier_id,$user_id);
        if( !$can_delete ){
            return 'invalid_status';
        }
        $this->permitWhere('w');
        if($courier_id){
            $this->where('courier_id',$courier_id);
        }
        if($user_id){
            $this->where('owner_id',$user_id);
        }

        $this->transStart();
        $UserGroupMemberModel=model('UserGroupMemberModel');
        $UserGroupMemberModel->leaveGroupByType($user_id,'courier');
        $this->delete();
        $result=$this->db->affectedRows()?'ok':'idle';
        $this->transComplete();
        return $result;
    }
    
    public function itemUnDelete( $courier_id ){
        if( !$this->permit($courier_id,'w') ){
            return 'forbidden';
        }

        $this->update($courier_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDisable( $courier_id, $is_disabled ){
        if( !$this->permit($courier_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['courier_id'=>$courier_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function isReadyCourier($courier_id=null){
        $isAdmin=sudo();
        if( $isAdmin ){
            return true;
        }
        $user=session()->get('user_data');
        $isCourier=str_contains($user->member_of_groups->group_types??'','courier');
        if( !$isCourier ){
            return false;
        }
        $this->permitWhere('r');
        $this->join('courier_group_member_list','member_id=courier_id');
        $this->join('courier_group_list','group_id');
        $this->where('group_type','ready');
        $this->where('courier_list.is_disabled','0');
        if($courier_id){
            $this->where('courier_id',$courier_id);
        } else {
            $this->where('courier_list.owner_id',$user->user_id);
        }
        return $this->get()->getRow('courier_id')?true:false;
    }

    public function listJobGet( $courier_id ){
        $isReadyCourier=$this->isReadyCourier();
        if( !$isReadyCourier ){
            //return [];
        }
        $point_distance=150000;//15km

        $LocationModel=model('LocationModel');
        $courier_location=$LocationModel->itemMainGet('courier', $courier_id);

        $LocationModel->select("store_id,store_name,store_time_preparation,'courier' user_role, 1 is_courier_job");
        $LocationModel->select("order_list.*,'' image_hash");//user_phone,user_name,
        $LocationModel->join('store_list',"location_holder_id=store_id AND is_main=1");
        $LocationModel->join('order_list','store_id=order_store_id');
        $LocationModel->join('order_group_member_list ogml','member_id=order_id');
        $LocationModel->join('order_group_list ogl','group_id');
        $LocationModel->where('group_type','delivery_search');

        $job_list=$LocationModel->distanceListGet( $courier_location->location_id, $point_distance, 'store' );
        if( !is_array($job_list) ){
            return 'not_found';
        }
        return $job_list;
    }

    public function itemJobGet( $order_id ){
        $isReadyCourier=$this->isReadyCourier();
        if( !$isReadyCourier ){
            return [];
        }
        $OrderModel=model("OrderModel");
        $LocationModel=model('LocationModel');

        $job=$OrderModel->where('order_id',$order_id)->get()->getRow();
        if( !$job ){
            return null;
        }
        $job->finish_location_address=$LocationModel->itemGet($job->order_finish_location_id??0)->location_address??'';
        $job->start_finish_distance=$LocationModel->distanceGet($job->order_start_location_id??0,$job->order_finish_location_id??0);
        return $job;
    }

    public function itemJobStart( $order_id, $courier_id ){
        $isReadyCourier=$this->isReadyCourier($courier_id);
        if( !$isReadyCourier ){
            return [];
        }



        $courier_user_id=$this->where($courier_id)->get()->getRow('owner_id');

        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderModel=model("OrderModel");

        $owner_ally_ids=$OrderModel->where('order_id',$order_id)->get()->getRow('owner_ally_ids');
        $this->transStart();
        $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');
        $OrderModel->update($order_id,['order_courier_id'=>$courier_id]);


        
        q($OrderModel);
        $result=$this->db->affectedRows()?'ok':'idle';
        $this->transComplete();
        return $result;
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
        $this->join('user_list','user_id=courier_list.owner_id');
        $this->join('courier_group_member_list','member_id=courier_id','left');
        $this->join('courier_group_list','group_id','left');
        $this->join('image_list status_icon',"status_icon.image_holder='user_group_list' AND status_icon.image_holder_id=group_id AND status_icon.is_main=1",'left');
        $this->orderBy("group_type='busy' DESC,group_type='ready' DESC,courier_group_member_list.created_at DESC");
        $this->join('image_list courier_photo',"courier_photo.image_holder='courier' AND courier_photo.image_holder_id=courier_id AND courier_photo.is_main=1",'left');
        $this->join('location_list','location_holder_id=courier_id AND location_list.is_main=1','left');
        $courier_list= $this->get()->getResult();
        return $courier_list;  
    }
    
    public function listNotify( $courier_status ){
        
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
        
        $courier_id=$image->image_holder_id;
        if( !$this->permit($courier_id,'w') ){
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
        
        $courier_id=$image->image_holder_id;
        if( !$this->permit($courier_id,'w') ){
            return 'forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
}