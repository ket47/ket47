<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Usercards extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemCreate(){
        
        return false;
    }
    
    public function itemMainSet(){
        $card_id=$this->request->getVar('card_id');
        $UserCardModel=model('UserCardModel');
        $result=$UserCardModel->itemMainSet($card_id);
        if($result=='notfound'){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function itemDelete(){
        $card_id=$this->request->getVar('card_id');
        $UserCardModel=model('UserCardModel');
        $cof=$UserCardModel->itemGet($card_id);
        if( $cof=='notfound' ){
            return $this->failNotFound();
        }
        $Acquirer=new \App\Libraries\AcquirerRncb();//\Config\Services::acquirer();
        $Acquirer->cardRegisteredRemove($cof->owner_id,$cof->card_remote_id);
        $Acquirer->cardRegisteredSync( $cof->owner_id );

        // $result=$UserCardModel->itemDelete($card_id);
        // if($result=='idle'){
        //     return $this->failNotFound($result);
        // }
        return $this->respond('ok');
    }
    
    public function listGet(){
        $user_id=session()->get('user_id');
        if( !($user_id>0) ){
            return $this->failForbidden('forbidden');
        }
        $UserCardModel=model('UserCardModel');
        $result=$UserCardModel->listGet($user_id);
        if($result=='notfound'){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
 
}
