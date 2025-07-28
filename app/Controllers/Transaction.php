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
        $trans=(object)[
            'tags'=>$this->request->getPost('tags'),

            'trans_date'=>$this->request->getPost('trans_date'),
            'trans_amount'=>$this->request->getPost('trans_amount'),
            'trans_role'=>$this->request->getPost('trans_role'),
            'trans_description'=>$this->request->getPost('trans_description'),
            'is_disabled'=>$this->request->getPost('is_disabled')
        ];
        $TransactionModel=model('TransactionModel');
        $result = $TransactionModel->itemCreate($trans);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->respondCreated($result);
    }

    public function itemUpdate(){
        $trans=(object)[
            'tags'=>$this->request->getPost('tags'),

            'trans_id'=>$this->request->getPost('trans_id'),
            'trans_date'=>$this->request->getPost('trans_date'),
            'trans_amount'=>$this->request->getPost('trans_amount'),
            'trans_role'=>$this->request->getPost('trans_role'),
            'trans_description'=>$this->request->getPost('trans_description'),
            'is_disabled'=>$this->request->getPost('is_disabled')
        ];
        $TransactionModel=model('TransactionModel');
        $result = $TransactionModel->itemUpdate($trans);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if($result != 'ok'){
            return $this->fail($result);
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
            'limit'             =>(int)$this->request->getVar('limit'),
            'offset'            =>(int)$this->request->getVar('offset'),
        ];
        // $start = date_create($filter->start_at);
        // $finish = date_create($filter->finish_at);
        // $interval = date_diff($start, $finish)->format('%a');
        // if($interval>92){
        //     return $this->fail("large_interval");
        // }

        $TransactionModel=model('TransactionModel');
        $result=$TransactionModel->listGet($filter);
        return $this->respond($result);
    }
    
    public function listServiceActGet(){
        $filter=(object)[
            'start_at'          =>'2023-07-01',//$this->request->getVar('start_at'),
            'finish_at'         =>'2025-07-01',//$this->request->getVar('finish_at'),
            'tagQuery'          =>'acc::supplier store:142',// acc::profit
        ];

        $TransactionModel=model('TransactionModel');
        $result=$TransactionModel->balanceGet222($filter);





        // $start_case= $filter->start_at?"trans_date>'{$filter->start_at} 00:00:00'":"1";
        // $finish_case=$filter->finish_at?"trans_date<'{$filter->finish_at} 23:59:59'":"1";
        // $sql_ledger_get="
        //     SELECT
        //         *
        //     FROM
        //         tmp_ledger_inner
        //     WHERE
        //         $start_case
        //     AND $finish_case
        // ";
        // $result=$TransactionModel->query($sql_ledger_get)->getResult();
        return $this->respond($result);
    }
    


}
