<?php
namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;
use \CodeIgniter\HTTP\RequestInterface;
use \CodeIgniter\HTTP\ResponseInterface;

use \Psr\Log\LoggerInterface;

class TaskManager extends \App\Controllers\BaseController {
    use ResponseTrait;
    
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger){
        parent::initController($request, $response, $logger);
        if( !sudo() ){
            die('Access denied!');
        }
    }
    
    public function index(){
        $TaskModel=model('TaskModel');
        $task_list=$TaskModel->listGet();
        return view('admin/task_manager',['task_list'=>$task_list]);
    }
    
    
    public function itemCreate(){
        if( !sudo() ){
            return $this->failForbidden();
        }
        $task_name=$this->request->getVar('task_name');
        $task=[
            'task_name'=>$task_name,
        ];
        $TaskModel=model('TaskModel');
        $result=$TaskModel->itemCreate($task);
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        return $this->fail($result);        
    }
    
    public function itemUpdate(){
        if( !sudo() ){
            return $this->failForbidden();
        }
        $data= $this->request->getJSON();
        $TaskModel=model('TaskModel');
        $result=$TaskModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $TaskModel->errors() ){
            return $this->failValidationErrors(json_encode($TaskModel->errors()));
        }
        return $this->respondUpdated($result);     
    }
    
    public function itemDelete(){
        if( !sudo() ){
            return $this->failForbidden();
        }
        $task_id=$this->request->getVar('task_id');
        $TaskModel=model('TaskModel');
        $result=$TaskModel->itemDelete($task_id);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
}