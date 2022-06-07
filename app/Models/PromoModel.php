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
        'promo_order_id',
        'promo_activator_id',
        'is_disabled',
        'is_used',
        'expired_at'
        ];

    protected $useSoftDeletes = false;
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet(){
        return false;
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