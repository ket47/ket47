<?php
namespace App\Models;
use CodeIgniter\Model;

class StoreModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'store_list';
    protected $primaryKey = 'store_id';
    protected $allowedFields = [
        'store_name_new',
        'store_phone',
        'store_email',
        'store_description_new',
        'store_tax_num',
        'store_company_name_new',
        'store_minimal_order',
        'store_delivery_allow',
        'store_delivery_cost',
        'store_pickup_allow',
        'store_time_preparation',
        'store_time_opens_0',
        'store_time_opens_1',
        'store_time_opens_2',
        'store_time_opens_3',
        'store_time_opens_4',
        'store_time_opens_5',
        'store_time_opens_6',
        'store_time_closes_0',
        'store_time_closes_1',
        'store_time_closes_2',
        'store_time_closes_3',
        'store_time_closes_4',
        'store_time_closes_5',
        'store_time_closes_6',
        'is_working',
        'validity',
        'owner_ally_ids'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_name'            => 'min_length[3]',
        'store_description'     => 'min_length[10]',
        'store_company_name'    => 'min_length[3]',
        'store_tax_num'         => 'exact_length[10,12]|integer'
    ];
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemCacheClear(){
        $this->itemCache=[];
        $this->resetQuery();
    }
    private $itemCache=[];
    public function itemGet( $store_id, $mode='all', $distanceToUserInclude=false ){
        if( $this->itemCache[$mode.$store_id]??0 ){
            return $this->itemCache[$mode.$store_id];
        }
        if( !$this->permit($store_id,'r') ){
            return 'forbidden';
        }
        $weekday=date('N')-1;
        $dayhour=date('H');
        $this->select("*");
        $this->select("store_time_opens_{$weekday} store_time_opens,store_time_closes_{$weekday} store_time_closes");
        $this->select("IF(is_working AND store_time_opens_{$weekday}<=$dayhour AND store_time_closes_{$weekday}>$dayhour,1,0) is_opened");

        $this->where('store_id',$store_id);
        $store = $this->get()->getRow();
        if( !$store ){
            return 'notfound';
        }
        if( $mode=='basic' ){
            $this->itemCache[$mode.$store_id]=$store;
            return $store;
        }
        
        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $LocationModel=model('LocationModel');
        
        $ImageModel=model('ImageModel');

        $store->is_writable=$this->permit($store_id,'w');
        $store->member_of_groups=$StoreGroupMemberModel->memberOfGroupsGet($store->store_id);
        $filter_loc=[
            'location_holder'=>'store',
            'location_holder_id'=>$store->store_id,
            'is_active'=>1
        ];
        if($distanceToUserInclude){
            $LocationModel->distanceToUserInclude();
        }
        $store->delivery_cost=$this->tariffRuleDeliveryCostGet( $store_id );
        $store->locations=$LocationModel->listGet($filter_loc);
        $filter=[
            'image_holder'=>'store',
            'image_holder_id'=>$store->store_id,
            'is_disabled'=>1,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>5
        ];
        $store->images=$ImageModel->listGet($filter);
        $filter=[
            'image_holder'=>'store_avatar',
            'image_holder_id'=>$store->store_id,
            'is_disabled'=>1,
            'is_deleted'=>0,
            'is_active'=>1
        ];
        $store->avatar=$ImageModel->listGet($filter);
        $this->itemCache[$mode.$store_id]=$store;
        return $store;
    }

    public function itemIsReady($store_id){
        $beforeCloseMargin=30*60;//30 min before closing
        $weekday=date('N')-1;
        $dayhour=date('H',time()+$beforeCloseMargin);
        $this->select("IF(is_working AND is_disabled=0 AND deleted_at IS NULL AND store_time_opens_{$weekday}<=$dayhour AND store_time_closes_{$weekday}>$dayhour,1,0) is_ready");
        $this->select("store_tax_num");
        $this->where('store_id',$store_id);
        $store = $this->get()->getRow();
        if( !$store || $store->is_ready==0 ){
            return 0;
        }
        if( empty($store->store_tax_num) || strlen($store->store_tax_num)<10 ){
            return 0;
        }
        /**
         * should I use balance caching instead?
         */
        $TransactionModel=model('TransactionModel');
        $filter=(object)[
            'tagQuery'=>"acc::supplier store:{$store_id}"
        ];
        $storeBalance=$TransactionModel->balanceGet($filter,'skip_permision_check');
        if($storeBalance<0){
            //return 0; until bug is fixed
        }
        return 1;     
    }
    
    public function itemCreate( $name ){
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $user_id=session()->get('user_id');
        $has_store_id=$this->where('owner_id',$user_id)->get()->getRow('store_id');
        if( !sudo() && $has_store_id ){
            return 'limit_exeeded';
        }
        $newstore=[
            'store_name_new'=>$name,
            'store_name'=>'    ',
            'store_description'=>'          ',
            'store_company_name'=>'   ',
            'store_tax_num'=>'0000000000'
        ];
        $store_id=$this->insert($newstore,true);
        if( $store_id ){
            $this->allowedFields[]='owner_id';
            $this->allowedFields[]='is_disabled';
            $this->update($store_id,['owner_id'=>$user_id,'is_disabled'=>1]);
            return $store_id;
        }
        return 0;
    }
    
    public function itemUpdate( $store ){
        if( empty($store->store_id) ){
            return 'noid';
        }
        if( !$this->permit($store->store_id,'w') ){
            return 'forbidden';
        }
        if( isset($store->is_primary) && !sudo() ){
            return 'forbidden';
        }
        model('ProductModel')->listUpdateValidity($store->store_id);
        if( sudo() ){
            $this->allowedFields[]='is_primary';
            $this->allowedFields[]='owner_id';
        }
        $this->update($store->store_id,$store);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUpdateGroup($store_id,$group_id,$is_joined){
        if( !$this->permit($store_id,'w') ){
            return 'forbidden';
        }
        $GroupModel=model('StoreGroupModel');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'not_found';
        }
        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $StoreGroupMemberModel->tableSet('store_group_member_list');
        $ok=$StoreGroupMemberModel->itemUpdate( $store_id, $group_id, $is_joined );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    
    public function itemDelete( $store_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ProductModel=model('ProductModel');
        $ProductModel->listDeleteChildren( $store_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('store', [$store_id]);
        
        $this->delete($store_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUnDelete( $store_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ProductModel=model('ProductModel');
        $ProductModel->listUnDeleteChildren( $store_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listUnDelete('store', [$store_id]);
        
        $this->allowedFields[]='deleted_at';
        $this->update($store_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDisable( $store_id, $is_disabled ){
        if( !$this->permit($store_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['store_id'=>$store_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function fieldApprove( $store_id, $field_name ){
        if( !sudo() ){
            return 'forbidden';
        }
        $new_value=$this->where('store_id',$store_id)->select("{$field_name}_new")->get()->getRow("{$field_name}_new");
        $this->allowedFields[]=$field_name;
        $data=[
            $field_name=>$new_value,
            "{$field_name}_new"=>""
        ];
        $this->update($store_id,$data);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function tariffRuleListGet( $store_id ){
        $this->permitWhere('r');
        $this->join('tariff_member_list','store_id');
        $this->join('tariff_list','tariff_id');
        $this->where('store_id',$store_id);
        $this->where('start_at<=NOW()');
        $this->where('finish_at>=NOW()');
        $this->where('tariff_list.is_disabled',0);
        $this->select("tariff_id,card_allow,cash_allow,delivery_allow,delivery_cost");
        $this->orderBy("delivery_allow DESC");
        $this->orderBy("card_allow DESC");
        return $this->get()->getResult();
    }

    public function tariffRuleDeliveryCostGet( $store_id ){
        $this->limit(1);
        $this->select('IF(delivery_cost>0,delivery_cost,store_delivery_cost) order_sum_delivery');
        $delivery_option=$this->tariffRuleListGet($store_id);
        if( isset($delivery_option[0]) ){
            return $delivery_option[0]->order_sum_delivery;
        }
        return 0;
    }
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter=null ){
        if( $filter['group_id']??0 ){
            $this->join('store_group_member_list','member_id=store_id');
            $this->where('group_id',$filter['group_id']);
        }
        if( $filter['owner_id']??0 ){//owner sees all owned stores
            $filter['is_active']=1;
            $filter['is_disabled']=1;
            $filter['is_deleted']=1;

            $owner_id=(int)$filter['owner_ally_ids'];
            $owner_ally_id=(int)$filter['owner_ally_ids'];
            $this->where("store_list.owner_id='$owner_id' OR FIND_IN_SET($owner_ally_id,store_list.owner_ally_ids)");
        }
        $this->filterMake( $filter );
        $weekday=date('N')-1;
        $dayhour=date('H');
        $this->select("store_time_opens_{$weekday} store_time_opens,store_time_closes_{$weekday} store_time_closes");
        $this->select("IF(is_working AND store_time_opens_{$weekday}<=$dayhour AND store_time_closes_{$weekday}>$dayhour,1,0) is_opened");
        $this->permitWhere('r');
        $this->orderBy("is_opened",'DESC');
        $this->join('image_list',"image_holder='store' AND image_holder_id=store_id AND is_main=1",'left');
        $this->select("{$this->table}.*,image_hash");
        $store_list= $this->get()->getResult();
        return $store_list;
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
    
    public function listPurge( $olderThan=1 ){
        $olderStamp= new \CodeIgniter\I18n\Time((-1*$olderThan)." hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }

    public function listNearGet($filter){
        if( !$filter['location_id'] ){
            return 'location_required'; 
        }
        $weekday=date('N')-1;
        $nextweekday=date('N')%7;
        $dayhour=date('H');

        $delivery_radius=getenv('delivery.radius');

        $permission_filter=$this->permitWhereGet('r','item');
        $LocationModel=model('LocationModel');
        $LocationModel->select("store_id,store_name,store_time_preparation,image_hash,is_working");
        $LocationModel->select("store_time_opens_{$weekday} store_time_opens,store_time_opens_{$nextweekday} store_next_time_opens,store_time_closes_{$weekday} store_time_closes,store_time_closes_{$nextweekday} store_next_time_closes");
        $LocationModel->select("IF(is_working AND store_time_opens_{$weekday}<=$dayhour AND store_time_closes_{$weekday}>$dayhour,1,0) is_opened");
        $LocationModel->join('store_list','store_id=location_holder_id');
        $LocationModel->join('image_list',"image_holder='store' AND image_holder_id=store_id AND image_list.is_main=1",'left');
        if( $permission_filter ){
            $LocationModel->where($permission_filter);
        }
        $LocationModel->orderBy("is_working",'DESC');
        $LocationModel->orderBy("is_opened",'DESC');
        $LocationModel->where("(is_primary=0 OR is_primary IS NULL)");
        $LocationModel->where("(store_list.deleted_at IS NULL AND store_list.is_disabled=0)");
        $store_list=$LocationModel->distanceListGet( $filter['location_id'], $delivery_radius, 'store' );
        if( !is_array($store_list) ){
            return 'not_found';
        }
        return $store_list;
    }

    public function primaryNearGet($filter){
        if( !$filter['location_id'] ){
            return 'location_required'; 
        }
        $weekday=date('N')-1;
        $dayhour=date('H');
        $PrefModel=model('PrefModel');
        $pref=$PrefModel->itemGet('delivery_radius');
        $delivery_radius=$pref->pref_value??15000;

        $permission_filter=$this->permitWhereGet('r','item');
        $LocationModel=model('LocationModel');

        $weekday=date('N')-1;
        $dayhour=date('H');
        $LocationModel->select("store_time_opens_{$weekday} store_time_opens,store_time_closes_{$weekday} store_time_closes");
        $LocationModel->select("store_id,store_name,store_time_preparation,is_primary,image_hash");
        $LocationModel->select("IF(is_working AND store_time_opens_{$weekday}<=$dayhour AND store_time_closes_{$weekday}>$dayhour,1,0) is_opened");
        $LocationModel->join('store_list','store_id=location_holder_id');
        $LocationModel->join('image_list',"image_holder='store' AND image_holder_id=store_id AND image_list.is_main=1",'left');
        if( $permission_filter ){
            $LocationModel->where($permission_filter);
        }
        $LocationModel->orderBy("is_opened",'DESC');
        $LocationModel->where("is_primary",1);
        $LocationModel->limit(1);
        $result=$LocationModel->distanceListGet( $filter['location_id'], $delivery_radius, 'store' );
        if( !isset($result[0]) ){
           return 'not_found';
        }
        $primary_store=$result[0];
        $ProductModel=model('ProductModel');
        $primary_store->category_list=$ProductModel->groupTreeGet(['store_id'=>$primary_store->store_id],'all');
        return $primary_store;
    }

    /////////////////////////////////////////////////////
    //STORE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function ownerListGet($store_id){
        $user_id=session()->get('user_id');
        if( !($user_id>0) ){
            return 'unauthorized';
        }
        if( !$store_id ){
            return 'nostore';
        }
        $this->where('store_id',$store_id);
        $this->permitWhere('w');//have read access only store administrators
        $store=$this->get()->getRow();

        if( !$store){
            return 'notfound';
        }
        $owners=$store->owner_id.($store->owner_ally_ids?','.$store->owner_ally_ids:'');
        if($owners){ 
            $UserModel=model('UserModel');
            $UserModel->select('user_id,user_phone,user_name');
            $UserModel->where("user_id IN($owners)");
            $owner_allys=$UserModel->get()->getResult();
        } else {
            $owner_allys=[];
        }
        return $owner_allys??[];
    }

    public function ownerSave($action, $store_id, $new_owner_id=null, $new_owner_phone=null){
        if(!$store_id ){
            return 'notfound';
        }
        $UserModel=model('UserModel');
        if($new_owner_id){
            $owner_ally_id=$UserModel->where('user_id',$new_owner_id)->get()->getRow('user_id');
        } else 
        if($new_owner_phone){
            $owner_ally_id=$UserModel->where('user_phone',$new_owner_phone)->get()->getRow('user_id');
        }
        if( !$owner_ally_id ){
            return 'notfound';
        }
        $user_id=session()->get('user_id');
        if( !($user_id>0) ){
            return 'unauthorized';
        }
        $this->where('store_id',$store_id);
        if( !sudo() ){
            $this->where('owner_id',$user_id);
        }
        $this->select('owner_id,owner_ally_ids');
        $store_owners=$this->get()->getRow();

        if(!$store_owners){
            return 'forbidden';
        }
        $owner_ally_ids=explode(',',"0,$store_owners->owner_ally_ids,$store_owners->owner_id");

        if( $action=='add' ){
            $owners=array_merge($owner_ally_ids,[$owner_ally_id]);
        } else
        if( $action=='delete' ){
            $owners=array_diff($owner_ally_ids,[$owner_ally_id]);
        } else
        if( $action=='swap' ){
            $this->allowedFields[]='owner_id';
            $this->update($store_id,['owner_id'=>$owner_ally_id]);
            $owners=array_merge($owner_ally_ids,[$owner_ally_id]);
        } else {
            return 'wrong_action';
        }

        $owners=array_unique($owners,SORT_NUMERIC);
        array_shift($owners);
        $owner_list=implode(',',$owners);
        $sql="
            UPDATE
                store_list sl
                    LEFT JOIN
                product_list pl USING(store_id)
                    LEFT JOIN
                image_list ils ON ils.image_holder_id=store_id AND ils.image_holder='store'
                    LEFT JOIN
                image_list ilp ON ilp.image_holder_id=product_id AND ilp.image_holder='product'
            SET
                sl.owner_ally_ids='$owner_list',
                pl.owner_ally_ids='$owner_list',
                ils.owner_ally_ids='$owner_list',
                ilp.owner_ally_ids='$owner_list'
            WHERE
                sl.store_id='$store_id'";
        $this->query($sql);
        return $this->db->affectedRows()?'ok':'idle';
    }
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $data ){
        $data['is_disabled']=1;
        $data['owner_id']=session()->get('user_id');
        if( !$this->permit($data['image_holder_id'], 'w') ){
            return 0;
        }
        if($data['image_holder']=='store_avatar'){
            $limit=1;
        } else {
            $limit=5;
        }
        $ImageModel=model('ImageModel');
        return $ImageModel->itemCreate($data,$limit);
    }

    public function imageUpdate( $data ){
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemUpdate($data);
        }
        return 0;
    }
    
    public function imageDisable( $image_id, $is_disabled ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');
        $ok=$ImageModel->itemDisable( $image_id, $is_disabled );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }    
    
    public function imageDelete( $image_id ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $store_id=$image->image_holder_id;
        if( !$this->permit($store_id,'w') || $image->image_holder!='store' ){
            return 'forbidden';
        }
        $ImageModel->itemDelete( $image_id );
        $ok=$ImageModel->itemPurge( $image_id );
        if( $ok ){
            return 'ok';
        }
        return 'idle';
    }
    
    public function imageOrder( $image_id, $dir ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $store_id=$image->image_holder_id;
        if( !$this->permit($store_id,'w') ){
            return 'forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
}
