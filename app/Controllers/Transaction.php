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
            'trans_holder'      =>$this->request->getVar('trans_holder'),
            'trans_holder_id'   =>$this->request->getVar('trans_holder_id'),
            'trans_tags'        =>$this->request->getVar('trans_tags'),
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

        $trans_list=$TransactionModel->get(5000)->getResult();
        foreach($trans_list as $trans){
            $tags=" {$trans->trans_holder}:{$trans->trans_holder_id}";

            if($trans->trans_role=='money.account->supplier'){
                $trans->trans_role='capital.account->supplier';
            }

            preg_match_all('/#([^\d#-]+)(\d+)/',$trans->trans_tags, $output_array);
            foreach($output_array[1] as $i=>$v){
                $id=(int)$output_array[2][$i];
                if(!$id){
                    continue;
                }
                $tags.=" {$v}:{$id}";
            }
            
            $trans->tags=$tags;
            $trans->trans_holder=null;
            $trans->trans_holder_id=null;
            $trans->trans_tags=null;

            $TransactionModel->itemUpdate($trans);
        }



        $result=$TransactionModel->listGet($filter);
        if($result=='no_account'){
            return $this->fail($result);
        }
        return $this->respond($result);
    }
    


}
