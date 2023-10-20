<?php
namespace App\Models;

class TariffModel extends SecureModel{
    
    protected $table      = 'tariff_list';
    protected $primaryKey = 'tariff_id';
    protected $allowedFields = [
        'tariff_name',
        'script_name',
        'order_allow',
        'order_fee',
        'order_cost',

        'card_allow',
        'card_fee',
        'cash_allow',
        'cash_fee',

        'delivery_allow',
        'delivery_fee',
        'delivery_cost',
        'order_fee',
        'card_fee',
        'delivery_cost',

        'is_disabled'
        ];

    protected $useSoftDeletes = false;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
    
    public function itemGet( int $tariff_id ){
        if( !isset($tariff_id) ){
            return 'noid';
        }
        return $this->find($tariff_id);
    }
    
    public function itemCreate( object $tariff ){
        if( !sudo() ){
            return 'forbidden';
        }
        $tariff->is_public=0;
        $tariff->is_disabled=1;
        return $this->insert($tariff,true);
    }
    
    public function itemUpdate( object $tariff ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( !isset($tariff->tariff_id) ){
            return 'noid';
        }
        $this->update($tariff->tariff_id,$tariff);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( int $tariff_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->delete($tariff_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listGet(){
        if( !sudo() ){
            $this->where('is_disabled',0);
            $this->where('is_public',1);
        }
        return $this->get()->getResult();
    }    
}