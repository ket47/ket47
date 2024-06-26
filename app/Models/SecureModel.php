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

    private $permitWriteSkip=false;
    protected function permitWrite(array $data){
        if( $this->permitWriteSkip==true ){
            $this->permitWriteSkip=false;
            return $data;
        }
        $this->permitWhere('w');
        return $data;
    }

    private $permitReadSkip=false;
    protected function permitRead(){
        if($this->permitReadSkip==true){
            $this->permitReadSkip=false;
            return;
        }
        $this->permitWhere('r');
    }

    /**
     * Skips permission check once
     */
    public function allowWrite(){
        $this->permitWriteSkip=true;
    }
    /**
     * Skips permission check once
     */
    public function allowRead(){
        $this->permitReadSkip=true;
    }
}