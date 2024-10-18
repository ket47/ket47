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
        'entry_quantity',
        'entry_comment',
        'entry_discount',
        ];

    protected $useSoftDeletes = false;
    protected $useTimestamps = false;
    
    protected $validationRules    = [
        'entry_text'  => 'min_length[5]',
        'entry_price' => 'greater_than_equal_to[1]',
        //'entry_quantity' => 'greater_than_equal_to[1]'
    ];

    protected function initialize(){
        $this->query("SET character_set_results = utf8mb4, character_set_client = utf8mb4, character_set_connection = utf8mb4,  character_set_server = utf8mb4");
    }
    public function itemGet( $entry_id ){
        $this->permitWhere('r');
        $this->where('entry_id',$entry_id);
        return $this->get()->getRow();
    }
    
    
    private function itemEditAllow( $order ){
        return $this->itemAtCart($order) || $this->itemAtCorrection($order);
    }

    private function itemAtCorrection( $order ){
        if( ($order->user_role??null) && in_array($order->user_role,['supplier','admin']) && $order->stage_current=='supplier_corrected' ){
            return true;
        }
        return false;
    }

    private function itemAtCart( $order ){
        if( ($order->user_role??null) && in_array($order->user_role,['customer','admin']) && $order->stage_current=='customer_cart' ){
            return true;
        }
        return false;
    }
    
    public function itemCreate($order_id,$product_id,$product_quantity,$entry_comment=null){//item on duplicate key update
        $OrderModel=model('OrderModel');
        $ProductModel=model('ProductModel');
        $OrderModel->permitWhere('w');
        $product_basic=$ProductModel->itemGet($product_id,'basic');
        $order_basic=$OrderModel->itemGet($order_id,'basic');
        if( !$this->itemEditAllow( $order_basic ) ){
            return 'forbidden_at_this_stage';
        }
        if( !is_object($order_basic) || !is_object($product_basic) ){
            return 'forbidden';
        }
        if( !$product_quantity || $product_quantity<0 ){
            $product_quantity=1;
        }

        $entry_text="{$product_basic->product_name} {$product_basic->product_code}";
        if($product_basic->product_option){
            $entry_text.=" [{$product_basic->product_option}]";
        }
        $this->allowedFields[]='order_id';
        $this->allowedFields[]='product_id';
        $this->allowedFields[]='entry_text';
        $this->allowedFields[]='entry_price';
        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='owner_ally_ids';
        $new_entry=[
            'order_id'=>$order_id,
            'product_id'=>$product_id,
            'entry_text'=>$entry_text,
            'entry_quantity'=>$product_quantity,
            'entry_price'=>$product_basic->product_final_price,
            'entry_comment'=>$entry_comment,
            'owner_id'=>$order_basic->owner_id,
            'owner_ally_ids'=>$order_basic->owner_ally_ids
            ];
        try{
            $entry_id=$this->insert($new_entry,true);
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
        $this->join('product_list','product_id');
        $this->select("product_quantity,entry_comment,entry_price,entry_quantity,order_id,is_counted");
        $this->where('entry_id',$entry->entry_id);
        $stock=$this->get()->getRow();
        if( !$stock ){
            return 'noentryid';
        }
        
        $OrderModel=model('OrderModel');
        $order_basic=$OrderModel->itemGet($stock->order_id,'basic');
        if( !$this->itemEditAllow( $order_basic ) ){
            return 'forbidden_at_this_stage';
        }
        if( ($entry->entry_discount??null) ){
            if(!$this->itemAtCorrection( $order_basic )){
                return 'forbidden_at_this_stage';
            }
            if( $entry->entry_discount<0 || $entry->entry_discount> ($stock->entry_price*$stock->entry_quantity) ){
                return 'invalid_discount_value';
            }
        }
        // if( $stock->is_counted && isset($entry->entry_quantity) && $entry->entry_quantity>$stock->product_quantity){
        //     $entry->entry_comment= preg_replace('/\[.+\]/u', '', $stock->entry_comment);
        //     $entry->entry_comment.="[Количество уменьшено с {$entry->entry_quantity} до {$stock->product_quantity}]";
        //     $entry->entry_quantity=$stock->product_quantity;
        // }
        $this->update($entry->entry_id,$entry);
        $result=$this->db->affectedRows()>0?'ok':'idle';


        if($order_basic->order_stock_status=='reserved'){
            $this->listStockReserve($order_basic->order_store_id);
        }

        
        return $result;
    }
    
    public function itemDelete( $entry_id ){
        if( !$entry_id ){
            return 'ok';
        }
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
    
    public $listGetSelectedFields="
        entry_id,
        order_id,
        product_id,
        entry_text,
        entry_quantity,
        entry_price,
        entry_discount,
        ROUND(entry_quantity*entry_price,2) entry_sum,
        image_hash,
        product_unit,
        product_weight,
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
        $this->join('product_list','product_id','left');
        $this->join('image_list','image_holder_id=COALESCE(product_parent_id,product_id) AND image_holder="product" AND is_main=1','left');
        $entries=$this->get()->getResult();
        return $entries;
    }
    
    public function listSumGet( $order_id ){
        $this->permitWhere('r');
        $this->select("SUM(ROUND(entry_quantity*entry_price-IFNULL(entry_discount,0),2)) order_sum_product");
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
        return $order_sum_product;
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate( $order_id, $entry_list ){
        foreach($entry_list as $entry){
            if(!$entry->product_id??0 || !$entry->entry_quantity??0){
                continue;
            }
            $this->itemCreate($order_id,$entry->product_id,$entry->entry_quantity,($entry->entry_comment??null));//item on duplicate key update
            if( $this->errors() ){
                return 'validation_error';
            }
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
        $this->permitWhere('w');
        $this->where('order_id',$order_id);
        $this->delete();
    }
    
    public function listUnDeleteChildren( $order_id ){
        $OrderModel=model('OrderModel');
        if( !$OrderModel->permit($order_id,'w') ){
            return 'forbidden';
        }
        $this->permitWhere('w');
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
        $this->transBegin();
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
            $this->transRollback();
            throw new Exception("Unknown stock status",500);
        }
        $this->transCommit();
        return $this->transStatus()?'ok':'fail';
    }

    private function listStockTrim(int $order_id){
        $sql="
            UPDATE
                product_list pl
                    JOIN
                order_entry_list oel USING(product_id)
            SET
                oel.entry_quantity=IF(
                    oel.entry_quantity>pl.product_quantity-pl.product_quantity_reserved,
                    pl.product_quantity-pl.product_quantity_reserved,
                    oel.entry_quantity)
            WHERE
                order_id='$order_id'
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