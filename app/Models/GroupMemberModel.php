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
    
    public function tableSet( $table_name ){
        $allowed_tables=[
            'product_group_member_list',
            'store_group_member_list',
            'user_group_member_list'
        ];
        if( !in_array($table_name, $allowed_tables) ){
            throw new ErrorException('Trying to use unallowed group table name');
        }
        $parts=explode('_',$table_name);
        
        $this->table=$parts[0].'_group_member_list';
        $this->groupTable=$parts[0].'_group_list';
    }
    
    public function itemUpdate( $product_id, $group_id, $value ){
        if( $value ){
            return $this->join($product_id, $group_id);
        }
        return $this->leave($product_id, $group_id);
    }
    
    public function memberGroupsGet($member_id){
        return $this->select("GROUP_CONCAT({$this->groupTable}.group_id) group_ids,GROUP_CONCAT group_type) group_types")
                ->where('member_id',$member_id)
                ->join("{$this->groupTable}", "{$this->groupTable}.group_id = {$this->table}.group_id")
                ->get()->getRow();
    }
    
    public function joinByType($member_id,$member_group_type){
        $group_id=$this
                ->query("SELECT group_id FROM {$this->groupTable} WHERE group_type='$member_group_type'")
                ->getRow('group_id');
        return $this->join($member_id,$group_id);
    }
    
    public function join($member_id,$group_id){
        $this->permit(null,'w');
        return $this->insert(['member_id'=>$member_id,'group_id'=>$group_id]);
    }
    
    public function leave($member_id,$group_id){
        $this->permitWhere('w');
        return $this
                ->where('member_id',$member_id)
                ->where('group_id',$group_id)
                ->delete();
    }
}