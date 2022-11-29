<?php
namespace App\Models;
use CodeIgniter\Model;

class TransactionModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'transaction_list';
    protected $primaryKey = 'trans_id';
    protected $allowedFields = [
        'trans_amount',
        'trans_data',
        'trans_tags',
        'trans_role',
        'trans_debit',
        'trans_credit',
        'trans_holder',
        'trans_holder_id',
        'trans_description',
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    protected $validationRules    = [
        'trans_amount'    => 'required|greater_than[0]',
        'trans_role'      => 'required',
        'trans_holder'    => 'required',
        'trans_holder_id' => 'required'
    ];
    
    public function itemGet( $trans_id ){
        $this->permitWhere('r');
        $this->where('trans_id',$trans_id);
        $trans=$this->get(1)->getRow();
        if( $trans->trans_data ){
            $trans->trans_data=json_decode($trans->trans_data);
        }
        return $trans;
    }

    public function itemFind( object $filter ){
        $this->permitWhere('r');
        if( $filter->trans_role??null ){
            $this->where('trans_role',$filter->trans_role);
        }
        if( $filter->trans_tags??null ){
            $this->where("MATCH (trans_tags) AGAINST ('{$filter->trans_tags}' IN BOOLEAN MODE)");
        }
        if( $filter->trans_holder??null ){
            $this->where('trans_holder',$filter->trans_holder);
        }
        if( $filter->trans_holder_id??null ){
            $this->where('trans_holder_id',$filter->trans_holder_id);
        }
        $trans=$this->get(1)->getRow();
        if( $trans?->trans_data ){
            $trans->trans_data=json_decode($trans->trans_data);
        }
        return $trans;
    }

    private function itemCreateTags(object $trans){
        $tags=$trans->trans_tags??'';
        if($trans->trans_role??''){
            list($debits,$credits)=explode('->',$trans->trans_role);
            $trans->trans_debit=$debits;
            $trans->trans_credit=$credits;
            $tags.=str_replace('.',' #debit','.'.ucfirst($debits));
            $tags.=str_replace('.',' #credit','.'.ucfirst($credits));
        }
        if($trans->trans_holder??''){
            //$tags.=" #{$trans->trans_holder}-{$trans->trans_holder_id}";
        }
        $trans->trans_tags=$tags;
        return $trans;
    }

    public function itemCreate( object $trans ){
        if( !$this->permit(null, 'w') ){
            return 0;
        }
        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='owner_ally_ids';
        $trans=$this->itemCreateTags($trans);
        $trans_id=$this->insert($trans,true);
        return $trans_id;
    }

    public function itemCreateOnce( object $trans ){
        $created=$this->itemFind($trans);
        if( $created ){
            return $created->trans_id;
        }
        return $this->itemCreate($trans);
    }

    public function itemUpdate( object $trans ){
        $this->permitWhere('w');
        $this->update($trans->trans_id,$trans);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( int $trans_id ){
        $this->permitWhere('w');
        $this->delete($trans_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function allowEnable(){
        $this->allowedFields[]='is_disabled';
    }

    public function listFind( object $filter ){
        $this->permitWhere('r');
        if( $filter->trans_role??null ){
            $this->where('trans_role',$filter->trans_role);
        }
        if( $filter->trans_tags??null ){
            $this->where("MATCH (trans_tags) AGAINST ('{$filter->trans_tags}' IN BOOLEAN MODE)");
        }
        if( $filter->trans_holder??null ){
            $this->where('trans_holder',$filter->trans_holder);
        }
        if( $filter->trans_holder_id??null ){
            $this->where('trans_holder_id',$filter->trans_holder_id);
        }
        //if( sudo() ){
        //    $this->select("*,created_at trans_date");
        //} else {
            $this->select("trans_id,trans_description,trans_amount");
        //}
        $tranList=$this->orderBy('updated_at DESC')->get()->getResult();
        if($tranList){
            foreach($tranList as $trans){
                if( $trans->trans_data??null ){
                    $trans->trans_data=json_decode($trans->trans_data);
                }
            }
        }
        return $tranList;
    }
    
    public function listGet( object $filter ){
        if($filter->account??null){
            $debit_case="MATCH (trans_tags) AGAINST ('#debit".ucfirst($filter->account)."' IN BOOLEAN MODE)";
            $credit_case="MATCH (trans_tags) AGAINST ('#credit".ucfirst($filter->account)."' IN BOOLEAN MODE)";
        } else {
            return 'no_account';
        }
        $start_case= $filter->start_at?"created_at>'{$filter->start_at} 00:00:00'":"1";
        $finish_case=$filter->finish_at?"created_at<'{$filter->finish_at} 23:59:59'":"1";
        $permission=$this->permitWhereGet('r','item');

        $sql_create_inner="
            CREATE TEMPORARY TABLE tmp_ledger_inner AS(
            SELECT 
                trans_id,
                trans_description,
                trans_amount,
                created_at trans_date,
                IF($debit_case,1,0) is_debit,
                IF($start_case,1,0) after_start
            FROM
                transaction_list
            WHERE
                $permission
                AND ($debit_case OR $credit_case)
                AND $finish_case
                AND is_disabled=0
            )
        ";
        $sql_ledger_get="
            SELECT
                *
            FROM
                tmp_ledger_inner
        ";
        $sql_meta_get="
            SELECT
                SUM(IF(after_start AND is_debit,trans_amount,0)) sum_debit,
                SUM(IF(after_start AND NOT is_debit,trans_amount,0)) sum_credit,
                SUM(IF(NOT after_start,IF(is_debit,trans_amount,-trans_amount),0)) sum_start,
                SUM(IF(is_debit,trans_amount,-trans_amount)) sum_finish
            FROM
                tmp_ledger_inner
        ";
        $this->query($sql_create_inner);
        $ledger =$this->query($sql_ledger_get)->getResult();
        $meta   =$this->query($sql_meta_get)->getRow();
        return [
            'ledger'=>$ledger,
            'meta'=>$meta
        ];
    }

    public function listDeleteChildren( $holder,$holder_id ){
        $OrderModel=model('OrderModel');
        if( !$OrderModel->permit($holder_id,'w') ){
            return 'forbidden';
        }
        $this->permitWhere('w');
        $this->where('trans_holder',$holder);
        $this->where('trans_holder_id',$holder_id);
        $this->delete();
    }

    public function listPurge( $olderThan=APP_TRASHED_DAYS ){
        $olderStamp= new \CodeIgniter\I18n\Time((-1*$olderThan)." hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }

    public function balanceGet( object $filter, $mode=null ):object{
        if( $mode!='skip_permision_check' ){
            $this->permitWhere('r');
        }
        if(empty($filter->trans_tags)){
            $filter->trans_tags='';
        }
        if($filter->account??null){
            $debit_tag='#debit'.ucfirst($filter->account);
            $credit_tag='#credit'.ucfirst($filter->account);

            $filter->trans_tags.=" $debit_tag $credit_tag";
            $this->select("SUM(IF(MATCH (`trans_tags`) AGAINST ('$debit_tag' IN BOOLEAN MODE),trans_amount,0)) debit_sum");
            $this->select("SUM(IF(MATCH (`trans_tags`) AGAINST ('$credit_tag' IN BOOLEAN MODE),trans_amount,0)) credit_sum");    
        } else {
            return 'no_account';
        }
        if( $filter->trans_holder??null ){
            $this->where('trans_holder',$filter->trans_holder);
        }
        if( $filter->trans_holder_id??null ){
            $this->where('trans_holder_id',$filter->trans_holder_id);
        }
        $this->where("MATCH (`trans_tags`) AGAINST ('{$filter->trans_tags}' IN BOOLEAN MODE)");
        $meta=$this->get()->getRow();
        return (object)[
            'debitSum'=>$meta->debit_sum,
            'creditSum'=>$meta->credit_sum,
            'balance'=>$meta->debit_sum-$meta->credit_sum
        ];
    }

    public function sumGet(array $trans_tags){
        foreach($trans_tags as $tag){
            $this->where("MATCH (trans_tags) AGAINST ('{$tag}' IN BOOLEAN MODE)");
        }
        $this->select("SUM(trans_amount) sum_total");
        return $this->get()->getRow('sum_total')??0;
    }
}