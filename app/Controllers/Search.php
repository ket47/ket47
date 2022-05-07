<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Search extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function listGet(){
        $location_id=$this->request->getVar('location_id');
        $query=$this->request->getVar('query');

        $StoreModel=model('StoreModel');
        $nearStoreList=$StoreModel->listNearGet(['location_id'=>$location_id]);
        $result=[
            'product_matches'=>$this->listStoreProductsGet($query,$nearStoreList)
        ];
        return $this->respond($result);
    }
    private function listStoreProductsGet( $query,$store_list ){
        $matched_stores=[];
        $ProductModel=model('ProductModel');
        foreach($store_list as $store){
            $filter=[
                'name_query'=>$query,
                'name_query_fields'=>'product_name',
                'limit'=>3,
                'store_id'=>$store->store_id,
                'order'=>'product_final_price'
            ];
            $store->matches=$ProductModel->listGet($filter);
            if($store->matches){
                $matched_stores[]=$store;
            }
        }
        return $matched_stores;
    }
}
