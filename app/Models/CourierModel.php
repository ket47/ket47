<?php
namespace App\Models;
use CodeIgniter\Model;

class CourierModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'user_list';
    protected $primaryKey = 'user_id';
    protected $allowedFields = [

        ];

    protected $useSoftDeletes = true;
    protected $selectList="
            user_id,
            user_name,
            user_surname,
            user_phone,
            user_email,
            user_avatar_name,
            signed_in_at,
            signed_out_at,
            user_list.is_disabled,
            user_list.deleted_at,
            user_group_list.group_id,
            user_group_list.group_type,
            user_group_list.group_name";
    
    public function __construct(\CodeIgniter\Database\ConnectionInterface &$db = null, \CodeIgniter\Validation\ValidationInterface $validation = null) {
        parent::__construct($db, $validation);
        $this->parentGroupIdGet();
    }
    
    private $parent_group_id=0;
    private function parentGroupIdGet(){
        if( !$this->parent_group_id ){
            $this->parent_group_id=$this
                    ->query("SELECT group_id FROM user_group_list WHERE group_type='courier'")
                    ->getRow('group_id');
        }
        return $this->parent_group_id;
    }
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter ){
        $this->filterMake( $filter );
        $this->permitWhere('r');
        $this->select($this->selectList);
        $this->join('user_group_member_list','user_id=member_id');
        $this->join('user_group_list','group_id');
        $this->like('group_path_id',"/$this->parent_group_id/",'after');
        $this->orderBy("group_type='courier_busy' DESC,group_type='courier_ready' DESC");
        $user_list= $this->get()->getResult();
        return $user_list;  
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