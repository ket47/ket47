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
        'is_started',
        'regular_group'
        ];

    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $returnType       = 'object';


    protected function initialize(){
        $this->query("SET character_set_results = utf8mb4, character_set_client = utf8mb4, character_set_connection = utf8mb4, character_set_database = utf8mb4, character_set_server = utf8mb4");
    }
    
    public function itemGet(int $mailing_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $mailing=$this->find($mailing_id);
        if( !is_object($mailing) ){
            return $mailing;
        }
        $ImageModel=model('ImageModel');
        $MailingMessageModel=model('MailingMessageModel');
        $mailing->recieverCount=$MailingMessageModel->listCountGet($mailing_id);


        $mailing->images=$ImageModel->listGet(['image_holder'=>'mailing','image_holder_id'=>$mailing_id]);

        if($mailing->images[0]->image_hash??null){
            $mailing->image=getenv('app.backendUrl')."image/get.php/{$mailing->images[0]->image_hash}.1000.1000.webp";
        }
        if(!empty($mailing->user_filter)){
            $mailing->user_filter=json_decode($mailing->user_filter);
        }
        
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
        if(!empty($mailing->user_filter)) $mailing->user_filter=json_encode($mailing->user_filter);
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
        $this->update($mailing_id,['is_started'=>1]);
        return 'ok';
    }

    public function itemCopy( $mailing_id ){
        $mailing=$this->where('mailing_id', $mailing_id)->get()->getRowArray();
        
        $mailing['subject_template'] .= ' (Copy '.rand(100, 999).')';

        $mailing['is_started'] = 0;
        $mailing['owner_id']=session()->get('user_id');

        $new_mailing_id=$this->itemCreate($mailing);
        $this->imageCopy($mailing['mailing_id'], $new_mailing_id);
        if(!empty($mailing['user_filter'])){
            $MailingMessageModel=model('MailingMessageModel');
            $MailingMessageModel->listDelete($new_mailing_id);
            $MailingMessageModel->listRecieverFill($new_mailing_id);
        }
        return $new_mailing_id;
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
    public function imageCopy( $image_holder_id, $new_image_holder_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');

        $images = $ImageModel->where('image_holder_id', $image_holder_id)->get()->getResultArray();
        $ok=1;
        foreach($images as $image){
            $image['image_holder_id'] = $new_image_holder_id;
            $image['owner_id']=session()->get('user_id');
            $ok = $ImageModel->itemCreate($image);
        }  
        if($ok){
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
    public function itemJobCreate($ids, $mailing){
        $result = 0;
        $id_batches=array_chunk($ids,100);
        $start_time = strtotime($mailing->start_at);
        foreach($id_batches as $batch){
            $start_time+=2*60;//5 min
            $mailing_task=[
                'task_name'=>"send mailing",
            //    'task_priority'=>'low',
                'task_programm'=>[
                        ['model'=>'UserModel','method'=>'systemUserLogin'],
                        ['model'=>'MailingMessageModel','method'=>'listSend','arguments'=>[$mailing,$batch]],
                        ['model'=>'UserModel','method'=>'systemUserLogout'],
                ],
               'task_next_start_time'=>$start_time
            ];
            jobCreate($mailing_task);
            $result='ok';
        }
        return $result;
    }
    
    public function nightlyCalculate(){
        
        $UserModel=model('UserModel');
        $UserModel->systemUserLogin();
        
        $OrderModel=model('OrderModel');
        $PromoModel=model('PromoModel');
        $MailingMessageModel=model('MailingMessageModel');
        $mailing_config = [];
        
        $mailing_config['cart23'] = $OrderModel->where('(order_group_id = 24 AND TIMESTAMPDIFF(HOUR,  updated_at, NOW()) < 23) OR owner_id = 43')->groupBy('owner_id')->select('owner_id as user_id')->get()->getResult();
        
        $mailing_config['cart47'] = $OrderModel->where('(order_group_id = 24 AND TIMESTAMPDIFF(HOUR,  updated_at, NOW()) BETWEEN 24 AND 47) OR owner_id = 43')->groupBy('owner_id')->select('owner_id as user_id')->get()->getResult();

        $mailing_config['promo-10'] = $PromoModel->where('(TIMESTAMPDIFF(HOUR, NOW(), expired_at) = 10) OR owner_id = 43')->groupBy('owner_id')->select('owner_id as user_id')->get()->getResult();
        $mailing_config['promo-3'] = $PromoModel->where('(TIMESTAMPDIFF(HOUR, NOW(), expired_at) = 3) OR owner_id = 43')->groupBy('owner_id')->select('owner_id as user_id')->get()->getResult();
        $mailing_config['promo-1'] = $PromoModel->where('(TIMESTAMPDIFF(HOUR, NOW(), expired_at) = 1) OR owner_id = 43')->groupBy('owner_id')->select('owner_id as user_id')->get()->getResult();

        $mailing_config['forgot14'] = $UserModel->where('(TIMESTAMPDIFF(DAY,  signed_in_at, NOW()) = 14) OR owner_id = 43')->groupBy('user_id')->select('user_id')->get()->getResult();
        $mailing_config['forgot30'] = $UserModel->where('(TIMESTAMPDIFF(DAY,  signed_in_at, NOW()) = 30) OR owner_id = 43')->groupBy('user_id')->select('user_id')->get()->getResult();
        $mailing_config['forgot90'] = $UserModel->where('(TIMESTAMPDIFF(DAY,  signed_in_at, NOW()) = 90) OR owner_id = 43')->groupBy('user_id')->select('user_id')->get()->getResult();
        $willsend_at = date("Y-m-d 09:00:00");
        foreach( $mailing_config as $regular_group => $mailing_receivers ){
            $mailing = $this->where('regular_group', $regular_group)->get()->getRow();
    
            if(!empty($mailing)){
                $MailingMessageModel->where('mailing_id', $mailing->mailing_id)->delete();
    
                $receivers = [];
    
                foreach( $mailing_receivers as $user ){
                    $receivers[] = $user->user_id;
                    $mailing_message = [
                        'reciever_id' => $user->user_id,
                        'mailing_id' => $mailing->mailing_id,
                        'willsend_at' => $willsend_at
                    ];
                    $MailingMessageModel->itemCreate($mailing_message);
                }
    
                $mailing->start_at = $willsend_at;
                $mailing->is_started = 1;
                $this->itemUpdate((object)[
                    'mailing_id' => $mailing->mailing_id,
                    'user_filter' => [],
                    'start_at' => $mailing->start_at,
                    'is_started' => $mailing->is_started
                ]);
                $this->itemJobCreate($receivers, $mailing);
            } 
        }
        $UserModel->systemUserLogout();
    }
}