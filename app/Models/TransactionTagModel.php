<?php
namespace App\Models;
use CodeIgniter\Model;

class TransactionTagModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'transaction_tag_list';
    protected $primaryKey = 'link_id';
    protected $allowedFields = [
        'trans_id',
        'tag_name',
        'tag_id',
        'tag_type',
        'tag_option'
        ];
    protected $validationRules    = [
        'trans_id'     => ['rules' =>'required'],
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
    
    public function listGet( $trans_id ){
        $this->where('trans_id',$trans_id);
        $this->select("transaction_tag_list.*,COALESCE(order_id,store_name,courier_name,group_name) tag_label");
        $this->join('transaction_account_list',"tag_name='acc' AND tag_type=group_type",'left');
        $this->join('order_list',"tag_name='order' AND tag_id=order_id",'left');
        $this->join('store_list',"tag_name='store' AND tag_id=store_id",'left');
        $this->join('courier_list',"tag_name='courier' AND tag_id=courier_id",'left');
        return $this->get()->getResult();
    }
    
    private function listCreateTags( $trans ){
        $tags=$trans->tags??'';
        $tag_list=explode(' ',$tags);
        if($trans->trans_role??''){
            list($debits,$credits)=explode('->',$trans->trans_role);
            $debits_list=explode('.',$debits);
            $credits_list=explode('.',$credits);
            foreach($debits_list as $acc_code){
                $tag_list[]="acc::{$acc_code}:debit";
            }
            foreach($credits_list as $acc_code){
                $tag_list[]="acc::{$acc_code}:credit";
            }
        }
        return $tag_list;
    }

    public function listCreate( object $trans ){
        $tag_list=$this->listCreateTags($trans);
        foreach($tag_list as $tag){
            $parsed_tag=$this->tagParse($tag);
            if(!$parsed_tag->tag_name){
                continue;
            }
            $parsed_tag->trans_id=$trans->trans_id;
            $this->ignore()->insert($parsed_tag);
        }
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listUpdate( object $trans ){
        $this->listDelete($trans->trans_id);
        return $this->listCreate( $trans );
    }
    
    public function listDelete($trans_id){
        $this->where('trans_id',$trans_id);
        $this->delete(null,true);
        return $this->db->affectedRows()?'ok':'idle';
    }

    private function tagParse($tag){
        return (object)array_combine(['tag_name','tag_id','tag_type','tag_option'],array_pad(explode(':',$tag),4,''));
    }
    private $queriedTagCount=0;
    public function tagWhereGet($tagQuery){
        $this->queriedTagCount=0;
        $where=[];
        $tag=array_unique(explode(' ',$tagQuery));
        foreach( $tag as $chunk ){
            if( str_contains($chunk,':') ){
                $parsed_tag=$this->tagParse($chunk);
                $and_case=[];
                foreach($parsed_tag as $field=>$value){
                    if( $value==null ){
                        continue;
                    }
                    if( $field=='tag_id' ){
                        $value=(int)$value;
                    }
                    $and_case[]="`transaction_tag_list`.`$field`='$value'";
                }
                $where[]='('.implode(' AND ',$and_case).')';
                $this->queriedTagCount++;
            }
        }
        return implode(' OR ',$where);
    }
    public function queriedTagCountGet(){
        return $this->queriedTagCount;
    }
    public function tagSubqueryGet($query){
        return $this->db->table('transaction_tag_list')->select('trans_id')->where($this->tagWhereGet($query));
    }
}