<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Search extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function listGet(){
        $location_id=$this->request->getVar('location_id');
        $query=trim($this->request->getVar('query'));

        $StoreModel=model('StoreModel');
        $result=$StoreModel->listNearGet(['location_id'=>$location_id]);
        if( !is_array($result) ){
            return $this->fail($result);
        }
        $response=[
            'product_matches'=>$this->listStoreProductsGet($query,$result)
        ];
        return $this->respond($response);
    }
    private function listStoreProductsGet( $query,$store_list ){
        $matched_stores=[];
        $ProductModel=model('ProductModel');
        $limit=5;
        foreach($store_list as $store){
            $filter=[
                'search_query'=>$query,
                'limit'=>4,
                'store_id'=>$store->store_id
            ];
            $ProductModel->where('(validity<>0 OR validity IS NULL)');
            $store->matches=$ProductModel->listSearch($filter);
            if($store->matches || mb_stripos($store->store_name,$query)!==false ){
                $matched_stores[]=$store;
                if(--$limit<1){
                    break;
                }
            }
        }
        return $matched_stores;
    }
}
