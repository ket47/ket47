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
        'target_external_id',
        'action',
        'updated_at'
    ];

    protected $useSoftDeletes = false;
    protected $user_id=-1;
    
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

    public function listAnalyse( $holder_id, $target, $colconfig ){
        if( $target==='product' ){
            return $this->productListAnalyse( $holder_id,$colconfig );
        }
    }

    public $itemCreateAsDisabled=true;
    public function listImport( string $holder, int $holder_id, string $target, object $colconfig){
        $this->listAnalyse( $holder_id, $target, $colconfig );
        $this->importCreate($holder,$holder_id,$target,$colconfig);
        $this->importUpdate($holder,$holder_id,$target,$colconfig);
        $this->importDelete($holder,$holder_id,$target);
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
        if($target==='product'){
            $ProductModel=model('ProductModel');
            $ProductModel->itemCreateAsDisabled=$this->itemCreateAsDisabled;
            $ProductModel->itemImageCreateAsDisabled=$this->itemCreateAsDisabled;
            $ProductModel->listCreate($holder_id,$listToCreate);
        }
        foreach($listToCreate as $item){
            $update_ids[]=$item->id;
        }
        $this->update($update_ids,['action'=>'done']);
    }
    
    public function importUpdate($holder,$holder_id,$target,$colconfig){
        $select_list=['target_id product_id','id'];
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
        if($target==='product'){
            $ProductModel=model('ProductModel');
            $ProductModel->listUpdate($holder_id,$listToUpdate);
        }
        foreach($listToUpdate as $item){
            $update_ids[]=$item->id;
        }
        $this->update($update_ids,['action'=>'done']);
    }
    public function importDelete($holder,$holder_id,$target){
        if($target=='product'){
            $this->select('target_id,id');
            $this->where('action','delete');
            $this->where('holder',$holder);
            $this->where('holder_id',$holder_id);
            $this->permitWhere('r');
            $listToDelete=$this->get()->getResult();
            if(!$listToDelete){
                return;//no products to delete
            }
            foreach($listToDelete as $item){
                $il_delete_ids[]=$item->id;
                $pl_delete_ids[]=$item->target_id;
            }
            $this->delete($il_delete_ids,true);
            $ProductModel=model('ProductModel');
            return $ProductModel->listDelete($pl_delete_ids);
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
            return 'no_required_fields';
        }
        if( isset($colconfig->product_code) ){
            $join_on_src=$colconfig->product_code;
            $join_on_dst='product_code';
        } else {
            $join_on_src=$colconfig->product_name;
            $join_on_dst='product_name';            
        }
        
        $owner_id=session()->get('user_id');
        $delete_older_than=date('Y-m-d H:i:s',strtotime('- 10 minute'));
        $sql="
            UPDATE
                imported_list il
                    LEFT JOIN
                product_list pl ON pl.$join_on_dst=il.$join_on_src AND pl.store_id='$store_id'
            SET
                pl.deleted_at=null,
                il.target_id=product_id,
                il.action=
                    IF(il.updated_at<'$delete_older_than','delete',
                    IF(product_id,'update',
                    IF(LENGTH(`{$colconfig->product_name}`)>5 AND (`{$colconfig->product_quantity}`>0 OR '".($colconfig->is_counted??0)."') AND `$colconfig->product_price`>0,'add',
                    'skip'
                )))
            WHERE
                il.owner_id='{$owner_id}'
                AND il.holder='store'
                AND il.holder_id='$store_id'
                AND (il.action <> 'done' OR il.action IS NULL OR il.updated_at<'$delete_older_than')
            ";
        $this->query($sql);
        
        $this->select("COUNT(*) row_count,`action`")
                ->where('owner_id',$owner_id)
                ->where('holder','store')
                ->where('holder_id',$store_id)
                ->groupBy('`action`');
        
        $analysed=$this->get()->getResult();
        //ANALYSE FOR DELETE
        $analysed[]=['row_count'=>$this->productListAnalyseAbsent($store_id,$colconfig,'row_count'),'action'=>'delete'];
        return $analysed;
    }
    
    private function productListAnalyseAbsent($store_id,$colconfig=null,$get='row_count'){
        if( isset($colconfig->product_code) ){
            $join_on_src=$colconfig->product_code;
            $join_on_dst='product_code';
        } else {
            $join_on_src=$colconfig->product_name;
            $join_on_dst='product_name';            
        }
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
                imported_list il ON pl.$join_on_dst=il.$join_on_src AND il.holder='store' AND il.holder_id='$store_id'
            WHERE
                pl.owner_id='$this->user_id'
                AND il.id IS NULL
                AND pl.deleted_at IS NULL
            ";
        return $this->query($sql)->getRow($get);
    }
}