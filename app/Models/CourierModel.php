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
        'deleted_at'
    ];
    protected $validationRules    = [
        'courier_name'        => 'min_length[3]',
        'courier_description' => 'min_length[10]',
        'courier_tax_num'     => 'exact_length[10,12]|integer'
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
            image_hash group_image_hash,
            current_order_id";
   
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $courier_id ){
        $this->permitWhere('r');
        if( !$this->permit($courier_id,'r') ){
            return 'forbidden';
        }
        $this->select('courier_list.*,user_list.user_id');
        $this->where('courier_id',$courier_id);
        $this->join('user_list','user_id=courier_list.owner_id');
        $courier = $this->get()->getRow();
        $CourierGroupMemberModel=model('CourierGroupMemberModel');
        $courier->member_of_groups=$CourierGroupMemberModel->memberOfGroupsGet($courier_id);
        unset($courier->user_pass);
        
        if( !$courier ){
            return 'notfound';
        }
        return $courier;  
    }
    
    public function itemCreate($user_id){
        $UserModel=model('UserModel');
        if( !$UserModel->permit($user_id,'w') || !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $this->allowedFields[]='owner_id';
        $courier=[
            'owner_id'=>$user_id
        ];
        $this->insert($courier);
        return $this->db->insertID();
    }
    
    public function itemUpdate(){
        return false;
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
    
    public function itemDelete($courier_id=null,$user_id=null){
        $this->permitWhere('w');
        if($courier_id){
            $this->where('courier_id',$courier_id);
        }
        if($user_id){
            $this->where('owner_id',$user_id);
        }        
        $this->delete();
        return $this->db->affectedRows()?'ok':'idle';
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
        $this->join('image_list',"image_holder='user_group_list' AND image_holder_id=group_id AND is_main=1",'left');
        $this->orderBy("group_type='busy' DESC,group_type='ready' DESC,courier_group_member_list.created_at DESC");
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
    
}