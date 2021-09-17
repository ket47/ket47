<?php
namespace App\Models;
use CodeIgniter\Model;

class GroupModel extends Model{
            
    use PermissionTrait;
    use FilterTrait;

    protected $table      = 'product_group_list';
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
            throw new ErrorException('Trying to use unallowed group table name');
        }
        $this->table=$table_name;
    }
    
    public function listGet( $filter=null ){
        $this->permitWhere('r');
        $this->filterMake($filter);
        return $this->get()->getResult();
    }
    
    
    
    
    
    
    
    
    
    
    public function itemGet( $group_id ){
        $this->permitWhere('r');
        return $this->where('group_id',$group_id)->get()->getRow();
    }
    
    public function itemCreate( int $parent_id, string $group_name, string $group_type=null ){
        if( !sudo() ){
            return 'item_create_forbidden';
        }
        $parent_parent_id=$this->where('group_id',$parent_id)->get()->getRow('parent_id');
        if( $parent_parent_id!=0 ){
            return 'only_two_levels_allowed';
        }
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
    
    public function itemUpdate( $group_id, $field, $value ){
        $this->permitWhere('w');
        if( $field=='group_parent_id' ){
            $parent_parent_id=$this->where('group_id',$value)->get()->getRow('parent_id');
            if( $parent_parent_id!=0 ){
                return 'only_two_levels_allowed';
            }
        }
        $ok=$this->update(['group_id'=>$group_id],[$field=>$value]);
        if( $field=='group_parent_id' ){
            $this->itemPathUpdate( $group_id );
        }
        return $ok;
    }
    
    private function itemPathUpdate( $group_id ){
        $sql="
            UPDATE
                $this->table
            SET
                group_path_id=CONCAT(group_parent_id,',',group_id)
            WHERE
                group_id='$group_id'
            ";
        $this->query($sql);
    }
    
    public function itemDelete( $group_id ){
        $this->permitWhere('w');
        return $this->where('group_id',$group_id)->delete();
    }
}