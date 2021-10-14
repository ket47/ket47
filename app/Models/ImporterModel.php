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
        'target'];

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
    
    public function itemCreate( $item, $holder, $holder_id, $target ){
        $set=[];
        foreach($item as $i=>$value){
            $num=$i+1;
            if( $num>16 ){
                continue;
            }
            $set['C'.$num]=$value;
        }
        $set['holder']=$holder;
        $set['holder_id']=$holder_id;
        $set['target']=$target;
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
        $this->orderBy("action='add'","DESC");
        $this->orderBy("action='update'","DESC");
        $this->orderBy("action","DESC");
        return $this->get()->getResult();
    }
    
    public function listCreate(){
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
    
    
    public function listAnalyse( $holder_id,$target,$colconfig ){
        if( $target==='product' ){
            return $this->productListAnalyse( $holder_id,$colconfig );
        }
    }
    ///////////////////////////////////////////
    //IMPORT SECTION
    ///////////////////////////////////////////
    public function importCreate($holder,$holder_id,$target,$colconfig){
        if($target=='product'){
            $this->productListAnalyse( $holder_id,$colconfig );
            $ProductModel=model('ProductModel');
            return $ProductModel->listCreate($holder_id,$colconfig);
        }
    }
    public function importUpdate($holder,$holder_id,$target,$colconfig){
        if($target=='product'){
            $ProductModel=model('ProductModel');
            return $ProductModel->listUpdate($holder,$holder_id,$colconfig);
        }
    }
    public function importDelete($holder,$holder_id,$target,$colconfig){
        if($target=='product'){
            $ProductModel=model('ProductModel');
            $id_list=$this->productListAnalyseAbsent($holder_id,$colconfig,'id_list');
            $product_ids= explode(',', $id_list);
            return $ProductModel->listDelete($product_ids);
        }
    }
    ///////////////////////////////////////////
    //PRODUCT SECTION
    ///////////////////////////////////////////
    private function productListAnalyseRequiredIsAbsent($colconfig){
        $has_pname=false;
        $has_pquantity=false;
        $has_pprice=false;
        foreach( $colconfig as $field=>$col ){
            $has_pname= $field=='product_name' ?true:$has_pname;
            $has_pquantity= $field=='product_quantity' || $field=='is_produced' ?true:$has_pquantity;
            $has_pprice= $field=='product_price' ?true:$has_pprice;
        }
        if( !$has_pname || !$has_pprice || !$has_pquantity ){
            return true;
        }
        return false;
    }
    
    private function productListAnalyse( $store_id,$colconfig ){
        $StoreModel=model('StoreModel');
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        if( $this->productListAnalyseRequiredIsAbsent($colconfig) ){
            return 'no_required_fields';
        }
        if( isset($colconfig->product_code) ){
            $join_on_src=$colconfig->product_code;
            $join_on_dst='product_code';
        } else {
            $join_on_src=$colconfig->product_name;
            $join_on_dst='product_name';            
        }
        
        $owner_id=$this->user_id;
        //ANALYSE FOR SKIP UPDATE ADD
        $sql="
            UPDATE
                imported_list il
                    LEFT JOIN
                product_list pl ON pl.$join_on_dst=il.$join_on_src AND pl.store_id='$store_id'
            SET
                il.target_id=product_id,
                il.action=IF(product_id,'update',IF(LENGTH(`$colconfig->product_name`)>10 AND (`$colconfig->product_quantity`>0 OR '".($colconfig->is_produced??0)."') AND `$colconfig->product_price`>0,'add','skip'))
            WHERE
                il.owner_id='{$owner_id}'
                AND il.holder_id='$store_id'
                AND (il.action <> 'done' OR il.action IS NULL)
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