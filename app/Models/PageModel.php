<?php
namespace App\Models;
use CodeIgniter\Model;

class PageModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'page_list';
    protected $primaryKey = 'page_id';
    protected $allowedFields = [
            'page_title',
            'page_name',
            'page_content'
        ];

    protected $useSoftDeletes = false;
    
    
    public function itemGet($page_id=null,$page_name=null){
        if($page_id){
            $this->where('page_id',$page_id);
        } else if($page_name){
            $this->where('page_name',$page_name);
        } else {
            return 'notfound';
        }
        $page=$this->get()->getRow();
        if(!$page){
            return 'notfound';
        }
        return $page;
    }
    
    public function itemCreate( $data ){
        if( !sudo() ){
            return 'forbidden';
        }
        return $this->insert($data,true);
    }
    
    public function itemUpdate($data){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->save($data);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete($page_id=null,$page_name=null){
        if($page_id){
            $this->where('page_id',$page_id);
        } else if($page_name){
            $this->where('page_name',$page_name);
        } else {
            return 'notfound';
        }
        if( !sudo() ){
            return 'forbidden';
        }
        $this->delete();
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function listGet(){
        return $this->get()->getResult();
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