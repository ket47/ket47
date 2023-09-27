<?php

namespace App\Controllers\Api;
use \CodeIgniter\API\ResponseTrait;

class Product extends \App\Controllers\BaseController{
    
    use ResponseTrait;

    private function authTokenGet(){
        foreach (getallheaders() as $name => $value) {
            if( $name=='Authorization' ){
                $chunks=explode('Bearer ',$value);
                return array_pop($chunks);
            }
        }
    }
    private function auth(){
        $token_hash=$this->authTokenGet();
        if(!$token_hash){
            return $this->failForbidden();
        }
        $UserModel=model('UserModel');
        $result=$UserModel->signInByToken($token_hash,'store');
        if( $result=='ok' ){
            $user=$UserModel->getSignedUser();
            if( !$user ){
                return 'user_data_fetch_error';
            }
            session()->set('user_id',$user->user_id);
            session()->set('user_data',$user);
        }
        return $result;
    }

    private function colconfigMake($data){
        $colconfig=[];
        foreach($data->cols as $i=>$col){
            $colconfig[$col]="C".($i+1);
        }
        return (object)$colconfig;
    }

    public function listSave(){
        $data=$this->request->getJSON();
        $result=$this->auth();
        if( $result!='ok' ){
            return $this->failForbidden();
        }
        $token_data=session()->get('token_data');

        $target='product';
        $holder='store';
        $holder_id=$token_data->token_holder_id;
        $colconfig=$this->colconfigMake($data);

        $ImporterModel=model('ImporterModel');
        $ImporterModel->itemCreateAsDisabled=true;
        $ImporterModel->olderItemsDeleteTresholdSet( date('Y-m-d H:i:s',time()-3) );//delete all products that left in imported_list
        $ImporterModel->listCreate( $data->rows, $holder, $holder_id, $target, $external_id_index=0 );

        $result=$ImporterModel->listImport( $holder, $holder_id, $target, $colconfig );
        return $this->respond($result);
    }




}