<?php
namespace App\Models;
use CodeIgniter\Model;

class StoreModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    protected function initialize(){
        $this->query("SET character_set_results = utf8mb4, character_set_client = utf8mb4, character_set_connection = utf8mb4, character_set_database = utf8mb4, character_set_server = utf8mb4");
    }
    
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
        'store_delivery_radius',
        'store_delivery_methods',
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
    protected $selectableFields = [
        'store_id',
        'store_name',
        'store_description',
        'store_phone',
        'store_email',
        //'store_tax_num',
        'store_company_name',
        'store_time_preparation',
        'store_minimal_order',
        'store_delivery_allow',
        'store_delivery_cost',
        'store_delivery_radius',
        'store_pickup_allow',
        'is_working',
        'owner_id',
        'owner_ally_ids',
    ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_name'            => 'min_length[3]',
        'store_description'     => 'min_length[10]',
        'store_company_name'    => 'min_length[3]',
        'store_tax_num'         => 'exact_length[10,12]|integer'
    ];
    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }
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
        $beforeCloseMargin=getenv('store.beforeCloseMargin');
        $weekday=date('N')-1;
        $dayhour=date('H',time()+$beforeCloseMargin);

        $is_writable=$this->permit($store_id,'w');
        if( $is_writable ){
            $this->select("*");
        } else {
            $this->select($this->selectableFields);
        }
        $this->select("store_time_opens_{$weekday} store_time_opens,store_time_closes_{$weekday} store_time_closes");
        $this->select("is_working AND IS_STORE_OPEN(store_time_opens_{$weekday},store_time_closes_{$weekday},$dayhour) is_opened");

        $this->where('store_id',$store_id);
        $store = $this->get()->getRow();

        if( !$store ){
            return 'notfound';
        }
        $store->is_writable=$is_writable;
        if( $mode=='basic' ){
            $this->itemCache[$mode.$store_id]=$store;
            return $store;
        }
        
        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $LocationModel=model('LocationModel');
        
        $ImageModel=model('ImageModel');

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

    public function itemTimetableGet(int $store_id){
        $this->permitWhere('r');
        $this->where('store_id',$store_id);
        $this->select('
            (is_working AND is_disabled=0) is_active,
            store_time_opens_0,
            store_time_closes_0,
            store_time_opens_1,
            store_time_closes_1,
            store_time_opens_2,
            store_time_closes_2,
            store_time_opens_3,
            store_time_closes_3,
            store_time_opens_4,
            store_time_closes_4,
            store_time_opens_5,
            store_time_closes_5,
            store_time_opens_6,
            store_time_closes_6,
            store_time_preparation,
        ');
        $store=$this->get()->getRow();
        if( !$store ){
            return 'notfound';
        }
        return $store;
    }

    public function itemDeliveryMethodsGet(int $store_id){
        $this->permitWhere('r');
        $this->where('store_id',$store_id);
        $this->select('store_id,store_delivery_methods,store_phone,store_name');
        $store=$this->get()->getRow();
        if( !$store ){
            return 'notfound';
        }

        $ImageModel=model('ImageModel');
        $filter=[
            'image_holder'=>'store_dmethods',
            'image_holder_id'=>$store_id,
            'is_disabled'=>1,
            'is_deleted'=>0,
            'is_active'=>1
        ];
        $store->images=$ImageModel->listGet($filter);
        return $store;
    }

    public function itemIsReady( int $store_id ){
        $beforeCloseMargin=getenv('store.beforeCloseMargin');//40 min before closing
        $weekday=date('N')-1;
        $dayhour=date('H',time()+$beforeCloseMargin);
        $this->select("(is_working AND is_disabled=0 AND deleted_at IS NULL AND LENGTH(store_tax_num)>=10) AS is_ready");
        $this->select("IS_STORE_OPEN(store_time_opens_{$weekday},store_time_closes_{$weekday},$dayhour) is_open");
        $this->where('store_id',$store_id);
        return $this->get()->getRow();
    }

    public function itemOwnedGet( int $owner_id ){
        $this->where('owner_id',$owner_id);
        $this->orWhere("FIND_IN_SET({$owner_id},owner_ally_ids)>0");
        $this->select('store_id,store_name,owner_id,owner_ally_ids');
        $this->limit(1);
        $store=$this->find();
        if( isset($store[0]['store_id']) ){
            return $store[0];
        }
        return null;
    }

    public function itemBalanceGet( int $store_id=null ){
        $TransactionModel=model('TransactionModel');
        $filter=(object)[
            'tagQuery'=>"acc::supplier store:{$store_id}"
        ];
        return $TransactionModel->balanceGet($filter,'skip_permision_check');
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
            $this->ownerGroupUpdate([$user_id]);
            return $store_id;
        }
        return 0;
    }
    
    private function itemRestrictedFilterout( $text ){
        return trim(str_ireplace([
            'Симф',
            'Крым',
            'симф',
            'крым',
        ],'***',$text));
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
        if($store->store_description_new??null ){
            $store->store_description_new=$this->itemRestrictedFilterout($store->store_description_new);
        }
        if($store->store_name_new??null ){
            $store->store_name_new=$this->itemRestrictedFilterout($store->store_name_new);
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
        $this->itemCacheGroupDelete( $store_id );

        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $StoreGroupMemberModel->tableSet('store_group_member_list');
        $ok=$StoreGroupMemberModel->itemUpdate( $store_id, $group_id, $is_joined );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }

    public function itemCacheGroupGet( int $store_id ){
        $this->where('store_id',$store_id);
        $cache_group_json=$this->select("store_data->>'$.cache_groups' cache_groups")->get()->getRow('cache_groups');
        $cache_group=json_decode($cache_group_json);
        if( $cache_group && $cache_group->expired_at<time() ){
            return $cache_group;
        }
        return $this->itemCacheGroupCreate($store_id);
    }

    private $cacheGroupLiveTime=24*60*60;
    public function itemCacheGroupCreate( int $store_id ){
        $store_groups=[];
        $product_groups=[];

        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $StoreGroupMemberModel->where('member_id',$store_id);
        $store_group_rows=$StoreGroupMemberModel->select('group_id')->get()->getResult();
        foreach($store_group_rows as $row){
            if( empty($row->group_id) ){
                continue;
            }
            $store_groups[]=$row->group_id;
        }

        $ProductGroupMemberModel=model('ProductGroupMemberModel');
        $ProductGroupMemberModel->join('product_group_list','group_id');
        $ProductGroupMemberModel->join('product_list','member_id=product_id');
        $ProductGroupMemberModel->where('store_id',$store_id)->groupBy('group_id');
        $product_group_paths=$ProductGroupMemberModel->select('group_path_id')->get()->getResult();
        foreach($product_group_paths as $path){
            $parts=explode('/',$path->group_path_id??'');
            if( !$parts[1] ){
                continue;
            }
            $product_groups[]=$parts[1];//user parent group_id
        }

        $cache=(object) [
            'store_groups'=>$store_groups,
            'product_groups'=>array_values(array_unique($product_groups,SORT_NUMERIC)),
            'expired_at'=>time()+$this->cacheGroupLiveTime
        ];
        $this->itemDataSave($store_id,(object) ['cache_groups'=>$cache]);
        return $cache;
    }

    public function itemCacheGroupDelete( int $store_id ){
        $this->itemDataSave($store_id,(object) ['cache_group'=>null]);
    }

    public function itemDataSave( int $store_id, object $data_update ){
        $path_value='';
        foreach($data_update as $path=>$value){
            if( is_object($value) ){
                $path_value.=','.$this->db->escape("$.$path").",CAST('".json_encode($value)."' AS JSON)";
            } else {
                $path_value.=','.$this->db->escape("$.$path").','.$this->db->escape($value);
            }
        }
        $this->set("store_data","JSON_SET(IFNULL(`store_data`,'{}'){$path_value})",false);
        $this->fieldUpdateAllow('store_data');
        $this->update($store_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( $store_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ProductModel=model('ProductModel');
        $ProductModel->listDeleteChildren( $store_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('store', [$store_id]);
        
        $this->permitWhere('w');
        $this->where('store_id',$store_id)->delete();
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
    
    public function itemDisable( $store_id, $is_disabled, $checkPermission=true ){
        if( $checkPermission && !$this->permit($store_id,'w','disabled') ){
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

    public function tariffRuleListGet( $store_id, $tariff_order_mode ){//this function is messy OMG
        $this->permitWhere('r');
        $this->join('tariff_member_list','store_id');
        $this->join('tariff_list','tariff_id');
        $this->where('store_id',$store_id);
        $this->where('start_at<=NOW()');
        $this->where('finish_at>=NOW()');
        $this->where('tariff_list.is_disabled',0);
        $this->where('order_allow',1);
        $this->where('is_shipment',0);
        $this->select("tariff_id,card_allow,cash_allow,delivery_allow,delivery_cost,delivery_fee,order_cost,order_fee,card_fee,cash_fee,credit_fee,cash_back");
        if( $tariff_order_mode=='delivery_by_courier_first' ){
            $this->orderBy("delivery_allow DESC");
        } else {
            $this->orderBy("delivery_allow ASC");
        }
        $this->orderBy("card_allow DESC");
        $tariffs=$this->get()->getResult();
        return $this->tarffSweetModificator($tariffs);
    }

    private function tarffSweetModificator($tariffs){
        $sweet_ratio=$this->tarffSweetRatioGet();
        if( $sweet_ratio==1 ){
            return $tariffs;
        }
        foreach($tariffs as $tariff){
            if( empty($tariff->delivery_allow) ){
                continue;
            }
            $tariff->delivery_cost=round($tariff->delivery_cost*$sweet_ratio);
        }
        return $tariffs;
    }

    private function tarffSweetRatioGet(){
        /**
         * TMP patch
         */
        $PrefModel=model('PrefModel');
        $delivery_sweet_start_hour=$PrefModel->itemGet('delivery_sweet_start_hour','pref_value');
        $delivery_sweet_finish_hour=$PrefModel->itemGet('delivery_sweet_finish_hour','pref_value');
        $now_hour=date("H");
        if( $now_hour > $delivery_sweet_start_hour && $now_hour >= $delivery_sweet_finish_hour ){
            return 1;
        }
        $delivery_sweet_ratio=$PrefModel->itemGet('delivery_sweet_ratio','pref_value');
        return (100-$delivery_sweet_ratio)/100;
    }

    public function tariffRuleDeliveryCostGet( $store_id ){//this function is messy OMG
        $this->limit(1);
        $this->select('IF(delivery_cost>0,delivery_cost,store_delivery_cost) order_sum_delivery');
        $this->where('delivery_allow',1);
        $delivery_option=$this->tariffRuleListGet($store_id,'delivery_by_courier_first');
        if( isset($delivery_option[0]) ){
            $sweet_ratio=$this->tarffSweetRatioGet();
            return $delivery_option[0]->order_sum_delivery*$sweet_ratio;
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

            //$this->permitWhere('w');
            $owner_id=(int)$filter['owner_id'];
            $owner_ally_id=(int)$filter['owner_ally_ids'];
            $this->where("(store_list.owner_id='$owner_id' OR FIND_IN_SET($owner_ally_id,store_list.owner_ally_ids))");
        } else {
            $this->permitWhere('r');
        }
        $this->filterMake( $filter );
        $weekday=date('N')-1;
        $dayhour=date('H');
        $this->select("store_time_opens_{$weekday} store_time_opens,store_time_closes_{$weekday} store_time_closes");
        $this->select("is_working AND IS_STORE_OPEN(store_time_opens_{$weekday},store_time_closes_{$weekday},$dayhour) is_opened");

        $this->orderBy("is_opened",'DESC');
        $this->join('image_list',"image_holder='store' AND image_holder_id=store_id AND is_main=1",'left');
        $this->select("{$this->table}.*,image_hash");
        $store_list= $this->get()->getResult();
        //ql($this);
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
        $this->select("GROUP_CONCAT(CONCAT(owner_id,',',owner_ally_ids)) owners_all");
        $owners_all_list=$this->get()->getRow('owners_all');

        $this->where('deleted_at<',$olderStamp);
        $ok=$this->delete(null,true);

        if($owners_all_list){
            $owners_all=array_unique(explode(',',$owners_all_list));
            $this->ownerGroupUpdate($owners_all);
        }
        return $ok;
    }

    public function listNearGet($filter){
        $location_id=$filter['location_id']??null;
        $LocationModel=model('LocationModel');
        if( !$location_id && $filter['location_latitude'] && $filter['location_longitude'] ){
            $location_id=$LocationModel->itemTemporaryCreate($filter['location_latitude'],$filter['location_longitude']);
        }
        if( !$location_id ){
            return 'location_required'; 
        }
        if( $filter['limit']??0 ){
            $LocationModel->limit($filter['limit']);
        }
        if( $filter['offset']??0 ){
            $LocationModel->offset($filter['offset']);
        }
        if( $filter['whitelistedStores']??0 ){
            $LocationModel->whereIn('store_id',$filter['whitelistedStores']);
        }


        $weekday=date('N')-1;
        $nextweekday=date('N')%7;
        $dayhour=date('H');

        $delivery_radius=getenv('delivery.radius');

        $permission_filter=$this->permitWhereGet('r','item');
        
        $LocationModel->select("store_data->>'$.cache_groups' cache_groups");
        $LocationModel->select("store_id,store_name,store_time_preparation,image_hash,is_working");
        $LocationModel->select("store_time_opens_{$weekday} store_time_opens,store_time_opens_{$nextweekday} store_next_time_opens,store_time_closes_{$weekday} store_time_closes,store_time_closes_{$nextweekday} store_next_time_closes");
        /**
         * Opossum mode
         */
        $CourierShiftModel=model('CourierShiftModel');
        $deliveryIsReady=$CourierShiftModel->where('shift_status','open')->select('shift_id')->get()->getRow('shift_id');
        if( !$deliveryIsReady ){
            // $TariffMemberModel=model("TariffMemberModel");
            // $TariffMemberModel->join('tariff_list','tariff_id');
            // $TariffMemberModel->where('delivery_allow',0);
            // $TariffMemberModel->select('store_id');
            // $stores=$TariffMemberModel->get()->getResult();
            // foreach( $stores as $store ){
            //     $opossum_stores[]=$store->store_id;
            // }
            $LocationModel->select("is_working AND IFNULL(store_delivery_allow,0)=1 AND IS_STORE_OPEN(store_time_opens_{$weekday},store_time_closes_{$weekday},$dayhour) is_opened");
            $LocationModel->orderBy("store_delivery_allow=1",'DESC',0);
        } else {
            $LocationModel->select("is_working AND IS_STORE_OPEN(store_time_opens_{$weekday},store_time_closes_{$weekday},$dayhour) is_opened");
        }
        /**
         * @deprecated
         */
        $LocationModel->select("GROUP_CONCAT(group_type) member_of_groups");
        $LocationModel->join('store_list','store_id=location_holder_id');
        $LocationModel->join('store_group_member_list sgml','store_id=member_id','left');
        $LocationModel->join('store_group_list sgl','sgl.group_id=sgml.group_id','left');
        
        $LocationModel->join('image_list',"image_holder='store' AND image_holder_id=store_id AND image_list.is_main=1",'left');
        if( $permission_filter ){
            $LocationModel->where($permission_filter);
        }
        $LocationModel->orderBy("is_working",'DESC');
        $LocationModel->orderBy("is_opened",'DESC');
        $LocationModel->groupBy("store_id");
        //$LocationModel->where("(is_primary=0 OR is_primary IS NULL)");
        $LocationModel->where("(store_list.deleted_at IS NULL AND store_list.is_disabled=0)");
        $LocationModel->where("(location_list.deleted_at IS NULL AND location_list.is_disabled=0)");
        $store_list=$LocationModel->distanceListGet( $location_id, $delivery_radius, 'store' );
        if( !is_array($store_list) ){
            return 'not_found';
        }
        $PerkModel=model('PerkModel');
        foreach($store_list as $store){
            $store->perks=$PerkModel->listGet('store',$store->store_id);
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

        $LocationModel->select("store_time_opens_{$weekday} store_time_opens,store_time_closes_{$weekday} store_time_closes");
        $LocationModel->select("store_id,store_name,store_time_preparation,is_primary,image_hash");
        $LocationModel->select("is_working AND IS_STORE_OPEN(store_time_opens_{$weekday},store_time_closes_{$weekday},$dayhour) is_opened");
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
        $store=$this->select('owner_id,owner_ally_ids')->get()->getRow();

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
                    LEFT JOIN
                transaction_tag_list ttl ON tag_name='store' AND tag_id=sl.store_id
                    LEFT JOIN
                transaction_list tl ON ttl.trans_id=tl.trans_id AND trans_role IN ('site->supplier','supplier->site','profit->supplier')
            SET
            sl.owner_ally_ids='$owner_list',
            pl.owner_ally_ids='$owner_list',
            ils.owner_ally_ids='$owner_list',
            ilp.owner_ally_ids='$owner_list',
            tl.owner_ally_ids='$owner_list',

            pl.owner_id='$store_owners->owner_id',
            ils.owner_id='$store_owners->owner_id',
            ilp.owner_id='$store_owners->owner_id',
            tl.owner_id='$store_owners->owner_id'
        
            WHERE
                sl.store_id='$store_id'";
        $this->query($sql);

        $owners_all=array_unique(array_merge($owners,[$owner_ally_id]));//including deleted owner
        $this->ownerGroupUpdate($owners_all);
        return $this->db->affectedRows()?'ok':'idle';
    }

    /**
     * Updates user. joins or leaves supplier group
     */
    private function ownerGroupUpdate( array $check_group_owners ){
        $UserGroupMemberModel=model('UserGroupMemberModel');
        foreach($check_group_owners as $owner_id){
            if(!(int) $owner_id){
                continue;
            }
            $is_supplier=$this->itemOwnedGet( $owner_id );
            if($is_supplier){
                $UserGroupMemberModel->joinGroupByType($owner_id,'supplier');
                continue;
            }
            $UserGroupMemberModel->leaveGroupByType($owner_id,'supplier');
        }
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



        /**
     * Nightly calculations
     */
    public function nightlyCalculate( ?int $store_id=null ){
        $this->nightlyCalculatePerks($store_id);
    }

    private function nightlyCalculatePerks( ?int $store_id=null ){
        $PerkModel=model('PerkModel');

        if($store_id){
            $PerkModel->where('perk_holder_id',$store_id);
        }
        $PerkModel->where('perk_holder','store');
        $PerkModel->delete();

        /**
         * Product perks
         */
        if($store_id){
            $PerkModel->where('perk_holder_id',$store_id);
        }
        $PerkModel->where('perk_holder','product');
        $PerkModel->whereIn('perk_type',['product_new','product_top','product_promo']);
        $PerkModel->join('product_list','product_id=perk_holder_id');
        $PerkModel->where('product_list.is_disabled','0');
        $PerkModel->where('product_list.is_hidden','0');
        $PerkModel->where('(NOT product_list.is_counted OR product_list.is_counted AND product_list.product_quantity)',null,false);
        $PerkModel->select('store_id,perk_type,expired_at');
        $PerkModel->groupBy('store_id,perk_type');
        $prods=$PerkModel->get()->getResult();

        /**
         * Halal perk
         */
        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        if($store_id){
            $StoreGroupMemberModel->where('member_id',$store_id);
        }
        $StoreGroupMemberModel->join('store_group_list','group_id');
        $StoreGroupMemberModel->where('group_type','halal');
        $halals=$StoreGroupMemberModel->select("member_id store_id,'store_halal' perk_type")->get()->getResult();

        /**
         * Rating
         */
        $minimum_reaction_count=10;
        $ReactionModel=model('ReactionModel');
        if($store_id){
            $ReactionModel->where('tag_id',$store_id);
        }
        $ReactionModel->join('reaction_tag_list','reaction_id=member_id');
        $ReactionModel->where('tag_name','store');
        $ReactionModel->select("tag_id store_id,'store_rating' perk_type,SUM(reaction_is_like)/SUM(reaction_is_like+reaction_is_dislike) perk_value,SUM(reaction_is_like+reaction_is_dislike) total_reactions");
        $ReactionModel->groupBy('tag_id');
        $ReactionModel->having('perk_value>0.7',null,false);//>3.5
        $ReactionModel->having("total_reactions>$minimum_reaction_count",null,false);
        $reacts=$ReactionModel->get()->getResult();

        /**
         * Cashback
         */
        $TariffModel=model('TariffModel');
        if($store_id){
            $TariffModel->where('store_id',$store_id);
        }
        $TariffModel->join('tariff_member_list','tariff_id');

        $TariffModel->select("store_id,'cashback' perk_type,cash_back perk_value");
        $TariffModel->where('cash_back>',0);
        $tariff=$TariffModel->get()->getResult();

        /**
         * Has challenge
         */
        $PostModel=model('PostModel');
        if($store_id){
            $PostModel->where('post_holder_id',$store_id);
        }
        $PostModel->where('post_holder','store');
        $PostModel->where('reaction_tags','challenge');
        $PostModel->where("NOW()>started_at");
        $PostModel->where("NOW()<finished_at");
        $PostModel->where('is_published', '1');
        $PostModel->groupBy('post_holder_id');
        $PostModel->select("post_holder_id store_id,'store_challenge' perk_type, 1 perk_value");
        $challenges=$PostModel->get()->getResult();

        /**
         * Composite
         */
        $def_expired_at=date('Y-m-d H:i:s',time()+24*60*60);
        $perks=array_merge($prods,$halals,$reacts,$tariff,$challenges);
        $PerkModel->transStart();
        foreach( $perks as $row ){
            $perk=[
                'perk_holder'=>'store',
                'perk_holder_id'=>$row->store_id,
                'perk_type'=>$row->perk_type,
                'perk_value'=>$row->perk_value??null,
                'expired_at'=>$row->expired_at??$def_expired_at,
            ];
            $PerkModel->itemCreate($perk);
        }
        $PerkModel->transComplete();
    }
}
