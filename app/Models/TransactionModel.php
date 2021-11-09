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
        'acc_debit_code',
        'acc_credit_code',
        'owner_id',
        'holder',
        'holder_id'
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    protected $validationRules    = [
        'trans_amount'    => 'required|greater_than[0]',
        'acc_debit_code'  => 'required',
        'acc_credit_code' => 'required',
        'holder'          => 'required',
        'holder_id'       => 'required'
    ];
    
    public function itemGet( $trans_id ){
        $this->permitWhere('r');
        $this->where('trans_id',$trans_id);
        return $this->get()->getRow();
    }
    
    public function itemCreate( $trans ){
        if( !$this->permit(null, 'w') ){
            return 'forbidden';
        }
        $trans_id=$this->insert($trans,true);
        return $trans_id;
    }
    
    public function itemUpdate( $trans ){
        $this->permitWhere('w');
        $this->update($trans->trans_id,$trans);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( $trans_id ){
        $this->permitWhere('w');
        $this->delete($trans_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function allowEnable(){
        $this->allowedFields[]='is_disabled';
    }
    
    public function listGet( $filter ){
        $ledger=[
            'ibal'=>$this->listIbalGet($filter),
            'entries'=>$this->listEntriesGet($filter),
            'fbal'=>$this->listFbalGet($filter)
        ];
        return $ledger;
    }
    
    private function listIbalGet( $filter ){
        if( $filter['idate']??0 ){
            $this->where('created_at<',$filter['idate']);
        } else {
            return 0;
        }
        $this->permitWhere('r');
        if( $filter['acc_debit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['acc_credit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        $this->select("SUM(trans_amount) ibal");
        return $this->get()->getRow('ibal');
    }
    
    private function listFbalGet( $filter ){
        if( $filter['fdate']??0 ){
            $this->where('created_at<',$filter['fdate']);
        } else {
            return 0;
        }
        $this->permitWhere('r');
        if( $filter['acc_debit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['acc_credit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        $this->select("SUM(trans_amount) fbal");
        return $this->get()->getRow('fbal');        
    }
    
    private function listEntriesGet( $filter ){
        $this->filterMake($filter);
        if( $filter['acc_debit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['acc_credit_code']??0 ){
            $this->where('acc_debit_code',$filter['acc_debit_code']);
        }
        if( $filter['idate']??0 ){
            $this->where('created_at>',$filter['idate']);
        }
        if( $filter['fdate']??0 ){
            $this->where('created_at<',$filter['fdate']);
        }
        return $this->get()->getResult();        
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}