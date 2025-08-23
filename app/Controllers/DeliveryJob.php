<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class DeliveryJob extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemCustomerDetailGet(){
        $job_id=$this->request->getPost('job_id');
        if( !courdo() ){
            return $this->failForbidden('notacourier');
        }
        $DeliveryJobModel=model('DeliveryJobModel');
        $DeliveryJobModel->join('order_list','order_id');
        $DeliveryJobModel->join('user_list','order_list.owner_id=user_id');
        $DeliveryJobModel->select('user_name,user_phone');

        $DeliveryJobModel->where('job_id',$job_id);
        $DeliveryJobModel->where("job_data->>'$.payment_by_cash'=1",null,false);
        $customerContacts=$DeliveryJobModel->get()->getRow();
        if( !$customerContacts ){
            return $this->failNotFound('not_found');
        }
        return $this->respond($customerContacts);
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
        if( !courdo() && !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $DeliveryJobModel=model('DeliveryJobModel');
        $DeliveryJobModel->join('courier_list','courier_id','left');
        $DeliveryJobModel->select('courier_name');
        $deliveryJobs=$DeliveryJobModel->listGet();
        return $this->respond($deliveryJobs);
    }
    
    public function routeListGet(){
        if( !courdo() && !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $user_id=session()->get('user_id');

        $CourierShiftModel=model('CourierShiftModel');
        $DeliveryJobModel=model('DeliveryJobModel');

        $delivery_jobs=$DeliveryJobModel->listGet();

        $CourierShiftModel->allowRead();//??? allowing see couriers each other???
        $CourierShiftModel->join('courier_list','courier_id','left');
        $CourierShiftModel->join('image_list','courier_list.courier_id=image_holder_id AND image_holder="courier"','left');
        $CourierShiftModel->select("courier_name,image_hash,IF({$user_id}=courier_shift_list.owner_id,1,0) users_shift");
        $open_shifts=$CourierShiftModel->listGet((object)['shift_status'=>'open']);

        $user_has_opened_shift=false;
        foreach($open_shifts as $shift){
            if( $shift->users_shift==1 ){
                $user_has_opened_shift=true;
                break;
            }
        }
        /**
         * Courier has to open shift to see routes
         */
        if( $user_has_opened_shift==false && !sudo() ){
            return $this->failForbidden('forbidden');
        }
        $routeList=[
            'delivery_jobs'=>$delivery_jobs,
            'open_shifts'=>$open_shifts
        ];
        return $this->respond($routeList);
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