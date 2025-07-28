<?php
namespace App\Models;
use CodeIgniter\Model;

class TransactionModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'transaction_list';
    protected $primaryKey = 'trans_id';
    protected $allowedFields = [
        'trans_date',
        'trans_amount',
        'trans_data',
        'trans_role',
        'trans_description',
        'updated_by',
        'created_by'
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    protected $validationRules    = [
        'trans_amount'    => 'required',
        'trans_role'      => 'required',
    ];
    
    public function itemGet( $trans_id ){
        $this->permitWhere('r');
        $this->select("{$this->table}.*,usu.user_name updated_user_name,usc.user_name created_user_name");
        $this->join('user_list usu','updated_by=usu.user_id','left');
        $this->join('user_list usc','created_by=usc.user_id','left');
        $this->where('trans_id',$trans_id);
        $trans=$this->get(1)->getRow();
        if( !$trans ){
            return 'notfound';
        }
        if( $trans->trans_data ){
            $trans->trans_data=json_decode($trans->trans_data);
        }

        $TransactionTagModel=model('TransactionTagModel');
        $trans->tags=$TransactionTagModel->listGet($trans_id);
        return $trans;
    }

    public function itemFind( object $filter ){
        if( !($filter->tagQuery??null) ){
            return null;
        } 
        $TransactionTagModel=model('TransactionTagModel');
        $tagWhere=$TransactionTagModel->tagWhereGet($filter->tagQuery);
        $tagCount=$TransactionTagModel->queriedTagCountGet();

        $this->join('transaction_tag_list','trans_id');
        $this->where($tagWhere);
        $this->groupBy('trans_id');
        /**
         * here we should limit selected columns not *
         */
        $this->select("transaction_list.*,COUNT(link_id) matched_tags");
        $this->having("matched_tags='$tagCount'");

        $this->permitWhere('r');
        $this->limit(1);
        $trans=$this->get()->getRow();
        if( $trans?->trans_data ){
            $trans->trans_data=json_decode($trans->trans_data);
        }
        return $trans;
    }

    public function itemCreate( object $trans ){
        if( !sudo() ){
            return 0;
        }
        if( !($trans->trans_amount??0) ){
            return -1;
        }
        if( !($trans->trans_date??0) ){
            $trans->trans_date=date('Y-m-d H:i:s'); 
        }
        $trans->created_by=$trans->updated_by=session()->get('user_id');

        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='owner_ally_ids';

        $trans_id=$this->insert($trans,true);
        if( !$trans_id ){
            return 0;
        }
        $TransactionTagModel=model('TransactionTagModel');
        $TransactionTagModel->listCreate( $trans_id, $trans->tags, $trans->trans_role );
        if( empty($trans->owner_ally_ids) ){//if allys not set copy them from parent object
            $this->itemOwnerAllysUpdate( $trans_id );
        }
        return $trans_id;
    }

    /**
     * copy owner_ally_ids from parent object (store or courier)
     */
    private function itemOwnerAllysUpdate( $trans_id ){
        $TransactionTagModel=model('TransactionTagModel');
        $parent_owner_ally_ids=$TransactionTagModel->parentOwnerAllysGet($trans_id);
        $this->allowedFields[]='owner_ally_ids';
        return $this->update($trans_id,['owner_ally_ids'=>$parent_owner_ally_ids]);
    }

    public function itemCreateOnce( object $trans ){
        $created=$this->itemFind($trans);
        if( $created ){
            return $created->trans_id;
        }
        return $this->itemCreate($trans);
    }

    public function itemUpdate( object $trans ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( $trans->trans_amount==0 ){
            return -1;
        }
        $this->permitWhere('w');

        $trans->updated_by=session()->get('user_id');
        $this->update($trans->trans_id,$trans);
        $result=$this->db->affectedRows()?'ok':'idle';
        if( $result!='ok' ){
            return $result;
        }
        $TransactionTagModel=model('TransactionTagModel');
        $TransactionTagModel->listUpdate($trans);
        if( empty($trans->owner_ally_ids) ){//if allys not set copy them from parent object
            $this->itemOwnerAllysUpdate( $trans->trans_id );
        }
        return $result;
    }
    
    public function itemDelete( int $trans_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( !$trans_id ){//if $trans_id==0 then it deletes all transactions
            return 'forbidden';
        }
        $this->permitWhere('w');
        $this->where('trans_id',$trans_id)->delete();
        $result=$this->db->affectedRows()?'ok':'idle';
        return $result;
    }
    
    public function allowEnable(){
        $this->allowedFields[]='is_disabled';
    }

    public function listFind( object $filter ){
        if( !($filter->tagQuery??null) ){
            return null;
        } 
        $TransactionTagModel=model('TransactionTagModel');
        $tagWhere=$TransactionTagModel->tagWhereGet($filter->tagQuery);
        $tagCount=$TransactionTagModel->queriedTagCountGet();

        $this->join('transaction_tag_list','trans_id');
        $this->where($tagWhere);
        $this->groupBy('trans_id');
        $this->having("matched_tag_count='$tagCount'");
        $this->permitWhere('r');

        $this->select("trans_id,trans_description,trans_amount,COUNT(*) matched_tag_count");
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

    private function userTagSqlGet(){
        $tag_select_sql=",
        CONCAT( 
            COALESCE(MAX(CONCAT('|store:',tag_id,'#продавец ',store_name)),''),
            COALESCE(MAX(CONCAT('|courier:',tag_id,'#курьер ',courier_name)),''),
            COALESCE(MAX(CONCAT('|order:',tag_id,'#заказ ',order_id)),'')
        ) tags";
        $tag_table_sql="
            transaction_tag_list ttl USING(trans_id)
                LEFT JOIN
            store_list ON ttl.tag_name='store' AND ttl.tag_id=store_id
                LEFT JOIN
            courier_list ON ttl.tag_name='courier' AND ttl.tag_id=courier_id
                LEFT JOIN
            order_list ON ttl.tag_name='order' AND ttl.tag_id=order_id
        ";
        return [
            'select'=>$tag_select_sql,
            'table'=>$tag_table_sql
        ];
    }

    private function adminTagSqlGet(){
        $tag_select_sql=",
        CONCAT( 
            GROUP_CONCAT(COALESCE(CONCAT('|acc::',tag_type,'#счет ',group_name),'') SEPARATOR ' '),
            COALESCE(MAX(CONCAT('|store:',tag_id,'#продавец ',store_name)),''),
            COALESCE(MAX(CONCAT('|courier:',tag_id,'#курьер ',courier_name)),''),
            COALESCE(MAX(CONCAT('|order:',tag_id,'#заказ ',order_id)),'')
        ) tags";
        $tag_table_sql="
            transaction_tag_list ttl USING(trans_id)
                LEFT JOIN
            transaction_account_list ON tag_name='acc' AND tag_type=group_type
                LEFT JOIN
            store_list ON ttl.tag_name='store' AND ttl.tag_id=store_id
                LEFT JOIN
            courier_list ON ttl.tag_name='courier' AND ttl.tag_id=courier_id
                LEFT JOIN
            order_list ON ttl.tag_name='order' AND ttl.tag_id=order_id
        ";
        return [
            'select'=>$tag_select_sql,
            'table'=>$tag_table_sql
        ];
    }
    
    public function listGet( object $filter ){
        $start_case= $filter->start_at?"trans_date>'{$filter->start_at} 00:00:00'":"1";
        $finish_case=$filter->finish_at?"trans_date<'{$filter->finish_at} 23:59:59'":"1";
        $permission=$this->permitWhereGet('r','item');

        $TransactionTagModel=model('TransactionTagModel');
        $searchWhere='';
        if( $filter->searchQuery??null ){
            $this->like('trans_description', $filter->searchQuery);
            $this->orLike('trans_amount', $filter->searchQuery);
            $searchWhere.='AND (';
            $searchWhere.="trans_description LIKE '%" .$this->escapeLikeString($filter->searchQuery) . "%' ESCAPE '!'";
            $searchWhere.="OR trans_amount LIKE '%" .$this->escapeLikeString($filter->searchQuery) . "%' ESCAPE '!'";
            $searchWhere.=')';
        }
        $tagWhere='';
        if($filter->tagQuery??null){
            $tagCase=$TransactionTagModel->tagWhereGet($filter->tagQuery);
            if($tagCase){
                $tagWhere=" AND ($tagCase)";
            }
        }
        $limit='';
        if($filter->limit??null){
            $limit="LIMIT {$filter->limit} OFFSET {$filter->offset}";
        }

        if( sudo() ){
            $tag_sql=$this->adminTagSqlGet();
        } else {
            $tag_sql=$this->userTagSqlGet();
        }

        $having="";
        $tagCount=$TransactionTagModel->queriedTagCountGet();
        if( $tagCount>0 ){
            $having="HAVING matched_tag_count='$tagCount'";
        }
        $sql_create_inner="
            CREATE TEMPORARY TABLE tmp_ledger_inner AS(
                SELECT
                    tl.*
                    {$tag_sql['select']}
                FROM
                (SELECT
                    trans_id,
                    trans_description,
                    trans_amount,
                    trans_date,
                    trans_role,
                    IF($start_case,1,0) after_start,
                    SUM(IF(tag_name='acc',IF(tag_option='credit',-1,1),0)) amount_sign,
                    COUNT(link_id) matched_tag_count
                FROM
                    transaction_list
                        JOIN
                    transaction_tag_list USING(trans_id)
                WHERE
                    $permission
                    $searchWhere
                    $tagWhere
                    AND $finish_case
                    AND transaction_list.is_disabled=0
                    AND transaction_list.deleted_at IS NULL
                GROUP BY trans_id
                $having
                ) AS tl
                    JOIN
                {$tag_sql['table']}
                GROUP BY trans_id
                ORDER BY trans_date DESC,trans_id DESC
            )
        ";
        $sql_ledger_get="
            SELECT
                *
            FROM
                tmp_ledger_inner
            WHERE
                $start_case
            AND $finish_case
            $limit
        ";
        $sql_meta_get="
            SELECT
                SUM(IF(after_start AND amount_sign>0,trans_amount,0)) sum_debit,
                SUM(IF(after_start AND amount_sign<0,trans_amount,0)) sum_credit,
                SUM(IF(NOT after_start,amount_sign*trans_amount,0)) sum_start,
                SUM(amount_sign*trans_amount) sum_finish
            FROM
                tmp_ledger_inner
        ";
        $this->query($sql_create_inner);

        //ql($this);

        $ledger =$this->query($sql_ledger_get)->getResult();
        $meta   =$this->query($sql_meta_get)->getRow();
        return [
            'ledger'=>$ledger,
            'meta'=>$meta
        ];
    }

    public function queryDelete( $query ){
        $TransactionTagModel=model('TransactionTagModel');
        $tagSubquery=$TransactionTagModel->tagSubqueryGet($query);
        $this->permitWhere('w');
        $this->whereIn('trans_id',$tagSubquery);
        return $this->delete(null,true);
    }

    public function listDeleteChildren( $holder,$holder_id ){
        $OrderModel=model('OrderModel');
        if( !$OrderModel->permit($holder_id,'w') ){
            return 'forbidden';
        }
        return $this->queryDelete("$holder:$holder_id");
    }

    public function listPurge( $olderThan=1 ){
        $olderStamp= new \CodeIgniter\I18n\Time((-1*$olderThan)." hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }

    public function balanceGet( object $filter, $mode='check_permission' ){
        $permission_where='1=1';
        if( $mode=='check_permission' ){
            $permission_where=$this->permitWhereGet('r','item');
        }
        if( !($filter->tagQuery??null) ){
            return null;
        } 
        $TransactionTagModel=model('TransactionTagModel');
        $tagWhere=$TransactionTagModel->tagWhereGet($filter->tagQuery);
        $tagCount=$TransactionTagModel->queriedTagCountGet();
        $balance_sql="
            SELECT
                SUM(`amount`) `balance`
            FROM (
                SELECT
                    IF(`tag_option`='debit',`trans_amount`,-`trans_amount`) `amount`,
                    COUNT(`link_id`) `matched_tag_count`
                FROM
                    `transaction_list`
                        JOIN
                    `transaction_tag_list` USING(`trans_id`)
                WHERE
                    ($tagWhere)
                    AND `transaction_list`.`is_disabled`=0
                    AND `transaction_list`.`deleted_at` IS NULL
                    AND $permission_where
                GROUP BY `trans_id`
                HAVING `matched_tag_count`='$tagCount') t";
        return $this->query($balance_sql)->getRow('balance');
    }
    public function serviceActGet( object $filter, $mode='check_permission' ){
        $permission_where='1=1';
        if( $mode=='check_permission' ){
            $permission_where=$this->permitWhereGet('r','item');
        }
        if( !($filter->tagQuery??null) ){
            return null;
        } 
        $TransactionTagModel=model('TransactionTagModel');
        $tagWhere=$TransactionTagModel->tagWhereGet($filter->tagQuery);
        $tagCount=$TransactionTagModel->queriedTagCountGet();
        $balance_sql="
            SELECT
                trans_role,
                SUM(`amount`) `balance`
            FROM (
                SELECT
                    trans_role,
                    IF(`tag_option`='debit',`trans_amount`,-`trans_amount`) `amount`,
                    COUNT(`link_id`) `matched_tag_count`
                FROM
                    `transaction_list`
                        JOIN
                    `transaction_tag_list` USING(`trans_id`)
                WHERE
                    ($tagWhere)
                    AND `transaction_list`.`is_disabled`=0
                    AND `transaction_list`.`deleted_at` IS NULL
                    AND $permission_where
                GROUP BY `trans_id`
                HAVING `matched_tag_count`='$tagCount') t
            GROUP BY trans_role
                ";
        return $this->query($balance_sql)->getResult();
    }
}