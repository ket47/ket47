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

    protected $useSoftDeletes   = false;
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
            return $this->insert($message);
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
    
    // public function listCreate( $mailing_id ){
    //     if( !sudo() ){
    //         return 'forbidden';
    //     }
    //     $reciever_list=$this->listRecieverGet($mailing_id);
    //     foreach($reciever_list as $reciever){
    //         $message=[
    //             'mailing_id'=>$mailing_id,
    //             'reciever_id'=>$reciever['user_id'],
    //         ];
    //         $this->itemCreate($message);
    //     }
    //     return true;
    // }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete( $mailing_id, $mode='only_un_sent' ){
        $this->where('mailing_id',$mailing_id);
        if( $mode=='only_un_sent' ){
            $this->where('is_sent',0);
            $this->where('is_failed',0);
        }
        $this->delete(null,true);
        return $this->db->affectedRows()>0?'ok':'idle';
    }

    public function listRecieverFill( int $mailing_id ){
        $MailingModel=model('MailingModel');
        $mailing=$MailingModel->itemGet($mailing_id);
        $UserModel=model('UserModel');

        $this->where('mailing_id', $mailing_id)->delete();

        if( $mailing->user_filter->phones??null ){
            $UserModel->whereIn('user_phone',explode(',',$mailing->user_filter->phones));
        }
        if( $mailing->user_filter->location??null && is_numeric($mailing->user_filter->location->location_longitude) && is_numeric($mailing->user_filter->location->location_latitude) ){
            $radius=getenv('delivery.radius');
            if($mailing->user_filter->radius??null){
                $radius=(int) $mailing->user_filter->radius;
            }
            $UserModel->query("SET @center_point:=POINT({$mailing->user_filter->location->location_longitude}, {$mailing->user_filter->location->location_latitude})");
            $UserModel->join('location_list',"is_main = 1 AND location_holder = 'user' AND location_holder_id = user_id");
            $UserModel->where("ST_DISTANCE_SPHERE(@center_point, location_point)<{$radius}");
        }
        $now=date('Y-m-d H:i:s');

        $UserModel->select("user_id,{$mailing_id} mailing_id,'$now' created_at");
        $UserModel->permitWhere('r');
        $recievers_select_sql=$UserModel->builder->getCompiledSelect();
        $recievers_insert_sql="INSERT IGNORE INTO mailing_message_list (reciever_id,mailing_id,created_at) $recievers_select_sql";
        $this->query($recievers_insert_sql);
        return $this->db->affectedRows()??0;
    }

    public function listCountGet( int $mailing_id ){
        $this->where('mailing_id',$mailing_id);

        $this->select("SUM(is_sent) `sent`");
        $this->select("SUM(is_failed) `failed`");
        $this->select("COUNT(*) `all`");

        $result= $this->find();
        if(!$result){
            return null;
        }
        return $result[0];
    }
    
    public function listSend( object $mailing, $reciever_ids ){
        if(!sudo()){
            return false;
        }
        $UserModel=model('UserModel');
        $Messenger=new \App\Libraries\Messenger();
        foreach($reciever_ids as $reciever_id){
            $context=$UserModel->find($reciever_id);
            $message=(object)[
                'message_transport'=>$mailing->transport,
                'message_reciever_id'=>$reciever_id,
                'message_data'=>(object)[
                    'link'=>$mailing->link,
                    'image'=>$mailing->image??'',
                    'sound'=>$mailing->sound??''
                ],
                'message_text'=>$this->render($mailing->text_template,$context),
                'message_subject'=>$mailing->subject_template,
            ];
            $is_sent=$Messenger->itemSend( $message );
            $this->where('mailing_id',$mailing->mailing_id);
            $this->where('reciever_id',$reciever_id);
            $this->update(null,['is_sent'=>$is_sent,'is_failed'=>!$is_sent,]);
        }
        return true;
    }

    private function render($template,$context){
        if(!$template || !$context){
            return $template;
        }
        foreach($context as $key=>$value){
            if(empty($value)){ 
                continue;
            }
            $template=str_replace('{{'.$key.'}}',$value,$template);
        }
        return preg_replace('/{{\w+}}/','',$template);
    }
}