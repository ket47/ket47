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
    
    public function get( $pref_name ){
        if( !sudo() ){
            return null;
        }
        return $this->getWhere(['pref_name'=>$pref_name])->getRow();
    }
    
    public function setValue( $pref_name, $pref_value ){
        if( !sudo() ){
            return null;
        }
        return $this->save($pref_name, ['pref_value'=>$pref_value]);
    }
    
    public function setJson( $pref_name, $pref_json ){
        if( !sudo() ){
            return null;
        }
        return $this->save($pref_name, ['pref_json'=>$pref_json]);
    }
}