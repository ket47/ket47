<?php
namespace App\Models;
use CodeIgniter\Model;

class StoreModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'store_list';
    protected $primaryKey = 'store_id';
    protected $allowedFields = [
        'store_name',
        'store_address',
        'store_coordinates',
        'store_description',
        'is_disabled',
        'deleted_at',
        'owner_id',
        'owner_ally_id'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->permitWhere('r');
        $store_list = $this->get()->getResult();
        $GroupMemberModel=model('GroupMemberModel');
        foreach($store_list as $store){
            if($store){
                $store->member_of_groups=$GroupMemberModel->memberGroupsGet($store->store_id);
            }
        }
        return $store_list;
    }
    
    
    
    
    
    
    
    
    public function itemGet( $store_id ){
        $store_list=$this->listGet( ['store_id'=>$store_id] );
        if( !$store_list ){
            return [];
        }
        return $store_list[0];
    }
    
    public function itemCreate( $name ){
        if( !$this->permit(null,'w') ){
            return 'item_create_error_forbidden';
        }
        $user_id=session()->get('user_id');
        $store_id=$this->where('owner_id',$user_id)->get()->getRow('store_id');
        if( $store_id ){
            return 'item_create_error_dublicate';
        }
        $ok=$this->insert(['store_name'=>$name]);
        if( $ok ){
            return 'ok';
        }
        return 'item_create_error';
    }
    
    public function itemUpdate( $data ){
        $this->permitWhere('w');
        return $this->update($data);
    }
    
    public function itemDelete( $store_id ){
        $this->permitWhere('w');
        return $this->delete(['store_id'=>$store_id]);
    }
    
    
    
    
    
    
    
    
    
    
}