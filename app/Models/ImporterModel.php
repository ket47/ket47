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
    
    
    public function listAnalyse( $columnConfig,$target,$holder_id ){
        if( $target==='product' ){
            return $this->productListAnalyse( $columnConfig, $holder_id );
        }
    }
    ///////////////////////////////////////////
    //IMPORT SECTION
    ///////////////////////////////////////////
    public function importCreate($holder,$holder_id,$target,$colconfig){
        if($target=='product'){
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
    public function importDelete($holder,$holder_id,$target){
        if($target=='product'){
            $ProductModel=model('ProductModel');
            return $ProductModel->listDelete($holder_id);
        }
    }
    ///////////////////////////////////////////
    //PRODUCT SECTION
    ///////////////////////////////////////////
    private function productListAnalyseRequiredIsAbsent($columnConfig){
        $has_pname=false;
        $has_pquantity=false;
        $has_pprice=false;
        foreach( $columnConfig as $field=>$col ){
            $has_pname= $field=='product_name' ?true:$has_pname;
            $has_pquantity= $field=='product_quantity' || $field=='is_produced' ?true:$has_pquantity;
            $has_pprice= $field=='product_price' ?true:$has_pprice;
        }
        if( !$has_pname || !$has_pprice || !$has_pquantity ){
            return true;
        }
        return false;
    }
    
    private function productListAnalyse( $columnConfig, $store_id ){
        $StoreModel=model('StoreModel');
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        if( $this->productListAnalyseRequiredIsAbsent($columnConfig) ){
            return 'no_required_fields';
        }
        if( isset($columnConfig->product_code) ){
            $join_on_src=$columnConfig->product_code;
            $join_on_dst='product_code';
        } else {
            $join_on_src=$columnConfig->product_name;
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
                il.action=IF(product_id,'update',IF(LENGTH(`$columnConfig->product_name`)>10 AND (`$columnConfig->product_quantity`>0 OR '".($columnConfig->is_produced??0)."') AND `$columnConfig->product_price`>0,'add','skip'))
            WHERE
                il.owner_id='{$owner_id}'
                AND il.holder_id='$store_id'
            ";
        $this->query($sql);
        
        $this->select("COUNT(*) row_count,`action`")
                ->where('owner_id',$owner_id)
                ->where('holder','store')
                ->where('holder_id',$store_id)
                ->groupBy('`action`');
        
        $analysed=$this->get()->getResult();
        //ANALYSE FOR DELETE
        $analysed[]=['row_count'=>$this->productListAnalyseAbsent($join_on_src,$join_on_dst,$store_id,'count',$columnConfig),'action'=>'delete'];
        return $analysed;
    }
    
    private function productListAnalyseAbsent($join_on_src,$join_on_dst,$store_id,$action='count',$columnConfig=null){
        if( $action=='count' ){
            $sql="
                SELECT
                    COUNT(*) row_count
                FROM
                    product_list pl
                        LEFT JOIN
                    imported_list il ON pl.$join_on_dst=il.$join_on_src AND il.holder='store' AND il.holder_id='$store_id'
                WHERE
                    pl.owner_id='$this->user_id'
                    AND il.id IS NULL
                ";
            return $this->query($sql)->getRow('row_count');
        }
        if( $action=='fill' && $columnConfig ){
            $src_col_list="'update','store',$store_id,'product'";
            $target_col_list="`action`,`holder`,`holder_id`,`target`";
            $delimeter=',';
            
            $skip_cols=['product_action_price','product_action_start','product_action_finish','product_categories'];
            foreach($columnConfig as $src=>$target){
                if(in_array($src, $skip_cols)){
                    continue;
                }
                $src_col_list.=$delimeter.$src;
                $target_col_list.=$delimeter.$target;
            }
            $sql="
                INSERT INTO imported_list ($target_col_list) SELECT
                    $src_col_list
                FROM
                    product_list pl
                        LEFT JOIN
                    imported_list il ON pl.$join_on_dst=il.$join_on_src AND il.holder='store' AND il.holder_id='$store_id'
                WHERE
                    pl.owner_id='$this->user_id'
                    AND il.id IS NULL
                ";
            die($sql);
            $this->query($sql);
            return $this->affectedRows();
        }
        return false;
    }
}