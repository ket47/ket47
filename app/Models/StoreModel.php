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
        'owner_id',
        'owner_ally_id'
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
        $this->where('store_id',$store_id);
        $store = $this->get()->getRow();
        if( !$store ){
            return 'notfound';
        }
        if( $mode=='basic' ){
            $this->itemCache[$mode.$store_id]=$store;
            return $store;
        }
        
        $LocationModel=model('LocationModel');
        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $ImageModel=model('ImageModel');
        $store->is_writable=$this->permit($store_id,'w');
        $store->member_of_groups=$StoreGroupMemberModel->memberOfGroupsGet($store->store_id);
        $filter=[
            'image_holder'=>'store',
            'image_holder_id'=>$store->store_id,
            'is_disabled'=>1,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>30
        ];
        $store->images=$ImageModel->listGet($filter);
        
        $filter_loc=[
            'location_holder'=>'store',
            'location_holder_id'=>$store->store_id,
            'is_disabled'=>1,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>30
        ];
        // if($distanceToUserInclude){
        //     $LocationModel->distanceToUserInclude();
        // }
        $store->locations=$LocationModel->listGet($filter_loc);
        $this->itemCache[$mode.$store_id]=$store;
        return $store;
    }
    
    public function itemCreate( $name ){
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $user_id=session()->get('user_id');
        $has_store_id=$this->where('owner_id',$user_id)->get()->getRow('store_id');
        if( $has_store_id ){
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
        return 'error';
    }
    
    public function itemUpdate( $store ){
        if( empty($store->store_id) ){
            return 'noid';
        }
        if( !$this->permit($store->store_id,'w') ){
            return 'forbidden';
        }
        if( isset($store->is_primary) ){
            if( !sudo() ){
                return 'forbidden';
            }
            $this->allowedFields[]='is_primary';
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
        if( !$this->permit($store_id,'w') ){
            return 'forbidden';
        }
        $ProductModel=model('ProductModel');
        $ProductModel->listDeleteChildren( $store_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('store', $store_id);
        
        $this->delete($store_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUnDelete( $store_id ){
        if( !$this->permit($store_id,'w') ){
            return 'forbidden';
        }
        $ProductModel=model('ProductModel');
        $ProductModel->listUnDeleteChildren( $store_id );
        
        $ImageModel=model('ImageModel');
        $ImageModel->listUnDelete('store', $store_id);
        
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
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        if( $filter['group_id']??0 ){
            $this->join('store_group_member_list','member_id=store_id');
            $this->where('group_id',$filter['group_id']);
        }
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
    
    public function listPurge( $olderThan=APP_TRASHED_DAYS ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan days");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }

    public function listNearGet($filter){
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
        $LocationModel->select("store_id,store_name,store_time_preparation,image_hash,store_description");
        $LocationModel->select("store_time_opens_{$weekday} store_time_opens,store_time_closes_{$weekday} store_time_closes");
        $LocationModel->select("IF(is_working AND store_time_opens_{$weekday}<=$dayhour AND store_time_closes_{$weekday}>$dayhour,1,0) is_opened");
        $LocationModel->join('store_list','store_id=location_holder_id');
        $LocationModel->join('image_list',"image_holder='store' AND image_holder_id=store_id AND image_list.is_main=1",'left');
        if( $permission_filter ){
            $LocationModel->where($permission_filter);
        }
        $LocationModel->orderBy("is_opened",'DESC');
        $LocationModel->where("(is_primary=0 OR is_primary IS NULL)");
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
        if(!$store_id ){
            return 'nostore';
        }
        $this->where('store_id',$store_id);
        if( !sudo() ){
            $this->where('store_list.owner_id',$user_id);
        }
        $owner_ally_ids=$this->get()->getRow('owner_ally_ids');
        if($owner_ally_ids){
            $UserModel=model('UserModel');
            $UserModel->select('user_id,user_phone');
            $UserModel->where("user_id IN($owner_ally_ids)");
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

        $owner_ally_ids=explode(',',"0,$store_owners->owner_ally_ids,$store_owners->owner_id");

        if( $action=='add' ){
            $owners=array_merge($owner_ally_ids,[$owner_ally_id]);
        } else
        if( $action=='delete' ){
            $owners=array_diff($owner_ally_ids,[$owner_ally_id]);
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
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemCreate($data);
        }
        return 0;
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
