<?php
namespace App\Models;
use CodeIgniter\Model;

class CourierModel extends Model{
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'courier_list';
    protected $primaryKey = 'courier_id';
    protected $allowedFields = [
        'courier_name',
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
            courier_name,
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
        $this->select("(is_disabled=0 AND deleted_at IS NULL) is_active");
        $is_active=$this->where('courier_id',$courier_id)->get()->getRow('is_active');
        if( !$is_active && $group_type!='idle' ){
            return 'notactive';
        }
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $leave_other_groups=true;
        $ok=$CourierGroupMemberModel->joinGroupByType( $courier_id, $group_type, $leave_other_groups );
        if( $ok ){
            if($group_type=='ready'){
                $courier=$this->itemGet($courier_id,'basic');
                $notify_of_waiting_jobs_task=[
                    'task_name'=>"free the courier",
                    'task_programm'=>[
                            ['model'=>'CourierModel','method'=>'itemNotify','arguments'=>[$courier]]
                    ],
                    'task_next_start_time'=>time()+1
                ];
                jobCreate($notify_of_waiting_jobs_task);
            }
            return 'ok';
        }
        return 'error';
    }

    public function itemShiftOpen( $courier_id ){
        $courier=$this->itemGet($courier_id,'basic');
        if( !is_object($courier) ){
            return false;
        }
        $result=$this->itemUpdateStatus($courier_id,'ready');
        if( $result=='ok' ){
            $CourierShiftModel=model('CourierShiftModel');
            return $CourierShiftModel->itemOpen($courier_id,$courier->owner_id);
        }

        if( $result=='notactive' ){
            $message_text=view('messages/events/on_delivery_shift_notactive_sms');
        } else {
            $message_text=view('messages/events/on_delivery_shift_error_sms');
        }
        $message=(object)[
            'message_reciever_id'=>$courier->owner_id,
            'message_transport'=>'telegram',
            'message_text'=>$message_text,
            'message_data'=>[
                'title'=>'Смена не открыта',
                'body'=>$message_text,
            ]
        ];
        $sms_job=[
            'task_name'=>"Courier Shift opened msg send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'itemSend','arguments'=>[$message]]
                ],
        ];
        jobCreate($sms_job);
    }

    public function itemShiftClose( $courier_id ){
        $courier=$this->itemGet($courier_id,'basic');
        if( !is_object($courier) ){
            return false;
        }
        $result=$this->itemUpdateStatus($courier_id,'idle');
        if( $result=='ok' ){
            $CourierShiftModel=model('CourierShiftModel');
            return $CourierShiftModel->itemClose($courier_id,$courier->owner_id);
        }

        if( $result=='notactive' ){
            $message_text=view('messages/events/on_delivery_shift_notactive_sms');
        } else {
            $message_text=view('messages/events/on_delivery_shift_error_sms');
        }
        $message=(object)[
            'message_reciever_id'=>$courier->owner_id,
            'message_transport'=>'telegram',
            'message_text'=>$message_text,
            'message_data'=>[
                'title'=>'Смена не закрыта',
                'body'=>$message_text,
            ]
        ];
        $sms_job=[
            'task_name'=>"Courier Shift opened msg send",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'itemSend','arguments'=>[$message]]
                ],
        ];
        jobCreate($sms_job);
    }

    private function itemShiftStaleNotify($courier_id,$loc_last_updated){
        $courier=$this->itemGet($courier_id,'basic');
        if( !is_object($courier) ){
            return false;
        }
        $message_text=view('messages/events/on_delivery_shift_stale_sms',['courier'=>$courier,'loc_last_updated'=>$loc_last_updated]);
        $message=(object)[
            'message_reciever_id'=>"$courier->owner_id",
            'message_transport'=>'telegram',
            'message_text'=>$message_text,
            'message_data'=>[
                'title'=>'📡 Координаты не обновляются',
                'body'=>$message_text,
            ]
        ];

        $sms_job=[
            'task_name'=>"Courier Notify",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'itemSend','arguments'=>[$message]]
                ],
        ];
        jobCreate($sms_job);
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


    public function isCourierReady($courier_id=null){
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
    
    public function itemDelete($courier_id=null,$user_id=null){
        if(!sudo()){
            return 'forbidden';
        }
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


    public function listJobGet( $courier_id ){
        //courier should be able to preview jobs
        // $isCourierReady=$this->isCourierReady();
        // if( !$isCourierReady ){
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
        $LocationModel->where('TIMESTAMPDIFF(HOUR,ogml.created_at,NOW())<4');//only 3 hours

        $job_list=$LocationModel->distanceListGet( $courier_location->location_id, $point_distance, 'store' );
        if( !is_array($job_list) ){
            return 'notfound';
        }
        return $job_list;
    }

    public function itemJobGet( $order_id ){
        $isCourierReady=$this->isCourierReady();
        if( !$isCourierReady ){
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
        $isCourierReady=$this->isCourierReady($courier_id);
        if( !$isCourierReady ){
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
        $result=$this->itemUpdateStatus($courier_id,'busy');
        if( $result!='ok' ){
            $this->transRollback();
            return $result;
        }
        $this->transCommit();

        return $OrderModel->itemStageAdd( $order_id, 'delivery_found' );
    }

    public function itemJobTrack($order_id){
        $OrderModel=model("OrderModel");
        $LocationModel=model('LocationModel');

        $OrderModel->permitWhere('r');
        $OrderModel->join('courier_list','courier_id=order_courier_id','left');
        //$OrderModel->join('user_list','courier_list.owner_id=user_id','left');
        $OrderModel->join('location_list','courier_id=location_holder_id AND location_holder="courier" AND is_main=1','left');
        $OrderModel->join('order_group_list ogl','group_id=order_group_id');
        $OrderModel->join('image_list il','image_holder="courier" AND image_holder_id=courier_id','left');
        $OrderModel->select("order_start_location_id,order_finish_location_id,group_type");
        $OrderModel->select("courier_name,image_hash,location_id courier_location_id,location_latitude,location_longitude,TIMESTAMPDIFF(SECOND,location_list.updated_at,NOW()) location_age");
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
        $this->join('location_list',"location_holder='courier' AND location_holder_id=courier_id AND location_list.is_main=1",'left');
        $courier_list= $this->get()->getResult();
        return $courier_list;  
    }

    /**
     * Notifies particular courier of available jobs
     * @param object $courier the object of courier that is ready to send list of jobs
     */
    public function itemNotify( $courier ){
        if(!$courier){
            return;
        }
        $job_list=$this->listJobGet( $courier->courier_id );
        $context_list=[];
        foreach($job_list as $job){
            $context_list[]=[
                'store'=>(object)[
                    'store_id'=>$job->store_id,
                    'store_name'=>$job->store_name,
                ],
                'order'=>$job,
                'courier'=>$courier
            ];
        }
        return $this->listNotifyCreate($context_list);
    }
    
    /**
     * Notifies ready couriers of particular job
     * @param array $new_job array with data about job to send to couriers
     * @todo We should in future check the distance between store and courier!!!
     */
    public function listNotify( $new_job ){
        $ready_courier_list=$this->listGet(['status'=>'ready','limit'=>5,'order']);
        if( !$ready_courier_list ){
            return false;
        }
        $context_list=[];
        foreach($ready_courier_list as $courier){
            $new_job['courier']=$courier;
            $context_list[]=$new_job;
        }
        return $this->listNotifyCreate($context_list);
    }

    /**
     * Creates message send job from array of contexts
     *  @param array $context_list list of contexts containing courier and job info
     * 
     * 
     * 
     * 
     * 
     * should rewrite so jobs will be sent only if job is still not taken!!!
     */

    private function listNotifyCreate( array $context_list ){
        $notification_time_gap=3*60;//1min between notifications
        $notification_index=0;
        foreach($context_list as $context){
            $reciever_id=$context['courier']->owner_id??$context['courier']->user_id;
            $message_text=view('messages/order/on_delivery_search_COUR_sms',$context);
            $message=(object)[
                        'message_reciever_id'=>$reciever_id,
                        'message_transport'=>'message',
                        'message_text'=>$message_text,
                        'message_data'=>[
                            'type'=>'flash',
                            'title'=>'🚀 Новое задание',
                            'body'=>$message_text,
                            'link'=>getenv('app.frontendUrl').'order/order-list'
                        ],
                        'telegram_options'=>[
                            'buttons'=>[['',"onCourierJobStart-{$context['order']->order_id}",'🚀 Взять задание']],
                            'disable_web_page_preview'=>1,
                        ],
                    ];
            $next_start_time=time()+$notification_index*$notification_time_gap;
            $sms_job=[
                'task_name'=>"Courier Notify Order",
                'task_programm'=>[
                        ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[ [$message] ] ]
                    ],
                'task_next_start_time'=>$next_start_time
            ];
            jobCreate($sms_job);
            $notification_index++;
        }
        return true;
    }

    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }

    public function listPurge( $olderThan=1 ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }

    public function listIdleShiftClose(){
        $locationUnknownTimeoutMin=15;
        $shiftCloseMin=30;
        $this->join('courier_group_member_list','member_id=courier_id');
        $this->join('courier_group_list','group_id');
        $this->join('location_list',"location_holder='courier' AND location_holder_id=courier_id AND location_list.is_main=1");    
        $this->select('courier_id');
        $this->select("TIMESTAMPDIFF(MINUTE,location_list.updated_at, NOW()) loc_last_updated",false);
        $this->whereIn('group_type',['ready']);
        $this->having("loc_last_updated>={$locationUnknownTimeoutMin}");
        $idleCouriers=$this->get()->getResult();
        foreach($idleCouriers as $courier){
            if( $courier->loc_last_updated>=$shiftCloseMin ){
                $this->itemShiftClose($courier->courier_id);
                continue;
            }
            $this->itemShiftStaleNotify($courier->courier_id,$courier->loc_last_updated);
        }
    }

    public function hasActiveCourier( object $aroundLocation=null ){
        $this->select('group_type');
        $this->join('courier_group_member_list','member_id=courier_id');
        $this->join('courier_group_list','group_id');
        $this->whereIn('group_type',['ready','busy']);
        $this->orderBy('group_type','DESC');//if exists ready first
        $this->limit(1);

        if( $aroundLocation ){
            $aroundLocationRadius=15000;//maybe it should be different setting?
            $location_holder=$aroundLocation->location_holder;
            $location_holder_id=$aroundLocation->location_holder_id;
            $this->query("SET @start_point:=(SELECT location_point FROM location_list WHERE is_main=1 AND location_holder='$location_holder' AND location_holder_id='$location_holder_id')");
            $this->where("ST_Distance_Sphere(@start_point,location_point)<='$aroundLocationRadius'");
            $this->join('location_list',"location_holder='courier' AND location_holder_id=courier_id AND location_list.is_main=1");
        }
        return $this->get()->getRow('group_type')??0;
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