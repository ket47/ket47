<?php
namespace App\Models;
use CodeIgniter\Model;

class PromoModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'promo_list';
    protected $primaryKey = 'promo_id';
    protected $allowedFields = [
        'promo_name',
        'promo_value',
        'promo_share',
        'promo_order_id',
        'promo_activator_id',
        'is_disabled',
        'is_used',
        'is_summable',
        'expired_at',
        'owner_id'
        ];

    protected $useSoftDeletes = false;
    protected $promo_lifetime=180*24*60*60;
    protected $promo_share=20;

    public function setLifetime( int $lifetime_day ){
        if( $lifetime_day>100 || $lifetime_day<1 ){
            return false;
        }
        $this->promo_lifetime=$lifetime_day*24*60*60;
    }
    
    public function setShare( int $share ){
        if( $share>100 || $share<10 ){
            return false;
        }
        $this->promo_share=$share;
    }

    public function itemGet($promo_id){
        if( !$promo_id ){
            return null;
        }
        $this->select("*,ROUND(promo_value/(promo_share/100)) min_order_sum_product");
        $this->permitWhere('r');
        $this->where('promo_id',$promo_id);
        return $this->get()->getRow();
    }
    
    public function itemCreate(int $owner_id,int $promo_value,string $promo_name, $promo_activator_id=null, $is_summable=0){
        $promo=[
            'owner_id'=>$owner_id,
            'promo_name'=>$promo_name,
            'promo_value'=>$promo_value,
            'promo_share'=>$this->promo_share,
            'promo_activator_id'=>$promo_activator_id,
            'is_disabled'=>$promo_activator_id?1:0,
            'is_summable'=>$is_summable,
            'expired_at'=>date('Y-m-d H:i:s',time()+$this->promo_lifetime)
        ];
        return $this->insert($promo,true);
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }

    public function itemOrderDisable( $order_id, $is_disabled ){
        $promo=$this->where('promo_order_id',$order_id)->get()->getRow();
        if( !$promo ){
            return 'idle';
        }
        $this->permitWhere('w');
        $this->update($promo->promo_id,['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemOrderUse($order_id){
        $promo=$this->where('promo_order_id',$order_id)->get()->getRow();
        if( !$promo ){
            return false;
        }
        $this->transBegin();
        $this->permitWhere('w');
        $this->update($promo->promo_id,['is_used'=>1,'is_disabled'=>0]);

        $this->itemActivate($promo->promo_id);
        $this->transCommit();
    }

    private function itemActivate($promo_activator_id){
        $this->where('promo_activator_id',$promo_activator_id);
        $this->update(null,['is_disabled'=>0]);
        
        $activated_promo=$this->where('promo_activator_id',$promo_activator_id)->get()->getRow();
        if($activated_promo->owner_id??0){
            $this->userNotify($activated_promo->owner_id,'activated',(object)['promo_name'=>$activated_promo->promo_name]);
        }
    }

    private function itemOrderApply($order_id,$promo_value){
        if( !$order_id ){
            return true;
        }
        $order=(object)[
            'order_id'=>$order_id,
            'order_sum_promo'=>$promo_value
        ];
        $OrderModel=model('OrderModel');
        $OrderModel->fieldUpdateAllow('order_sum_promo');
        $OrderModel->itemUpdate($order);
    }

    public function itemUnlink( int $order_id ){
        $this->permitWhere('w');
        $this->where('promo_order_id',$order_id);
        $this->update(null,['promo_order_id'=>null]);
        $this->itemOrderApply($order_id,0);//all promos deselected update order to 0
    }

    /**
     * Links and Unlinks promos and orders
     */
    public function itemLink( int $order_id, int $promo_id=null ){
        $this->itemUnlink($order_id);
        $promo=$this->itemGet($promo_id);
        if( !$promo ){//unlinking
            return 'ok';
        }
        $this->transBegin();
            $this->itemOrderApply($promo->promo_order_id,0);
            $this->itemOrderApply($order_id,$promo->promo_value);
            $this->permitWhere('w');
            $this->update($promo_id,['promo_order_id'=>$order_id]);
            $result=$this->db->affectedRows()?'ok':'idle';
        $this->transCommit();
        return $result;
    }

    public function itemLinkGet($order_id){
        $this->select("*,ROUND(promo_value/(promo_share/100)) min_order_sum_product");
        $this->permitWhere('r');
        $this->where('promo_order_id',$order_id);
        return $this->get()->getRow();
    }
    
    public function listGet( $user_id=null, $type='active', $mode='all' ){
        $this->permitWhere('r');
        if( $user_id ){
            $this->where('owner_id',$user_id);
        }
        $this->limit(30);
        if( $type == 'active' ){
            $this->select("*,ROUND(promo_value/(promo_share/100)) min_order_sum_product,DATE_FORMAT(expired_at,'%d.%m.%Y') expiration");
            $this->where('promo_list.is_disabled',0);
            $this->where('is_used',0);
            $this->where('expired_at>NOW()');
        } else {
            $this->select("*,expired_at<NOW() is_expired");
            $this->where('(promo_list.is_disabled OR is_used OR expired_at<NOW())');
            $this->orderBy('promo_order_id');
        }
        if( $mode=='count' ){
            $this->select('COUNT(*) count');
            return $this->get()->getRow('count');
        }
        $this->where('is_summable',0);
        $this->orderBy('expired_at');
        return $this->get()->getResult();
    }

    public function listFilteredGet( object $filter ){
        $this->join('promo_code_list','promo_code_id','left');

        if( $filter->user_id??0 ){
            $this->where("promo_list.owner_id={$filter->user_id}");
        }
        if( $filter->order_id??0 ){
            $filter->type='active';

            $OrderModel=model('OrderModel');
            $order=$OrderModel->itemGet($filter->order_id,'basic');
            if(!is_object($order)){
                return $order;
            }
            
            $EntryModel=model('EntryModel');
            $entries=$EntryModel->where('order_id',$filter->order_id)->select('GROUP_CONCAT(product_id) pids')->get()->getRow();
            if(!$entries){
                return 'noentries';
            }

            $this->where("(case_product_id IS NULL  OR case_product_id IN({$entries->pids}) )");
            $this->where("(case_store_id IS NULL    OR case_store_id={$order->order_store_id})");
            $this->where("(case_user_id IS NULL     OR case_user_id={$order->owner_id})");
            $this->where("(case_min_sum IS NULL     OR case_min_sum<={$order->order_sum_product})");
        }

        if( $filter->type=='active' ){
            $this->where('expired_at>NOW()');
            $this->where('promo_list.is_disabled',0);
            $this->where('is_used',0);
            $this->orderBy('expired_at');
        }

        $this->where('is_summable',0);
        $this->permitWhere('r');
        return $this->get()->getResult();

    }
    
    public function listCreate($user_id,$inviter_user_id=null){
        $cnt=$this->select('COUNT(*) cnt')->where('owner_id',$user_id)->get()->getRow('cnt');
        if($cnt){
            return 'already_have_promos';
        }
        if($user_id==$inviter_user_id){
            return 'cant_invite_yourself';
        }
        $UserModel=model('UserModel');
        $new_user_name=$UserModel->select('user_name')->where('user_id',$user_id)->get()->getRow('user_name');

        $parent_value=200;
        $parent_name="Новому клиенту";

        $child_value=100;
        $child_name="За приглашённого друга: {$new_user_name}";

        $promo_voucher_count=1;
        $this->transBegin();
            for($i=0;$i<$promo_voucher_count;$i++){
                $promo_activator_id=$this->itemCreate($user_id,$parent_value,$parent_name);
                if($inviter_user_id>0){
                    $this->itemCreate($inviter_user_id,$child_value,$child_name,$promo_activator_id,1);
                }
            }
        $this->transCommit();

        $this->userNotify($user_id,'created',(object)['count'=>$promo_voucher_count,'value'=>$parent_value,'promo_name'=>$parent_name]);
        if($inviter_user_id>0){
            $this->userNotify($inviter_user_id,'created',(object)['count'=>$promo_voucher_count,'value'=>$child_value,'promo_name'=>$child_name]);
        }
        return 'ok';
    }

    public function listPurge(){

    }

    function userNotify($user_id,$template,$promo_context){
        $context=[
            'promo'=>$promo_context,
            'expired_at'=>date('d.m.Y',time()+$this->promo_lifetime)
        ];
        helper('job');
        if( $template=='activated' ){
            $template_file="messages/promo/activated.php";
        } else 
        if( $template=='created' ){
            $template_file="messages/promo/created.php";
        } else {
            return;
        }
        $cust_sms=(object)[
            'message_reciever_id'=>$user_id,
            'message_transport'=>'message',
            'template'=>$template_file,
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"customer Promo Notify #$user_id",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$cust_sms]]]
                ]
        ];
        jobCreate($notification_task);
    }

    public function bonusOrderCalculate( int $order_id ){
        $EntryModel=model('EntryModel');
        $EntryModel->permitWhere('r');
        $EntryModel->where('order_id',$order_id);
        $EntryModel->join('perk_list',"perk_holder='product' AND perk_holder_id=product_id AND perk_type='product_bonusgain'");
        $EntryModel->select("SUM(entry_quantity*perk_value) bonus_gain");
        $EntryModel->select("SUM((entry_price-1)*entry_quantity) bonus_spend");
        return $EntryModel->get()->getRow();
    }

    public function bonusTotalGet( $user_id ){
        $this->permitWhere('r');
        $this->where('owner_id',$user_id);
        $this->where('is_summable',1);
        $this->where('expired_at>NOW()');
        $this->select('SUM(promo_value) bonus_total');
        return $this->get()->getRow('bonus_total');
    }
}