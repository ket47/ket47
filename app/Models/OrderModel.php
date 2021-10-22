<?php
namespace App\Models;
use CodeIgniter\Model;

class OrderModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'order_list';
    protected $primaryKey = 'order_id';
    protected $allowedFields = [

        ];

    protected $useSoftDeletes = true;
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( $entry_list,$store_id=0 ){
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }

        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
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