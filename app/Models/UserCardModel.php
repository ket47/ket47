<?php
namespace App\Models;
use CodeIgniter\Model;

class UserCardModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'user_card_list';
    protected $primaryKey = 'card_id';
    protected $allowedFields = [
        'card_type',
        'card_mask',
        'card_acquirer',
        'card_remote_id',
        'is_main',
        'is_disabled'
        ];

    protected $useSoftDeletes = false;
    
    public function itemGet( $card_id ){
        $this->permitWhere('r');
        $this->where('card_id',$card_id);
        $card=$this->get()->getRow();
        if( !$card ){
            return 'notfound';
        }
        return $card;
    }

    public function itemDisabledGet(){
        $user_id=session()->get('user_id');
        $this->permitWhere('w');
        $this->where('owner_id',$user_id);
        $this->where('is_disabled',1);
        $card=$this->get()->getRow();
        if( !$card ){
            return 'notfound';
        }
        return $card;
    }
    
    public function itemCreate( object $card ){
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $user_id=session()->get('user_id');
        $card->owner_id=$user_id;
        $this->allowedFields[]='owner_id';
        return $this->insert($card,true);
    }

    public function itemUpdate(object $card){
        if(!$card->card_id){
            return 'notfound';
        }
        // $this->itemMainReset();
        // $card->is_disabled=0;
        // $card->is_main=1;
        $this->permitWhere('w');
        $this->update($card->card_id,$card);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemMainGet( int $user_id ){
        $this->permitWhere('r');
        $this->select("card_id,card_remote_id,card_type,card_mask,card_acquirer");
        $this->limit(1);
        $this->orderBy('is_main','DESC');
        $this->where('is_disabled',0);
        $this->where('owner_id',$user_id);
        $card=$this->get()->getRow();
        if( !$card ){
            return 'notfound';
        }
        return $card;
    }

    public function itemMainSet($card_id){
        $this->itemMainReset();
        $this->permitWhere('w');
        $this->update($card_id,['is_main'=>1]);
        return $this->db->affectedRows()?'ok':'idle';
    }

    private function itemMainReset(){
        $this->permitWhere('w');
        $this->where('is_main',1);
        $this->set(['is_main'=>0]);
        $this->update();
    }
    
    public function itemDelete($card_id){
        $this->permitWhere('w');
        $this->delete($card_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listGet( int $user_id ){
        $this->permitWhere('r');
        $this->select("card_id,card_mask,LOWER(card_type) card_type");
        $this->where('is_disabled',0);
        $this->orderBy('is_main','DESC');
        if( $user_id ){
            $this->where('owner_id',$user_id);
        }
        $card_list=$this->get()->getResult();
        return $card_list;
    }

    public function listDelete( int $user_id ){
        $this->permitWhere('w');
        $this->where('owner_id',$user_id);
        $this->delete();
        return $this->db->affectedRows()?'ok':'idle';
    }

}