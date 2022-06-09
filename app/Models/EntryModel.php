<?php
namespace App\Models;
use CodeIgniter\Model;
use Exception;

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

    protected $useSoftDeletes = false;
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
            
            if( $entry_id ){
                $this->listSumUpdate( $order_id );
            }
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
                order_id,
                is_counted
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
        if( $stock->is_counted && isset($entry->entry_quantity) && $entry->entry_quantity>$stock->product_quantity){
            $entry->entry_comment= preg_replace('/\[.+\]/u', '', $stock->entry_comment);
            $entry->entry_comment.="[Количество уменьшено с {$entry->entry_quantity} до {$stock->product_quantity}]";
            $entry->entry_quantity=$stock->product_quantity;
        }
        $this->update($entry->entry_id,$entry);
        $result=$this->db->affectedRows()>0?'ok':'idle';
        if( $result=='ok' && isset($entry->entry_quantity)){
            $this->listSumUpdate( $order_basic->order_id );
        }
        return $result;
    }
    
    public function itemDelete( $entry_id ){
        $entry=$this->itemGet($entry_id);
        if( !$entry ){
            return 'ok';
        }
        $OrderModel=model('OrderModel');
        $order_basic=$OrderModel->itemGet($entry->order_id,'basic');
        if( !$this->itemEditAllow( $order_basic ) ){
            return 'forbidden_at_this_stage';
        }
        $this->permitWhere('w');
        $this->delete($entry_id);
        $result=$this->db->affectedRows()>0?'ok':'idle';
        if( $result=='ok' ){
            $this->listSumUpdate( $entry->order_id );
        }
        return $result;
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
        product_quantity_reserved,
        is_counted,
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
        $this->select("SUM(ROUND(entry_quantity*entry_price,2)) order_sum_product");
        $this->where('order_id',$order_id);
        $this->where('deleted_at IS NULL');
        return $this->get()->getRow('order_sum_product');
    }

    public function listSumUpdate( $order_id ){
        $OrderModel=model('OrderModel');
        $order_sum_product=$this->listSumGet($order_id);
        $OrderModel->itemUpdate((object)[
            'order_id'=>$order_id,
            'order_sum_product'=>$order_sum_product
        ]);
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
        $this->listSumUpdate( $order_id );
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

    public $trimmedEntryCount=0;
    public function listStockMove( $order_id, $new_stock_status, $order_store_id=null ){
        $this->trimmedEntryCount=0;
        $OrderModel=model('OrderModel');
        if(!$order_store_id){
            $order_store_id=$OrderModel->select('order_store_id')->where('order_id',$order_id)->get()->getRow('order_store_id');
        }
        $this->db->transStart();
        if($new_stock_status=='free'){// reserved->free
            $OrderModel->permitWhere('w')->update($order_id,['order_stock_status'=>null]);
            $this->listStockReserve($order_store_id);
        } else 
        if($new_stock_status=='reserved'){// free->reserved
            $OrderModel->permitWhere('w')->update($order_id,['order_stock_status'=>'reserved']);
            $this->trimmedEntryCount=$this->listStockTrim($order_id);
            $this->listStockReserve($order_store_id);
        } else 
        if($new_stock_status=='commited'){// reserved->commited
            $OrderModel->permitWhere('w')->update($order_id,['order_stock_status'=>'commited']);
            $this->listStockCommit($order_id);
            $this->listStockReserve($order_store_id);
        } else {
            throw new Exception("Unknown stock status",500);
        }
        $this->db->transComplete();
        return $this->db->transStatus()?'ok':'fail';
    }

    private function listStockTrim(int $order_id){
        $sql="
            UPDATE
                product_list pl
                    JOIN
                order_entry_list oel USING(product_id)
            SET
                oel.entry_comment=CONCAT('[Количество уменьшено с ',oel.entry_quantity,' до ',(pl.product_quantity-pl.product_quantity_reserved),']'),
                oel.entry_quantity=pl.product_quantity-pl.product_quantity_reserved
            WHERE
                order_id='$order_id'
                AND oel.entry_quantity>pl.product_quantity-pl.product_quantity_reserved
                AND pl.is_counted=1
        ";
        $this->query($sql);
        return $this->db->affectedRows();
    }

    private function listStockReserve(int $store_id){
        $sql="
            UPDATE
                product_list pl
                    LEFT JOIN
                (SELECT 
                    product_id,
                    SUM(oel.entry_quantity) sum_quantity
                FROM
                    order_entry_list oel
                        JOIN
                    order_list ol USING(order_id)
                WHERE
                    ol.order_stock_status='reserved'
                    AND ol.order_store_id='$store_id'
                GROUP BY product_id
                ) rsv USING(product_id)
            SET
                pl.product_quantity_reserved=sum_quantity
            WHERE
                pl.is_counted=1
                AND store_id='$store_id'
        ";
        $this->query($sql);
        return $this->db->affectedRows();
    }

    private function listStockCommit(int $order_id){
        $sql="
            UPDATE
                product_list pl
                    JOIN
                order_entry_list oel USING(product_id)
            SET
                pl.product_quantity=pl.product_quantity-oel.entry_quantity
            WHERE
                order_id='$order_id'
                AND pl.is_counted=1
        ";
        $this->query($sql);
        return $this->db->affectedRows();        
    }
    
}