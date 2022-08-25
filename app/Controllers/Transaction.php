<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Transaction extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function itemFind(){
        $trans_holder=$this->request->getVar('trans_holder');
        $trans_holder_id=$this->request->getVar('trans_holder_id');
        $trans_tags=$this->request->getVar('trans_tags');

        $filter=(object)[];
        if($trans_holder){
            $filter->trans_holder=$trans_holder;
        }
        if($trans_holder_id){
            $filter->trans_holder_id=$trans_holder_id;
        }
        if($trans_tags){
            $filter->trans_tags=$trans_tags;
        }
        $TransactionModel=model('TransactionModel');
        $result=$TransactionModel->listFind($filter);
        return $this->respond($result);
    }
    
}
