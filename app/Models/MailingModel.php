<?php
namespace App\Models;
use CodeIgniter\Model;

class MailingModel extends SecureModel{
    
    use FilterTrait;
    
    protected $table      = 'mailing_list';
    protected $primaryKey = 'mailing_id';
    protected $allowedFields = [
        'subject_template',
        'text_template',
        'user_filter',
        'transport',
        'sound',
        'link',
        'start_at',
        'is_started'
        ];

    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $returnType       = 'object';


    protected function initialize(){
        $this->query("SET character_set_results = utf8mb4, character_set_client = utf8mb4, character_set_connection = utf8mb4, character_set_database = utf8mb4, character_set_server = utf8mb4");
    }
    
    public function itemGet($mailing_id){
        if( !sudo() ){
            return 'forbidden';
        }
        $mailing=$this->find($mailing_id);
        if( !$mailing ){
            return null;
        }
        $ImageModel=model('ImageModel');
        $mailing->images=$ImageModel->listGet(['image_holder'=>'mailing','image_holder_id'=>$mailing_id]);

        if($mailing->images[0]->image_hash??null){
            $mailing->image=getenv('app.backendUrl')."image/get.php/{$mailing->images[0]->image_hash}.1000.1000.webp";
        }
        $mailing->user_filter=json_decode($mailing->user_filter);
        return $mailing;
    }
    
    public function itemCreate($mailing){
        if( !sudo() ){
            return 'forbidden';
        }
        try{
            return $this->ignore()->insert($mailing,true);
        } catch(\Exception $e){
            return $e->getMessage();
        }
    }
    
    public function itemUpdate(object $mailing){
        if( !sudo() ){
            return 'forbidden';
        }
        $mailing->user_filter=json_encode($mailing->user_filter);
        $this->update($mailing->mailing_id,$mailing);
        return $this->db->affectedRows()>0?'ok':'idle';
    }
    
    public function itemDelete($mailing_id){
        if( !sudo() ){
            return 'forbidden';
        }
        $MailingMessageModel=model('MailingMessageModel');
        $MailingMessageModel->listDelete($mailing_id);
        $this->delete(['mailing_id'=>$mailing_id]);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemStart( $mailing_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $mailing=$this->itemGet($mailing_id);
        $reciever_list=$this->itemRecieversGet( $mailing->user_filter );
        if( !$reciever_list ){
            return 'no_recievers';
        }
        $MailingMessageModel=model('MailingMessageModel');
        foreach($reciever_list as $reciever){
            $message=[
                'mailing_id'=>$mailing_id,
                'reciever_id'=>$reciever['user_id'],
                'willsend_at'=>$mailing->start_at,
            ];
            $MailingMessageModel->itemCreate($message);
        }
        $this->update($mailing_id,['is_started'=>1]);
        return 'ok';
    }

    private function itemRecieversGet( $user_filter ){
        $UserModel=model('UserModel');
        if( $user_filter->phones??null && $user_filter->phones!='*' ){
            $UserModel->orWhereIn('user_phone',explode(',',$user_filter->phones));
        }
        $UserModel->select('user_id');
        $UserModel->permitWhere('r');
        return $UserModel->findAll();
    }
    
    public function listGet( $filter ){
        $this->filterMake($filter);
        return $this->findAll()??[];
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
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $data ){
        if( !sudo() ){
            return 'forbidden';
        }
        $data['is_disabled']=0;
        $data['owner_id']=session()->get('user_id');
        if( $this->permit($data['image_holder_id'], 'w') ){
            $limit=1;
            $ImageModel=model('ImageModel');
            $ok=$ImageModel->itemCreate($data,$limit);
            return $ok;
        }
        return 0;
    }
    
    public function imageDelete( $image_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $mailing_id=$image->image_holder_id;
        if( !$this->permit($mailing_id,'w') ){
            return 'forbidden';
        }
        $ImageModel->itemDelete( $image_id );
        $ok=$ImageModel->itemPurge( $image_id );
        if( $ok ){
            return 'ok';
        }
        return 'idle';
    }
}