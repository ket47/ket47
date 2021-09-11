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
        'owner_id',
        'owner_ally_id'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    
    
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        $this->permitWhere('r');
        return $this->get()->getResult();
    }
    
    
    
    
    
    
    
    
    
    public function itemGet( $store_id ){
        $store_list=$this->listGet( ['store_id'] );
        if( !$store_list ){
            return [];
        }
        return $store_list[0];
    }
    
    
}