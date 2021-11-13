<?php
namespace App\Models;
use CodeIgniter\Model;

class GroupMemberLayer extends Model{
        
    use PermissionTrait;

    protected $table="";
    protected $groupTable="";
    protected $primaryKey = 'group_id';
    protected $allowedFields = [
        'group_id',
        'member_id'
        ];
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    
    /**
     * 
     * @param type $table_name
     * @throws \ErrorException
     * @deprecated use specialized models instead
     */
    public function tableSet( $table_name ){
        $allowed_tables=[
            'order_group_member_list',
            'product_group_member_list',
            'store_group_member_list',
            'user_group_member_list'
        ];
        if( !in_array($table_name, $allowed_tables) ){
            throw new \ErrorException('Trying to use unallowed group table name');
        }
        $parts=explode('_',$table_name);
        
        $this->table=$parts[0].'_group_member_list';
        $this->groupTable=$parts[0].'_group_list';
    }
    
    public function itemUpdate( $member_id, $group_id, $is_joined, $leave_other_groups=false ){
        if( $is_joined ){
            return $this->joinGroup($member_id, $group_id, $leave_other_groups);
        }
        return $this->leaveGroup($member_id, $group_id);
    }
    
    public function memberOfGroupsGet($member_id){
        return $this->select("GROUP_CONCAT({$this->groupTable}.group_id) group_ids,GROUP_CONCAT(group_type) group_types")
                ->where('member_id',$member_id)
                ->join("{$this->groupTable}", "{$this->groupTable}.group_id = {$this->table}.group_id")
                ->get()->getRow();
    }
    
    public function memberOfGroupsListGet($member_id){
        return $this
                ->select("{$this->table}.*,group_name,group_type")
                ->where('member_id',$member_id)
                ->join("{$this->groupTable}", "{$this->groupTable}.group_id = {$this->table}.group_id")
                ->get()->getResult();
    }
    
    public function joinGroupByType($member_id,$member_group_type){
        $group_id=$this
                ->query("SELECT group_id FROM {$this->groupTable} WHERE group_type='$member_group_type'")
                ->getRow('group_id');
        return $this->joinGroup($member_id,$group_id);
    }
    
    public function joinGroup($member_id,$group_id,$leave_other_groups=false){
        if($leave_other_groups){
            $this->where('member_id',$member_id)->delete();
        }
        $created_by=session()->get('user_id');
        if($created_by<1){
            $created_by=null;
        }
        $this->where('member_id',$member_id)->where('group_id',$group_id)->delete();
        $this->insert(['member_id'=>$member_id,'group_id'=>$group_id,'created_by'=>$created_by]);
        return $this->affectedRows()?true:false;
    }
    
    public function leaveGroup($member_id,$group_id){
        //$this->permitWhere('w');
        return $this
                ->where('member_id',$member_id)
                ->where('group_id',$group_id)
                ->delete(true);
    }
}