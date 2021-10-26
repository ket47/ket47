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
        'entry_comment',
        'owner_id',
        'owner_ally_ids'
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = false;
    
    protected $validationRules    = [
        'entry_text'        => 'min_length[10]',
        'entry_price' => 'greater_than_equal_to[1]',
        'entry_quantity' => 'greater_than_equal_to[1]'
    ];
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate($order_id,$product_id){
        $OrderModel=model('OrderModel');
        $ProductModel=model('ProductModel');
        $OrderModel->permitWhere('w');
        $order_basic=$OrderModel->itemGet($order_id,'basic');
        $product_basic=$ProductModel->itemGet($product_id,'basic');
        if( !is_object($order_basic) || !is_object($product_basic) ){
            echo "order, product notfound or ";
            return 'forbidden';
        }
        
        $new_entry=[
            'order_id'=>$order_id,
            'product_id'=>$product_id,
            'entry_text'=>"{$product_basic->product_name} {$product_basic->product_code}",
            'entry_quantity'=>1,
            'entry_price'=>$product_basic->product_final_price,
            'owner_id'=>$order_basic->owner_id,
            'owner_ally_ids'=>$order_basic->owner_ally_ids
            ];
        $this->insert($new_entry);
        $entry_id=$this->db->insertID();
        return $entry_id;
    }
    
    public function itemUpdate( $entry ){
        $this->permitWhere($entry->order_entry_id,'w');
        $this->update($entry->order_entry_id,$entry);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete( $order_entry_id ){
        $this->permitWhere($order_entry_id,'w');
        $this->delete($order_entry_id);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function listGet( $order_id ){
        $this->permitWhere('r');
        $this->where('order_id',$order_id);
        $entries=$this->get()->getResult();
        return $entries;
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