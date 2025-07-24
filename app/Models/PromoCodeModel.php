<?php
namespace App\Models;
class PromoCodeModel extends SecureModel{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'promo_code_list';
    protected $primaryKey = 'promo_code_id';
    protected $returnType = 'object';
    protected $allowedFields = [
            'promo_code',
            'promo_sum',
            'promo_description',
            'promo_subject',
            'case_user_id',
            'case_store_id',
            'case_product_id',
            'case_started_at',
            'case_finished_at',
            'case_min_sum',

            'charge_taget',
            'charge_target_id',
            'charge_sum',

            'is_disabled',
            'is_working',
            'updated_at',
            'updated_by',
        ];

    protected $useSoftDeletes = false;
        protected $validationRules    = [
        'promo_code'     => [
            'rules' =>'required|min_length[3]|is_unique[promo_code_list.promo_code]',
            'errors'=>[
                'required'=>'required',
                'min_length'=>'short',
                'is_unique'=>'notunique'
            ]
        ],
        'promo_sum'    => [
            'rules' =>'required|integer|greater_than[50]',
            'errors'=>[
                'required'=>'required',
                'integer'=>'invalid',
                'greater_than'=>'small'
            ]
        ],
    ];

    
    public function itemGet( int $promo_code_id, string $mode='all' ){
        $this->select('promo_code_list.*');
        if($mode=='all'){
            $this->select('store_name case_store_name,product_name case_product_name, user_name case_user_name, user_phone case_user_phone');
            $this->join('store_list','store_id=case_store_id','left');
            $this->join('product_list','product_id=case_product_id','left');
            $this->join('user_list','user_id=case_user_id','left');
        }
        $promo=$this->find($promo_code_id);
        return $promo;
    }
    
    public function itemCreate( $item ){
        if( !sudo() ){
            return 'forbidden';
        }
        $item['promo_subject']='product';
        $item['case_min_sum']=$item['promo_sum']/0.2;//set default value to 20%
        $item['case_started_at']=date('Y-m-d 00:00:00',time());//now
        $item['case_finished_at']=date('Y-m-d 23:59:59',time()+30*24*60*60);//now + 30 days

        $item['owner_id']=$item['updated_by']=session()->get('user_id');
        $item['is_disabled']=0;
        $item['is_working']=1;

        $this->allowedFields=[
            'promo_code',
            'promo_sum',
            'promo_description',
            'promo_subject',
            'case_min_sum',
            'case_started_at',
            'case_finished_at',
            
            'owner_id',
            'is_disabled',
            'is_working',
        ];
        return $this->ignore()->insert($item,true);
    }
    
    public function itemUpdate( $pcode ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( !$pcode->promo_code_id ){
            return 'noid';
        }
        $pcode=$this->itemReset($pcode);
        $pcode->updated_by=session()->get('user_id');
        $this->update($pcode->promo_code_id,$pcode);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemReset( $update ){
        $promo_code=$this->itemGet( $update->promo_code_id );
        if( isset($update->case_min_sum) ){
            if( $update->case_min_sum < $promo_code->promo_sum ){
                $update->case_min_sum=$promo_code->promo_sum;
            }
        }
        if( !empty($update->charge_sum) ){
            if( $update->charge_sum > $promo_code->promo_sum ){
                $update->charge_sum=$promo_code->promo_sum;
            }
        }
        if( !empty($update->promo_sum) ){
            if( $update->promo_sum>$promo_code->case_min_sum ){
                $update->case_min_sum=$update->promo_sum;
            }
            if( $update->promo_sum<$promo_code->charge_sum ){
                $update->charge_sum=$update->promo_sum;
            }
            if( empty($promo_code->charge_sum) && !empty($update->case_store_id) ){
                $update->charge_sum=$update->promo_sum;
            }
            if( empty($promo_code->case_store_id) && empty($update->case_store_id) ){
                $update->charge_sum=null;
            }
        }
        if( !empty($update->case_started_at) ){
            if( $update->case_started_at > $promo_code->case_finished_at ){
                $update->case_finished_at=date('Y-m-d 23:59:59',strtotime($update->case_started_at)+1*24*60*60);//plus 1 day
            }
        }
        if( !empty($update->case_finished_at) ){
            if( $update->case_finished_at < $promo_code->case_started_at ){
                $update->case_started_at=date('Y-m-d 00:00:00',strtotime($update->case_finished_at)-1*24*60*60);//minus 1 day
            }
        }
        return $update;
    }




    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return $this->findAll();
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