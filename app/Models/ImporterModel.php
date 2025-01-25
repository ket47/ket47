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
        'holder',
        'holder_id',
        'holder_data_hash',
        'target',
        'target_id',
        'target_external_id',
        'action',
        'updated_at'
    ];

    public $itemCreateAsDisabled=true;
    protected $useSoftDeletes = false;
    protected $user_id=-1;
    protected $olderItemsDeleteTreshold;

    function __construct(){
        parent::__construct();
        $this->olderItemsDeleteTreshold=date('Y-m-d H:i:s',strtotime('- 10 minute'));
    }

    public function olderItemsDeleteTresholdSet( $tresholdDatetime ){
        $this->olderItemsDeleteTreshold=$tresholdDatetime;
    }
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( $item, $holder, $holder_id, $target, $target_external_id=null ){
        $set=[];
        foreach($item as $i=>$value){
            $num=$i+1;
            if( $num>16 ){
                continue;
            }
            if($value=='-skip-'){
                continue;
            }
            $set['C'.$num]=$value;
        }
        $set['holder']=$holder;
        $set['holder_id']=$holder_id;
        $set['holder_data_hash']=md5(implode('|',$item));//prevent reinserting same products
        $set['target']=$target;
        $set['owner_id']=session()->get('user_id');
        if( $target_external_id ){
            $set['target_external_id']=$target_external_id;
        }


        $row_id=$this->ignore()->insert($set,true);
        if(!$row_id){
            $this->where('holder',$set['holder']);
            $this->where('holder_id',$set['holder_id']);
            $this->where('holder_data_hash',$set['holder_data_hash']);
            $this->set('updated_at','NOW()',false);
            $this->update();            
        }
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
    
    public function listGet( $filter=[] ){
        $this->filterMake($filter);

        if($filter['holder']){
            $this->where('holder',$filter['holder']);
        }
        if($filter['holder_id']){
            $this->where('holder_id',$filter['holder_id']);
        }

        $this->permitWhere('r');
        $this->orderBy("action='add'","DESC");
        $this->orderBy("action='update'","DESC");
        $this->orderBy("action","DESC");
        return $this->get()->getResult();
    }
    
    public function listCreate( array $itemList, string $holder, int $holder_id, string $target, int $external_id_index=null ){
        $this->listStaleDelete( $holder, $holder_id );
        foreach ($itemList as $item){
            $external_id=$item[$external_id_index]??null;
            $this->itemCreate( $item, $holder, $holder_id, $target, $external_id );
        }
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete( $ids ){
        $this->permitWhere('w');
        $this->delete($ids,true);
        return $this->db->affectedRows()>0?'ok':'idle';
    }

    private function listStaleDelete(string $holder, int $holder_id){
        $this->where('holder',$holder);
        $this->where('holder_id',$holder_id);
        $this->where('action','stale');
        $this->delete(null);
    }

    public function listAnalyse( $holder_id, $target, $colconfig ){
        if( $target==='product' ){
            return $this->productListAnalyse( $holder_id,$colconfig );
        }
    }

    public function listImport( string $holder, int $holder_id, string $target, object $colconfig){
        $rowcount=0;
        $this->listAnalyse( $holder_id, $target, $colconfig );
        $rowcount+=$this->importCreate($holder,$holder_id,$target,$colconfig);
        $rowcount+=$this->importUpdate($holder,$holder_id,$target,$colconfig);
        $rowcount+=$this->importDelete($holder,$holder_id,$target);
        if( $target==='product' ){
            $ProductModel=model('ProductModel');
            $ProductModel->listUpdateValidity($holder_id);
        }
        return $rowcount;
    }
    ///////////////////////////////////////////
    //IMPORT SECTION
    ///////////////////////////////////////////
    public function importCreate($holder,$holder_id,$target,$colconfig){
        $select_list=['id'];
        foreach($colconfig as $trg=>$src){
            $select_list []= "{$src} {$trg}";
        }
        $this->select(implode(',',$select_list));
        $this->where('action','add');
        $this->where('holder',$holder);
        $this->where('holder_id',$holder_id);
        $this->permitWhere('r');
        $listToCreate=$this->get()->getResult();
        if(!$listToCreate){
            return;//no products to add
        }
        $rowcount=0;
        if($target==='product'){
            $product_option_children=[];
            $ProductModel=model('ProductModel');
            $ProductModel->itemCreateAsDisabled=$this->itemCreateAsDisabled;
            $ProductModel->itemImageCreateAsDisabled=$this->itemCreateAsDisabled;
            foreach($listToCreate as $product){
                $product->store_id=$holder_id;
                $product_id=$ProductModel->ignore()->itemCreate($product);
                if($product_id){
                    $this->update($product->id,['action'=>'done','target_id'=>$product_id]);
                    $rowcount++;
                }
                if($product->product_external_parent_id??null){
                    $product_option_children[]= $product_id;
                }
            }
            if($colconfig->product_external_parent_id??null){
                $this->importCreateOptionLinks($product_option_children,$colconfig->product_external_parent_id);
            }
        }
        return $rowcount;
    }
    private function importCreateOptionLinks( array $product_option_children, string $ext_parent_id_col ){
        if( !$product_option_children ){
            return;
        }
        $this->whereIn('imported_list.target_id',$product_option_children);
        $this->join('imported_list il2',"imported_list.`$ext_parent_id_col`=il2.target_external_id");
        $this->select("imported_list.target_id AS product_id, il2.target_id AS parent_product_id");
        $optionLinks=$this->get()->getResult();
        if(!$optionLinks){
            return;
        }
        $parent_ids=[];
        $ProductModel=model('ProductModel');
        foreach($optionLinks as $link){
            $ProductModel->itemOptionSave( $link->product_id, $link->parent_product_id );
            if( !in_array($link->parent_product_id,$parent_ids) ){
                /**
                 * mark parent product as parent to itself to indicate it has options
                 */
                $ProductModel->itemOptionSave( $link->parent_product_id, $link->parent_product_id );
            }
            $parent_ids[]=$link->parent_product_id;
        }
    }
    
    public function importUpdate($holder,$holder_id,$target,$colconfig){
        $select_list=['target_id product_id','id','NULL deleted_at'];
        foreach($colconfig as $trg=>$src){
            $select_list []= "{$src} {$trg}";
        }
        $this->select(implode(',',$select_list));
        $this->where('action','update');
        $this->where('holder',$holder);
        $this->where('holder_id',$holder_id);
        $this->permitWhere('r');
        $listToUpdate=$this->get()->getResult();
        if(!$listToUpdate){
            return;//no products to update
        }
        $rowcount=0;
        if($target==='product'){
            $ProductModel=model('ProductModel');
            $rowcount=$ProductModel->listUpdate($holder_id,$listToUpdate);
        }
        foreach($listToUpdate as $item){
            $update_ids[]=$item->id;
        }
        $this->update($update_ids,['action'=>'done']);
        return $rowcount;
    }
    public function importDelete($holder,$holder_id,$target){
        if($target=='product'){
            $pl_delete_ids_str=$this->productListAnalyseAbsent( $holder_id, $get='id_list' );
            if(!$pl_delete_ids_str){
                return 0;
            }
            $pl_delete_ids=explode(',',$pl_delete_ids_str);
            $this->whereIn('target_id',$pl_delete_ids);
            $this->delete(null,true);

            $ProductModel=model('ProductModel');
            $ProductModel->listDelete($pl_delete_ids);

            return count($pl_delete_ids);
        }
    }
    ///////////////////////////////////////////
    //PRODUCT SECTION
    ///////////////////////////////////////////
    private function productColValidate($colconfig){
        $has_pname=false;
        $has_pquantity=false;
        $has_pprice=false;
        foreach( $colconfig as $field=>$col ){
            $has_pname      |= $field=='product_name';
            $has_pquantity  |= $field=='product_quantity' || $field=='is_counted';
            $has_pprice     |= $field=='product_price';
        }
        if( !$has_pname || !$has_pprice || !$has_pquantity ){
            return true;
        }
        return false;
    }
    
    private function productListAnalyse( $store_id, $colconfig ){
        if( $this->productColValidate($colconfig) ){
            pl("Import failed: no_required_fields");
            return 'no_required_fields';
        }
        $join_cases=[];
        if( isset($colconfig->product_external_id) ){
            $join_cases[]="pl.product_external_id IS NOT NULL AND pl.product_external_id={$colconfig->product_external_id}";
        } else
        if( isset($colconfig->product_code) ){
            $join_cases[]="pl.product_code IS NOT NULL AND pl.product_code={$colconfig->product_code}";
        }
        $join_condition=implode(' OR ',$join_cases);
        
        $owner_id=session()->get('user_id');
        $delete_older_than=$this->olderItemsDeleteTreshold;
        $sql="
            UPDATE
                imported_list il
                    LEFT JOIN
                product_list pl ON ($join_condition) AND pl.store_id='$store_id'
            SET
                pl.deleted_at=null,
                il.target_id=product_id,
                il.action=
                    IF(il.updated_at<'$delete_older_than','stale',
                    IF(product_id,'update',
                    IF(CHAR_LENGTH(`{$colconfig->product_name}`)>=4 AND (`{$colconfig->product_quantity}`<>'' OR '".($colconfig->is_counted??0)."') AND `$colconfig->product_price`>0,'add',
                    'skip'
                )))
            WHERE
                il.owner_id='{$owner_id}'
                AND il.holder='store'
                AND il.holder_id='$store_id'
                AND (il.action <> 'done' OR il.action IS NULL OR il.updated_at<'$delete_older_than')
            ";
        $this->query($sql);

//        pl("il.updated_at<'$delete_older_than'");
        
        $this->select("COUNT(*) row_count,`action`")
                ->where('owner_id',$owner_id)
                ->where('holder','store')
                ->where('holder_id',$store_id)
                ->groupBy('`action`');
        $analysed=$this->get()->getResult();
        $analysed[]=['row_count'=>$this->productListAnalyseAbsent($store_id,'row_count'),'action'=>'delete'];
        return $analysed;
    }
    
    private function productListAnalyseAbsent( $store_id, $get='row_count' ){
        if($get=='row_count'){
            $select='COUNT(*) row_count';
        }
        if($get=='id_list'){
            $select='GROUP_CONCAT(pl.product_id) id_list';
        }
        $sql="
            SELECT
                $select
            FROM
                product_list pl
                    LEFT JOIN
                imported_list il ON il.target_id=pl.product_id AND il.holder='store' AND il.holder_id='$store_id' AND il.action<>'stale'
            WHERE
                pl.store_id='$store_id'
                AND pl.deleted_at IS NULL
                AND il.id IS NULL
            ";
        return $this->query($sql)->getRow($get);
    }
}