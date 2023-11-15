<?php
namespace App\Models;
use CodeIgniter\Model;

class PrefModel extends Model{
    protected $table      = 'pref_list';
    protected $primaryKey = 'pref_name';
    protected $allowedFields = [
        'pref_name',
        'pref_value',
        'pref_json'
        ];
    
    public function itemGet( $pref_name, $pref_property=null, $pref_default=null ){
        $pref=$this->where('pref_name',$pref_name)->get()->getRow();
        if( $pref_property ){
            return $pref->$pref_property??$pref_default;
        }
        return $pref;
    }
    
    public function itemCreate( $pref_name ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( $this->getWhere(['pref_name'=>$pref_name])->getRow() ){
            return 'duplicate';
        }
        $this->insert(['pref_name'=>$pref_name]);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemUpdate( $pref ){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->save($pref);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemUpdateValue( $pref_name, $pref_value ){
        return $this->itemUpdate((object)['pref_name'=>$pref_name,'pref_value'=>$pref_value]);
    }
    
    public function itemUpdateJson( $pref_name, $pref_json ){
        return $this->itemSave($pref_name, (object)['pref_name'=>$pref_name,'pref_json'=>$pref_json]);
    }
    
    public function itemDelete( $pref_name ){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->where('pref_name',$pref_name)->delete();
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function listGet(){
        if( !sudo() ){
            return null;
        }
        return $this->get()->getResult();
    }
}