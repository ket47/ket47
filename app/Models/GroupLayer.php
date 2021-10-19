<?php
namespace App\Models;
use CodeIgniter\Model;

class GroupLayer extends Model{
            
    use PermissionTrait;
    use FilterTrait;

    protected $table      = '';
    protected $primaryKey = 'group_id';
    protected $allowedFields = [
        'group_parent_id',
        'group_name',
        'group_type',
        'group_path_id'
        ];
    
    public function tableSet( $table_name ){
        $allowed_tables=[
            'product_group_list',
            'store_group_list',
            'user_group_list'
        ];
        if( !in_array($table_name, $allowed_tables) ){
            throw new \ErrorException('Trying to use unallowed group table name '.$table_name);
        }
        $this->table=$table_name;
    }
    
    public function listGet( $filter=null ){
        $this->permitWhere('r');
        $this->filterMake($filter);
        if( $filter['level']??0==2 ){
            $this->where('group_parent_id IS NOT NULL AND group_parent_id<>0');
        }
        $this->orderBy('group_path');
        return $this->get()->getResult();
    } 
    public function itemGet( $group_id ){
        $this->permitWhere('r');
        return $this->where('group_id',$group_id)->get()->getRow();
    }
    
    public function itemCreate( int $parent_id, string $group_name, string $group_type=null ){
        if( !sudo() ){
            return 'forbidden';
        }
        $parent_parent_id=$this->where('group_id',$parent_id)->get()->getRow('parent_id');
        if( $parent_parent_id!=0 ){
            return 'only_two_levels_allowed';
        }
        $group_name=str_replace('/', '', $group_name);
        $group_id=$this->insert([
            'group_parent_id'=>$parent_id,
            'group_name'=>$group_name,
            'group_type'=>$group_type,
            'owner_id'=>session()->get('user_id')
        ],true);
        if( $group_id ){
            $this->itemPathUpdate( $group_id );
        }
        return $group_id;
    }
    
    public function itemUpdate( $group ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( isset($group->group_parent_id) ){
            $parent_parent_id=$this->where('group_id',$group->group_parent_id)->get()->getRow('parent_id');
            if( $parent_parent_id!=0 ){
                return 'only_two_levels_are_allowed';
            }
        }
        if( empty($group->group_id) ){
            return 'noid';
        }
        if( !empty($group->group_name) ){
            $group->group_name=str_replace('/', '', $group->group_name);
        }
        try{
            $this->update($group->group_id,$group);
        } catch(\Exception $e){
            p($e);
        }
        $result=$this->db->affectedRows()?'ok':'idle';
        
        if( isset($group->group_parent_id) || isset($group->group_name) ){
            $this->itemPathUpdate();
        }
        return $result;
    }
    
    private function itemPathUpdate(){
        $path_tmp_sql="
            CREATE TEMPORARY TABLE tmp_group_path AS(
            SELECT 
                gl.group_id,
                COALESCE(CONCAT(COALESCE(gl_parent.group_id,gl_parent.group_id),'/',gl.group_id),gl.group_id) group_path_id,
                COALESCE(CONCAT(COALESCE(gl_parent.group_name,gl_parent.group_name),'/',gl.group_name),gl.group_name) group_path
            FROM
                $this->table gl
                    LEFT JOIN
                $this->table gl_parent ON gl.group_parent_id = gl_parent.group_id
            ORDER BY
                    gl.group_parent_id,
                gl.group_id)
            ";
        $path_update_sql="
            UPDATE
                $this->table gl
                    JOIN
                tmp_group_path tgp USING(group_id)
            SET
                gl.group_path_id=CONCAT('/',tgp.group_path_id,'/'),
                gl.group_path=CONCAT('/',tgp.group_path,'/')
            ";
        $this->query($path_tmp_sql);
        $this->query($path_update_sql);
    }
    
    public function itemDelete( $group_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->like('group_path_id',"/$group_id/")->delete(true);
        return $this->db->affectedRows()?'ok':'idle';
    }
}