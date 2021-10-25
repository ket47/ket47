<?php
namespace App\Models;
use CodeIgniter\Model;

class EntryModel extends Model{
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'order_entry_list';
    protected $primaryKey = 'entry_id';
    protected $allowedFields = [
        'order_id',
        'product_id',
        'entry_text',
        'entry_quantity',
        'entry_self_price',
        'entry_price',
        'entry_comment'
        ];

    protected $useSoftDeletes = false;
    
    
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
    
    public function listGet( $order_id ){
        $this->permitWhere('r');
        $this->where('order_id',$order_id);
        return $this->get()->getResult();
    }
    
    public function listCount( $order_id ){
        return [
            'self'=>0,
            'profit'=>0,
            'tax'=>0,
            'total'=>0
        ];
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate( $order_id, $entry_list ){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}