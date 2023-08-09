<?php
namespace App\Models;
use CodeIgniter\Model;

class SecureModel extends Model{
    use PermissionTrait;

    protected $beforeFind   =['permitRead'];
    protected $beforeInsert =['permitCreate'];
    protected $beforeUpdate =['permitWrite'];
    protected $beforeDelete =['permitWrite'];

    protected function permitCreate(array $data){
        if( $this->permit(null,'w')){
            return $data;
        }
        throw new \Exception('forbidden');
        return false;
    }

    protected function permitWrite(array $data){
        $this->permitWhere('w');
        return $data;
    }

    protected function permitRead(){
        $this->permitWhere('r');
    }

}