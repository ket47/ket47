<?php
namespace App\Models;
use CodeIgniter\Model;

class MailingMessageModel extends SecureModel{
    
    use FilterTrait;
    
    protected $table      = 'mailing_message_list';
    protected $primaryKey = 'message_id';
    protected $allowedFields = [
        'reciever_id',
        'mailing_id',
        'willsend_at',
        'is_sent',
        'is_failed',
        ];

    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $returnType       = 'object';
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate( $message ){
        if( !sudo() ){
            return 'forbidden';
        }
        try{
            return $this->insert($message,true);
        } catch(\Exception $e){
            return $e->getMessage();
        }
    }
    
    public function itemUpdate(object $message){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->update($message->message_id,$message);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete($message_id){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->delete(['message_id'=>$message_id]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listGet( $mailing_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->where('mailing_id',$mailing_id);
        return $this->findAll();
    }
    
    public function listCreate( $mailing_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $UserModel=model('UserModel');
        $UserModel->permitWhere('r');
        $UserModel->select('reciever_id');

        $MailingModel=model('MailingModel');
        $mailing=$MailingModel->itemGet($mailing_id);

        $filter=json_decode($mailing->user_filter);
        if( $filter->user_phones??0 ){
            $UserModel->whereIn($filter->user_phones);
        }
        $user_list=$UserModel->listGet($filter);
        foreach($user_list as $user){
            $message=[
                'reciever_id'=>$user->reciever_id,
                'mailing_id'=>$mailing_id
            ];
            $this->insert($message);
        }
        return true;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}