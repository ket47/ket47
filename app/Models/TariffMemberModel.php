<?php
namespace App\Models;
use CodeIgniter\Model;

class TariffMemberModel extends Model{


    protected $table      = 'tariff_member_list';
    protected $allowedFields = [
        'tariff_id',
        'store_id',
        'start_at',
        'finish_at'
        ];

    protected $useSoftDeletes = false;
    
    public function itemGet( int $tariff_id, int $store_id, string $mode='only_valid' ){
        $this->where('tariff_id',$tariff_id);
        $this->where('store_id',$store_id);
        $this->join('tariff_list','tariff_id');
        if( str_contains($mode,'only_valid') ){
            $this->where('start_at<=NOW()');
            $this->where('finish_at>=NOW()');
            $this->where('is_disabled',0);
        }
        return $this->get()->getRow();
    }

    // private $itemCurrentCache=[];
    // public function itemCurrentGet( int $store_id ){
    //     if( isset($this->itemCurrentCache[$store_id]) ){
    //         return $this->itemCurrentCache[$store_id];
    //     }
    //     $this->where('start_at<=NOW()');
    //     $this->where('finish_at>=NOW()');
    //     $this->where('store_id',$store_id);
    //     $this->join('tariff_list','tariff_id');
    //     $this->orderBy('start_at');
    //     $this->itemCurrentCache[$store_id]=$this->get(1)->getRow();//only first tariff
    //     return $this->itemCurrentCache[$store_id];
    // }

    public function itemCreate( int $tariff_id, int $store_id, string $start_at=null, string $finish_at=null ){
        if( !sudo() ){
            return 'forbidden';
        }
        if( !$start_at ){
            $start_at=date('Y-m-d H:i:s');
        }
        if( !$finish_at ){
            $finish_at=date('Y-m-d H:i:s',time()+157680000);//5 years
        }
        $tariff=[
            'tariff_id'=>$tariff_id,
            'store_id'=>$store_id,
            'start_at'=>$start_at,
            'finish_at'=>$finish_at
        ];
        $this->insert($tariff);
        return $this->affectedRows()?'ok':'idle';
    }

    public function itemDelete( int $tariff_id, int $store_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->where('tariff_id',$tariff_id);
        $this->where('store_id',$store_id);
        $this->delete();
        return $this->affectedRows()?'ok':'idle';
    }
    
    public function listGet( int $tariff_id=null, int $store_id=null ){
        if( !sudo() || !$tariff_id&&!$store_id ){
            return;
        }
        if( $tariff_id ){
            $this->where('tariff_id',$tariff_id);
        }
        if( $store_id ){
            $this->where('store_id',$store_id);
        }
        $this->join('tariff_list','tariff_id');
        return $this->get()->getResult();
    }
}