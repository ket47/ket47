<?php

namespace App\Controllers;

use \CodeIgniter\API\ResponseTrait;

class Order extends \App\Controllers\BaseController {

    use ResponseTrait;

    public function itemGet() {
        $order_id = $this->request->getVar('order_id');
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemGet($order_id);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'notfound') {
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }

    public function itemCreate() {
        $order_order_id = $this->request->getVar('order_order_id');
        $entry_list_json = $this->request->getVar('entry_list');
        $entry_list = [];
        if ($entry_list_json) {
            $entry_list = json_decode($entry_list_json);
        }
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemCreate($order_order_id, $entry_list);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($result === 'noorder') {
            return $this->fail($result);
        }
        if ($OrderModel->errors()) {
            return $this->failValidationErrors($OrderModel->errors());
        }
        return $this->respond($result);
    }

    public function itemUpdate() {
        $data = $this->request->getJSON();
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemUpdate($data);
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        if ($OrderModel->errors()) {
            return $this->failValidationErrors($OrderModel->errors());
        }
        return $this->respondUpdated($result);
    }

    public function itemStageCreate() {
        $order_id = $this->request->getVar('order_id');
        $new_stage = $this->request->getVar('new_stage');
        return $this->itemStage($order_id, $new_stage);
    }

    private function itemStage($order_id, $stage) {
        $OrderModel = model('OrderModel');
        $result = $OrderModel->itemStageCreate($order_id, $stage);
        if ($result === 'ok') {
            return $this->respondUpdated($result);
        }
        if ($result === 'forbidden') {
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }

    public function itemDelete() {
        $order_id = $this->request->getVar('order_id');
        return $this->itemStage($order_id, 'customer_deleted');
    }

    public function itemUnDelete() {
        $order_id = $this->request->getVar('order_id');
        return $this->itemStage($order_id, 'customer_created');
    }

    public function itemDisable() {
        return $this->failNotFound();
    }

//    public function itemDelete(){
//        $order_id=$this->request->getVar('order_id');
//        
//        $OrderModel=model('OrderModel');
//        $result=$OrderModel->itemDelete($order_id);        
//        if( $result==='ok' ){
//            return $this->respondDeleted($result);
//        }
//        if( $result==='forbidden' ){
//            return $this->failForbidden($result);
//        }
//        return $this->fail($result);   
//    }
//    
//    public function itemUnDelete(){
//        $order_id=$this->request->getVar('order_id');
//        
//        $OrderModel=model('OrderModel');
//        $result=$OrderModel->itemUnDelete($order_id);        
//        if( $result==='ok' ){
//            return $this->respondUpdated($result);
//        }
//        if( $result==='forbidden' ){
//            return $this->failForbidden($result);
//        }
//        return $this->fail($result);   
//    }
//
//    
//    public function itemDisable(){
//        $order_id=$this->request->getVar('order_id');
//        $is_disabled=$this->request->getVar('is_disabled');
//        
//        $OrderModel=model('OrderModel');
//        $result=$OrderModel->itemDisable($order_id,$is_disabled);
//        if( $result==='ok' ){
//            return $this->respondUpdated($result);
//        }
//        if( $result==='forbidden' ){
//            return $this->failForbidden($result);
//        }
//        return $this->fail($result);
//    }


    public function listGet() {
        return false;
    }

    public function listStageGet() {
        $OrderGroupModel = model('OrderGroupModel');
        $result = $OrderGroupModel->listGet();
        return $this->respond($result);
    }

    public function listCreate() {

        \CodeIgniter\Events\Events::on('post_system', [$this, 'long_wait']);


        return false;
    }

    public function respondOK($text = null) {
        ignore_user_abort(true);


//        $serverProtocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING);
//        header($serverProtocol . ' 200 OK');
//        // Disable compression (in case content length is compressed).
//        header('Content-Encoding: none');
//        header('Content-Length: ' . ob_get_length());

        // Close the connection.
        //header('Connection: close');

        ob_end_flush();
        ob_flush();
        flush();
    }

    public function long_wait() {
        $this->respondOK();
        sleep(1);
        echo('Text user will never see');

        sleep(1);
        echo 'hahaha';
        die();
    }

    public function listUpdate() {
        return false;
    }

    public function listDelete() {
        return false;
    }

    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload() {
        $image_holder_id = $this->request->getVar('image_holder_id');
        $items = $this->request->getFiles();
        if (!$items) {
            return $this->failResourceGone('no_files_uploaded');
        }
        foreach ($items['files'] as $file) {
            $type = $file->getClientMimeType();
            if (!str_contains($type, 'image')) {
                continue;
            }
            if ($file->isValid() && !$file->hasMoved()) {
                $result = $this->fileSaveImage($image_holder_id, $file);
                if ($result !== true) {
                    return $result;
                }
            }
        }
        return $this->respondCreated('ok');
    }

    private function fileSaveImage($image_holder_id, $file) {
        $image_data = [
            'image_holder' => 'order',
            'image_holder_id' => $image_holder_id
        ];
        $OrderModel = model('OrderModel');
        $image_hash = $OrderModel->imageCreate($image_data);
        if (!$image_hash) {
            return $this->failForbidden('forbidden');
        }
        if ($image_hash === 'limit_exeeded') {
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH . 'images/', $image_hash . '.webp');

        return \Config\Services::image()
                        ->withFile(WRITEPATH . 'images/' . $image_hash . '.webp')
                        ->resize(1024, 1024, true, 'height')
                        ->convert(IMAGETYPE_WEBP)
                        ->save();
    }

    public function imageDelete() {
        $image_id = $this->request->getVar('image_id');

        $OrderModel = model('OrderModel');
        $result = $OrderModel->imageDelete($image_id);
        if ($result === 'ok') {
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }

}
