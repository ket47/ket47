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
            courier_list.updated_at,
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
    public function itemGet( $courier_id=null, $mode='all' ){
        if( $courier_id ){
            $this->where('courier_id',$courier_id);
        } else {
            $this->where('courier_list.owner_id',session()->get('user_id'));
        }
        $this->permitWhere('r');

        $this->select("{$this->table}.*,group_name status_name,group_type status_type");
        $this->join('courier_group_member_list','courier_id=member_id','left');
        $this->join('courier_group_list','group_id','left');
        if($mode=='basic'){
            return $this->get()->getRow();
        }
        $this->select('courier_list.*,location_address,location_latitude,location_longitude');
        $this->select('user_id,user_name,user_phone');
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
        $is_exists=$this->where('owner_id',$user_id)->get()->getRow('courier_id');
        if( $is_exists ){
            return 'exists';
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
        
        $this->transBegin();
            $UserGroupMemberModel->joinGroupByType($user_id,'courier');
            $courier_id=$this->insert($courier,true);
            $this->itemUpdateStatus($courier_id,'idle');
        $this->transCommit();
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
        $this->select("(is_disabled=1 OR deleted_at IS NOT NULL) notactive");
        $notactive=$this->where('courier_id',$courier_id)->get()->getRow('notactive');
        if( $notactive && $group_type!='idle' ){
            return 'notactive';
        }
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $leave_other_groups=true;
        $ok=$CourierGroupMemberModel->joinGroupByType( $courier_id, $group_type, $leave_other_groups );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }

    public function isIdle($courier_id=null,$user_id=null){
        if($courier_id){
            $this->where('courier_id',$courier_id);
        }
        if($user_id){
            $this->where('owner_id',$user_id);
        }
        $courier = $this->get()->getRow();
        if( !$courier ){
            return true;
        }
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        if( $CourierGroupMemberModel->isMemberOf($courier->courier_id,'idle') ){
            return true;
        }
        return false;
    }
    
    public function itemDelete($courier_id=null,$user_id=null){
        $can_delete=$this->isIdle($courier_id,$user_id);
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

        $this->transBegin();
        $UserGroupMemberModel=model('UserGroupMemberModel');
        $UserGroupMemberModel->leaveGroupByType($user_id,'courier');
        $this->delete();
        $result=$this->db->affectedRows()?'ok':'idle';
        $this->transCommit();
        return $result;
    }
    
    public function itemUnDelete( $courier_id ){
        if( !$this->permit($courier_id,'w') ){
            return 'forbidden';
        }
        $this->update($courier_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemPurge( $courier_id ){
        $can_delete=$this->isIdle($courier_id,null);
        if( !$can_delete ){
            return 'invalid_status';
        }
        $this->delete($courier_id,true);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDisable( $courier_id, $is_disabled ){
        $can_delete=$this->isIdle($courier_id,null);
        if( !$can_delete ){
            return 'invalid_status';
        }
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
        //courier should be able to preview jobs
        // $isReadyCourier=$this->isReadyCourier();
        // if( !$isReadyCourier ){
        //     return 'notready';
        // }
        $point_distance=getenv('delivery.radius');

        $LocationModel=model('LocationModel');
        $courier_location=$LocationModel->itemMainGet('courier', $courier_id);
        if(!$courier_location){
            return 'courier_location_required';
        }

        $LocationModel->select("location_latitude,location_longitude");
        $LocationModel->select("store_id,store_name,store_time_preparation,'courier' user_role, 1 is_courier_job");
        $LocationModel->select("order_list.*,'' image_hash");//user_phone,user_name,
        $LocationModel->join('store_list',"location_holder_id=store_id AND is_main=1");
        $LocationModel->join('order_list','store_id=order_store_id');
        $LocationModel->join('order_group_member_list ogml','member_id=order_id');
        $LocationModel->join('order_group_list ogl','group_id');
        $LocationModel->where('group_type','delivery_search');
        $LocationModel->where('TIMESTAMPDIFF(HOUR,order_list.created_at,NOW())<4');//only 3 hours

        $job_list=$LocationModel->distanceListGet( $courier_location->location_id, $point_distance, 'store' );
        if( !is_array($job_list) ){
            return 'notfound';
        }
        return $job_list;
    }

    public function itemJobGet( $order_id ){
        $isReadyCourier=$this->isReadyCourier();
        if( !$isReadyCourier ){
            return 'notready';
        }
        $OrderModel=model("OrderModel");
        $LocationModel=model('LocationModel');

        $job=$OrderModel->where('order_id',$order_id)->get()->getRow();
        if( !$job ){
            return 'notfound';
        }
        $job->finish_location_address=$LocationModel->itemGet($job->order_finish_location_id??0)->location_address??'';
        $job->start_finish_distance=$LocationModel->distanceGet($job->order_start_location_id??0,$job->order_finish_location_id??0);
        return $job;
    }

    public function itemJobStart( $order_id, $courier_id ){
        $isReadyCourier=$this->isReadyCourier($courier_id);
        if( !$isReadyCourier ){
            return 'notready';
        }
        $OrderGroupMemberModel=model('OrderGroupMemberModel');
        $OrderModel=model("OrderModel");

        $this->transBegin();
        $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');
        $was_searching=$this->db->affectedRows()?true:false;
        if( !$was_searching ){
            $this->transRollback();
            return 'notsearching';
        }
        $courier=$this->itemGet($courier_id,'basic');
        $OrderModel->update($order_id,(object)['order_courier_id'=>$courier_id,'order_courier_admins'=>$courier->owner_id]);
        $OrderModel->itemUpdateOwners($order_id);
        $this->transCommit();
        return 'ok';
    }

    public function itemJobTrack($order_id){
        $OrderModel=model("OrderModel");
        $LocationModel=model('LocationModel');

        $OrderModel->permitWhere('r');
        $OrderModel->join('courier_list','courier_id=order_courier_id','left');
        $OrderModel->join('user_list','courier_list.owner_id=user_id','left');
        $OrderModel->join('location_list','courier_id=location_holder_id AND location_holder="courier" AND is_main=1','left');
        $OrderModel->join('order_group_list ogl','group_id=order_group_id');
        $OrderModel->select("order_start_location_id,order_finish_location_id,group_type");
        $OrderModel->select("user_list.user_name as courier_name,location_id courier_location_id");
        $OrderModel->where('order_id',$order_id);
        //$OrderModel->where('group_type','delivery_start');
        $job=$OrderModel->get()->getRow();
        if( !$job ){
            return 'notfound';
        }
        $job->courier_finish_distance=  $LocationModel->distanceGet($job->courier_location_id??0,$job->order_finish_location_id??0);
        $job->start_finish_distance=    $LocationModel->distanceGet($job->order_start_location_id??0,$job->order_finish_location_id??0);
        return $job;
    }

    
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter ){
        $this->filterMake( $filter,false );
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
    
    public function listNotify( $context ){
        ///////////////////////////////////////////////////
        //CREATING READY COURIERS NOTIFICATIONS
        ///////////////////////////////////////////////////
        $this->where("TIMESTAMPDIFF(HOUR,courier_group_member_list.created_at, NOW())<13");//at maximum notifications during 13 hours
        $ready_courier_list=$this->listGet(['status'=>'ready','limit'=>5,'order']);
        if( !$ready_courier_list ){
            return false;
        }
        $messages=[];
        foreach($ready_courier_list as $courier){
            $context['courier']=$courier;
            $message_text=view('messages/order/on_delivery_search_COUR_sms',$context);
            $messages[]=(object)[
                        'message_reciever_id'=>$courier->user_id,
                        'message_transport'=>'message',
                        'message_text'=>$message_text,
                        'message_data'=>[
                            'type'=>'flash',
                            'title'=>'🚀 Новое задание',
                            'body'=>$message_text,
                            'link'=>getenv('app.frontendUrl').'order/order-list'
                        ],
                        'telegram_options'=>[
                            'buttons'=>[['',"onCourierJobStart-{$context['order']->order_id}",'🚀 Взять задание']]
                        ],
                    ];
        }
        $sms_job=[
            'task_name'=>"Courier Notify Order",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[$messages]]
                ],
        ];
        jobCreate($sms_job);
        return true;
    }
    
    
    
    
    
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
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