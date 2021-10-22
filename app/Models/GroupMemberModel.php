<?php
namespace App\Models;
use CodeIgniter\Model;

class GroupMemberModel extends Model{
        
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

        try{
            $this->insert(['member_id'=>$member_id,'group_id'=>$group_id],true);
            return $this->affectedRows()?true:false;
        }
        catch (\Exception $e){
            return true;//duplicate key
        }
    }
    
    public function leaveGroup($member_id,$group_id){
        //$this->permitWhere('w');
        return $this
                ->where('member_id',$member_id)
                ->where('group_id',$group_id)
                ->delete(true);
    }
}