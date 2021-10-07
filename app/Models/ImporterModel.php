<?php
namespace App\Models;
use CodeIgniter\Model;

class ImporterModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'imported_list';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        "C1","C2","C3","C4","C5","C6","C7","C8","C9","C10","C11","C12","C13","C14","C15","C16",
        'owner_id',
        'holder'];

    protected $useSoftDeletes = false;
    protected $user_id=-1;
    
    public function __construct(\CodeIgniter\Database\ConnectionInterface &$db = null, \CodeIgniter\Validation\ValidationInterface $validation = null) {
        parent::__construct($db, $validation);
        $UserModel=model('UserModel');
        $this->user_id=session()->get('user_id');
        $user=$UserModel->itemGet($this->user_id);
        if( !isset($user->member_of_groups->group_types) || !str_contains($user->member_of_groups->group_types, 'supplier') ){
            http_response_code(403);
            die('User must be member of Supplier group');
        }
    }
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( $item, $holder ){
        $set=[];
        foreach($item as $i=>$value){
            $num=$i+1;
            if( $num>16 ){
                continue;
            }
            $set['C'.$num]=$value;
        }
        $set['holder']=$holder;
        $set['owner_id']=$this->user_id;
        $this->insert($set);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUpdate( $data ){
        if( empty($data->id) ){
            return 'noid';
        }
        if( !$this->permit($data->id,'w') ){
            return 'forbidden';
        }
        $this->update($data->id,$data);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet( $filter ){
        $this->filterMake($filter);
        $this->permitWhere('r');
        return $this->get()->getResult();
    }
    
    public function listCreate( $list ){
        
        
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete( $ids ){
        $this->permitWhere('w');
        $this->delete($ids,true);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    
    public function listAnalyse( $holder, $columns ){
        p($columns);
    }
}