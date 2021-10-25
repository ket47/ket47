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
        'deleted_at',
        'owner_id',
        'owner_ally_id'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_name'        => 'min_length[3]',
        'store_description' => 'min_length[10]',
        'store_company_name' => 'min_length[3]',
        'store_tax_num'         => 'exact_length[10,12]|integer'
    ];
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $store_id, $mode='all' ){
        if( !$this->permit($store_id,'r') ){
            return 'forbidden';
        }
        $this->where('store_id',$store_id);
        $store = $this->get()->getRow();
        if( !$store ){
            return 'notfound';
        }
        if( $mode=='basic' ){
            return $store;
        }

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
        q($StoreGroupMemberModel);
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
        $this->permitWhere('r');
        $this->orderBy("{$this->table}.updated_at",'DESC');
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
    
    public function listPurge( $olderThan=7 ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan days");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
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
        if( !$this->permit($store_id,'w') ){
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
