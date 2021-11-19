<?php

namespace App\Controllers;

class Home extends BaseController {

    public function index() {
        if( session()->get('user_id') ) {
            return view('home/dashboard');
        }
        return view('user/signin_form');
    }

    public function courier_manager(){
        return view('courier/courier_manager');
    }

    public function courier_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit')
        ];
        $CourierModel=model('CourierModel');
        $courier_list=$CourierModel->listGet($filter);
        return view('courier/courier_list', [
            'courier_list' => $courier_list,
                ]);
    }
    public function courierCardGet(){
        $courier_id=$this->request->getVar('courier_id');
        $CourierModel=model('CourierModel');
        $courier= $CourierModel->itemGet($courier_id);
        
        $CourierGroupModel=model('CourierGroupModel');
        $status_list=$CourierGroupModel->listGet();
        
        if(is_object($courier)){
            $data=[
                'courier'=>$courier,
                'courier_group_list'=>$status_list
            ];
            return view('courier/courier_card',$data);
        }
        return 'forbidden';
    }
    
    
    
    
    
    
    
    
    public function payment_form(){
        $payment_description=$this->request->getVar('payment_description');
        $payment_amount=$this->request->getVar('payment_amount');
        $payment=[
            'payment_description'=>$payment_description,
            'payment_amount'=>$payment_amount
        ];
        return view('payment/payment_form',$payment);
    }
    
    public function payment_submit(){
        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function user_login_form() {
        if( session()->get('user_id')>0 ) {
            $user_data = (array) session()->get('user_data');
            return view('user/signed_userdata', ['user' => $user_data]);
        }
        return view('user/signin_form');
    }
    
    public function user_data(){
        if( session()->get('user_id') ) {
            $user_data = (array) session()->get('user_data');
            return view('user/signed_userdata', ['user' => $user_data]);
        } else {
            echo "NOT SIGNED IN";
        }        
    }
    
    public function user_manager(){
        return view('user/user_manager');
    }
    
    public function user_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit')
        ];
        $UserModel=model('UserModel');
        $user_list=$UserModel->listGet($filter);
        
        $UserGroupModel=model('UserGroupModel');
        $user_group_list=$UserGroupModel->listGet();
        return view('user/user_list', [
            'user_list' => $user_list,
            'user_group_list'=>$user_group_list
                ]); 
    }
    public function userCardGet(){
        $user_id=$this->request->getVar('user_id');
        $UserModel=model('UserModel');
        $UserGroupModel=model('UserGroupModel');
        $user= $UserModel->itemGet($user_id);
        
        $user_group_list=$UserGroupModel->listGet();
        $data=[
            'user'=>$user,
            'user_group_list'=>$user_group_list
        ];
        return view('user/user_card',$data);
    }

    

    public function user_register_form() {
        return view('user/register_form');
    }

    public function user_phone_verification() {
        return view('user/phone_verification');
    }

    public function user_password_reset() {
        return view('user/password_reset');
    }








    public function product_importer(){
        return view('product/product_importer');
    }

    
    public function product_manager(){
        return view('product/product_manager');
    }
    
    
    public function product_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
            'offset'=>$this->request->getVar('offset'),
            'store_id'=>$this->request->getVar('store_id'),
            'group_id'=>$this->request->getVar('group_id'),
        ];
        $ProductModel=model('ProductModel');
        $ProductGroupModel=model('ProductGroupModel');
        $product_list=$ProductModel->listGet($filter);
        $product_group_list=$ProductGroupModel->listGet(['level'=>2]);
        
        $data=[
            'product_list' => $product_list,
            'product_group_list'=>$product_group_list
            ];
        return view('product/product_list', $data);
    }
    
    public function productCardGet(){
        $product_id=$this->request->getVar('product_id');
        $ProductModel=model('ProductModel');
        $ProductGroupModel=model('ProductGroupModel');
        $product= $ProductModel->itemGet($product_id);
        
        $product_group_list=$ProductGroupModel->listGet();
        $data=[
            'product'=>$product,
            'product_group_list'=>$product_group_list
        ];
        return view('product/product_card',$data);
    }
    
    
    
    
    
    
    
    public function store_manager(){
        return view('store/store_manager');
    }
    public function store_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit')
        ];
        $StoreModel=model('StoreModel');
        $GroupModel=model('StoreGroupModel');
        $store_list=$StoreModel->listGet($filter);
        $store_group_list=$GroupModel->listGet();
        return view('store/store_list', [
            'store_list' => $store_list,
            'store_group_list'=>$store_group_list
                ]);
    }
    public function storeCardGet(){
        $product_id=$this->request->getVar('store_id');
        $StoreModel=model('StoreModel');
        $StoreGroupModel=model('StoreGroupModel');
        $product= $StoreModel->itemGet($product_id);
        
        $product_group_list=$StoreGroupModel->listGet();
        $data=[
            'store'=>$product,
            'store_group_list'=>$product_group_list
        ];
        return view('store/store_card',$data);
    }
    
    









    public function order_manager(){
        return view('order/order_manager');
    }

    public function order_list(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
            'order_store_id'=>$this->request->getVar('order_store_id'),
        ];
        $OrderModel=model('OrderModel');
        //$GroupModel=model('OrderGroupModel');
        $order_list=$OrderModel->listGet($filter);
        //$order_group_list=$GroupModel->listGet();
        return view('order/order_list', [
            'order_list' => $order_list,
            //'order_group_list'=>$order_group_list
                ]);
    }
    public function orderCardGet(){
        $order_id=$this->request->getVar('order_id');
        $OrderModel=model('OrderModel');
        $order= $OrderModel->itemGet($order_id);
        
        $OrderGroupModel=model('OrderGroupModel');
        $stage_list=$OrderGroupModel->listGet();
        
        
        if(is_object($order)){
            $data=[
                'order'=>$order,
                'stage_list'=>$stage_list
            ];
            return view('order/order_card',$data);
        }
        return 'forbidden';
    }
    
    public function orderEntryListGet(){
        $order_id=$this->request->getVar('order_id');
        $EntryModel=model('EntryModel');
        $entry_list=$EntryModel->listGet($order_id);
        $OrderModel=model('OrderModel');
        $order= $OrderModel->itemGet($order_id);
        return view('order/entry_list',['entry_list'=>$entry_list,'order'=>$order]);
    }
}
