<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table      = 'pref_list';
    protected $primaryKey = 'pref_name';
    protected $allowedFields = [
        'pref_name',
        'pref_value',
        'pref_json'
        ];
    
    public function get( $pref_name ){
        permit('prefGet');
        return $this->getWhere(['pref_name'=>$pref_name])->getRow();
    }
    
    public function setValue( $pref_name, $pref_value ){
        permit('prefSet');
        return $this->save($pref_name, ['pref_value'=>$pref_value]);
    }
    
    public function setJson( $pref_name, $pref_json ){
        permit('prefSet');
        return $this->save($pref_name, ['pref_json'=>$pref_json]);
    }
}