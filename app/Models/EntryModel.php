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
        'deleted_at',
        'owner_id',
        'owner_ally_ids'
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = false;
    
    protected $validationRules    = [
        'entry_text'        => 'min_length[10]',
        'entry_price' => 'greater_than_equal_to[1]',
        //'entry_quantity' => 'greater_than_equal_to[1]'
    ];
    
    public function itemGet( $entry_id ){
        $this->permitWhere('r');
        $this->where('entry_id',$entry_id);
        return $this->get()->getRow();
    }
    
    
    private function itemEditAllow( $order ){
        if( !($order->user_role??null) ){
            return false;
        }
        if( $order->user_role=='customer' && $order->stage_current=='customer_cart' ){
            return true;
        }
        if( $order->user_role=='supplier' && $order->stage_current=='supplier_corrected' ){
            return true;
        }
        if( $order->user_role=='admin' && in_array($order->stage_current,['supplier_corrected','customer_cart']) ){
            return true;
        }
        return false;
    }
    
    public function itemCreate($order_id,$product_id,$product_quantity){
        $OrderModel=model('OrderModel');
        $ProductModel=model('ProductModel');
        $OrderModel->permitWhere('w');
        $order_basic=$OrderModel->itemGet($order_id,'basic');
        if( !$this->itemEditAllow( $order_basic ) ){
            return 'forbidden_at_this_stage';
        }
        $product_basic=$ProductModel->itemGet($product_id,'basic');
        if( !is_object($order_basic) || !is_object($product_basic) ){
            echo "order, product notfound or ";
            return 'forbidden';
        }
        if( !$product_quantity || $product_quantity<1 ){
            $product_quantity=1;
        }
        $new_entry=[
            'order_id'=>$order_id,
            'product_id'=>$product_id,
            'entry_text'=>"{$product_basic->product_name} {$product_basic->product_code}",
            'entry_quantity'=>$product_quantity,
            'entry_price'=>$product_basic->product_final_price,
            'owner_id'=>$order_basic->owner_id,
            'owner_ally_ids'=>$order_basic->owner_ally_ids
            ];
        try{
            $this->insert($new_entry);
            $entry_id=$this->db->insertID();
            return $entry_id;
        } catch( \Exception $e ){
            $this->where('order_id',$order_id);
            $this->where('product_id',$product_id);
            $this->set('entry_quantity',"$product_quantity",false);
            $this->update();
            
            
            $this->where('order_id',$order_id);
            $this->where('product_id',$product_id);
            $entry_id=$this->get()->getRow('entry_id');
            return $entry_id;
        }
    }
    
    public function itemUpdate( $entry ){
        if( !($entry->entry_id??0) ){
            return 'noentryid';
        }
        $this->permitWhere('w');
        $stock_check_sql="SELECT 
                product_quantity,
                entry_comment,
                order_id
            FROM
                order_entry_list
                    JOIN
                product_list USING (product_id)
            WHERE
                entry_id = '{$entry->entry_id}'";
        $stock=$this->query($stock_check_sql)->getRow();
        
        $OrderModel=model('OrderModel');
        $order_basic=$OrderModel->itemGet($stock->order_id,'basic');
        if( !$this->itemEditAllow( $order_basic ) ){
            return 'forbidden_at_this_stage';
        }
        if( isset($entry->entry_quantity) && $entry->entry_quantity>$stock->product_quantity){
            $entry->entry_comment= preg_replace('/\[.+\]/u', '', $stock->entry_comment);
            $entry->entry_comment.="[Количество уменьшено с {$entry->entry_quantity} до {$stock->product_quantity}]";
            $entry->entry_quantity=$stock->product_quantity;
        }
        $this->update($entry->entry_id,$entry);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete( $entry_id ){
        $entry=$this->itemGet($entry_id);
        $OrderModel=model('OrderModel');
        $order_basic=$OrderModel->itemGet($entry->order_id,'basic');
        if( !$this->itemEditAllow( $order_basic ) ){
            return 'forbidden_at_this_stage';
        }
        $this->permitWhere('w');
        $this->delete($entry_id);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemUnDelete( $entry_id ){
        $entry=$this->itemGet($entry_id);
        $OrderModel=model('OrderModel');
        $order_basic=$OrderModel->itemGet($entry->order_id,'basic');
        if( !$this->itemEditAllow( $order_basic ) ){
            return 'forbidden_at_this_stage';
        }
        $this->permitWhere('w');
        $this->update($entry_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    private $listGetSelectedFields="
        entry_id,
        order_id,
        product_id,
        entry_text,
        entry_quantity,
        entry_price,
        ROUND(entry_quantity*entry_price,2) entry_sum,
        image_hash,
        product_unit,
        product_quantity,
        product_quantity_min,
        is_produced,
        order_entry_list.deleted_at,
        entry_comment
    ";
    public function listGet( $order_id ){
        $this->permitWhere('r');
        $this->select($this->listGetSelectedFields);
        $this->where('order_id',$order_id);
        $this->join('image_list','image_holder_id=product_id AND image_holder="product" AND is_main=1','left');
        $this->join('product_list','product_id','left');
        $entries=$this->get()->getResult();
        return $entries;
    }
    
    public function listSumGet( $order_id ){
        $this->permitWhere('r');
        $this->select("SUM(ROUND(entry_quantity*entry_price,2)) order_sum_total");
        $this->where('order_id',$order_id);
        $this->where('deleted_at IS NULL');
        return $this->get()->getRow();
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate( $order_id, $entry_list ){
        foreach($entry_list as $entry){
            if(!$entry->product_id??0 || !$entry->entry_quantity??0){
                continue;
            }
            $this->itemCreate($order_id,$entry->product_id,$entry->entry_quantity);
        }
        return 'ok';
    }
    
    public function listDelete(){
        return false;
    }
    
    public function listDeleteChildren( $order_id ){
        $OrderModel=model('OrderModel');
        if( !$OrderModel->permit($order_id,'w') ){
            return 'forbidden';
        }
        $this->where('deleted_at IS NOT NULL OR is_disabled=1');
        $this->where('order_id',$order_id);
        $this->delete(null,true);
        
        $this->where('deleted_at IS NULL AND is_disabled=0');
        $this->where('order_id',$order_id);
        $this->delete();
    }
    
    public function listUnDeleteChildren( $order_id ){
        $OrderModel=model('OrderModel');
        if( !$OrderModel->permit($order_id,'w') ){
            return 'forbidden';
        }
        $olderStamp= new \CodeIgniter\I18n\Time("-".APP_TRASHED_DAYS." days");
        $this->where('deleted_at>',$olderStamp);
        $this->where('order_id',$order_id);
        $this->set('deleted_at',NULL);
        $this->update();
    }

    
}