<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class DeliveryJob extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }

    /**
     * This is intended for couriers
     */
    public function itemTake(){
        if( !courdo() ){
            return $this->failForbidden('forbidden');
        }
        $order_id=$this->request->getPost('order_id');

        $OrderModel=model("OrderModel");
        $CourierModel=model('CourierModel');
        $OrderGroupMemberModel=model('OrderGroupMemberModel');

        $courier=$CourierModel->itemGet(null,'basic');//getting courier by user_id from session
        /**
         * courier experimentally can take all pre assigned jobs
         */
        //$CourierGroupMemberModel=model('CourierGroupMemberModel');
        // $isCourierReady=$CourierGroupMemberModel->isMemberOf($courier->courier_id,'ready');
        // if( !$isCourierReady ){
        //     return $this->fail('notready');
        // }
        $isSearching4Courier=$OrderGroupMemberModel->isMemberOf($order_id,'delivery_search');
        if( !$isSearching4Courier ){
            return $this->fail('notsearching');
        }
        $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');

        $OrderModel->allowWrite();//allow modifying order once
        $OrderModel->update($order_id,(object)['order_courier_id'=>$courier->courier_id,'order_courier_admins'=>$courier->owner_id]);
        $OrderModel->itemUpdateOwners($order_id);
        $OrderModel->itemCacheClear();
        $result= $OrderModel->itemStageAdd( $order_id, 'delivery_found' );
        if($result=='ok'){
            return $this->respond($result);
        }
        return $this->fail($result);
    }

    /**
     * This is intended for admins
     */
    public function itemAssign(){
        if( !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $order_id=$this->request->getPost('order_id');
        $courier_id=$this->request->getPost('courier_id');

        $OrderModel=model("OrderModel");
        $CourierModel=model('CourierModel');
        $OrderGroupMemberModel=model('OrderGroupMemberModel');

        $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');
        $courier=$CourierModel->itemGet($courier_id,'basic');
        $CourierModel->itemUpdateStatus($courier_id,'busy');
        $CourierModel->itemJobStartNotify( $courier->owner_id, ['courier'=>$courier,'order_id'=>$order_id] );

        $OrderModel->allowWrite();//allow modifying order once
        $OrderModel->update($order_id,(object)['order_courier_id'=>$courier_id,'order_courier_admins'=>$courier->owner_id]);
        $OrderModel->itemUpdateOwners($order_id);
        $OrderModel->itemCacheClear();
        $result= $OrderModel->itemStageAdd( $order_id, 'delivery_found' );

        if($result=='ok'){
            return $this->respond($result);
        }
        return $this->fail($result);
    }
    
    public function listGet(){
        $user_id=session()->get('user_id');
        if( !courdo() && !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $CourierModel=model('CourierModel');
        $courier_id=$CourierModel->where('owner_id',$user_id)->select('courier_id')->get()->getRow('courier_id');
        if( !$courier_id ){
            return $this->failForbidden('forbidden');
        }
        $DeliveryJobModel=model('DeliveryJobModel');
        $DeliveryJobModel->join('courier_list','courier_id','left');
        $DeliveryJobModel->select('courier_name');
        $deliveryJobs=$DeliveryJobModel->listGet();
        return $this->respond($deliveryJobs);
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
 
}