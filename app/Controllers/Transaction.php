<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Transaction extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function listGet(){
        $filter=(object)[
            'trans_holder'      =>$this->request->getVar('trans_holder'),
            'trans_holder_id'   =>$this->request->getVar('trans_holder_id'),
            'trans_tags'        =>$this->request->getVar('trans_tags'),
            'start_at'          =>$this->request->getVar('start_at'),
            'finish_at'         =>$this->request->getVar('finish_at'),
            'account'           =>$this->request->getVar('account'),
        ];
        $start = date_create($filter->start_at);
        $finish = date_create($filter->finish_at);
        $interval = date_diff($start, $finish)->format('%a');
        if($interval>92){
            return $this->fail("large_interval");
        }
        $TransactionModel=model('TransactionModel');
        $result=$TransactionModel->listGet($filter);
        if($result=='noaccount'){
            return $this->fail($result);
        }
        return $this->respond($result);
    }
    


}
