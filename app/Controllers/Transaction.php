<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Transaction extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function itemGet(){
        $trans_id=$this->request->getVar('trans_id');

        $TransactionModel=model('TransactionModel');
        $result=$TransactionModel->itemGet($trans_id);
        if($result=='notfound'){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }

    public function itemCreate(){
        $data = $this->request->getJSON();
        if(!$data){
            return $this->fail('malformed_request');
        }
        $TransactionModel=model('TransactionModel');
        $result = $TransactionModel->itemCreate($data);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->respondCreated($result);
    }

    public function itemUpdate(){
        $data = $this->request->getJSON();
        if(!$data){
            return $this->fail('malformed_request');
        }
        $TransactionModel=model('TransactionModel');
        $result = $TransactionModel->itemUpdate($data);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->respondUpdated($result);
    }

    public function itemDelete(){
        $trans_id=$this->request->getVar('trans_id');
        $TransactionModel=model('TransactionModel');
        $result = $TransactionModel->itemDelete($trans_id);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->respondDeleted($result);
    }









    public function listGet(){
        $filter=(object)[
            'start_at'          =>$this->request->getVar('start_at'),
            'finish_at'         =>$this->request->getVar('finish_at'),
            'tagQuery'          =>$this->request->getVar('tagQuery'),
            'searchQuery'       =>$this->request->getVar('searchQuery'),
        ];
        $start = date_create($filter->start_at);
        $finish = date_create($filter->finish_at);
        $interval = date_diff($start, $finish)->format('%a');
        if($interval>92){
            return $this->fail("large_interval");
        }

        $TransactionModel=model('TransactionModel');
        $result=$TransactionModel->listGet($filter);
        return $this->respond($result);
    }
    


}
