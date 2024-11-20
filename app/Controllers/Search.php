<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Search extends \App\Controllers\BaseController{

    use ResponseTrait;

    public function listGet(){
        $location_id=$this->request->getVar('location_id');
        $query=trim($this->request->getVar('query'));
        $limit=$this->request->getVar('limit');
        $filter=[
            'query'=>$query,
            'location_id'=>$location_id,
            'limit'=>$limit??100
        ];
        $SearchModel=model('SearchModel');
        $result=$SearchModel->storeMatchesGet( $filter );
        $response=[
            'product_matches'=>$result
        ];
        if( $query ){
            madd('search','get',count($result)?'ok':'error',null,$query,(object) ['append'=>1]);
        }
        //bench('matchTableCreate Response');
        return $this->respond($response);
    }

    public function suggestionListGet(){
        $location_id=$this->request->getVar('location_id');
        $query=trim($this->request->getVar('query'));
        $limit=$this->request->getVar('limit');
        $filter=[
            'query'=>$query,
            'location_id'=>$location_id,
            'limit'=>$limit??100
        ];
        $SearchModel=model('SearchModel');
        $result=$SearchModel->suggestionListGet( $filter );
        $response=[
            'product_matches'=>$result
        ];
        //bench('matchTableCreate Response');
        return $this->respond($response);
    }

    public function categoryListGet(){
        $parent_id=(int) $this->request->getVar('parent_id');
        $ProductGroupModel=model('ProductGroupModel');
        $ProductGroupModel->where('group_parent_id',$parent_id);
        $ProductGroupModel->join('image_list',"image_holder='product_group_list' AND image_holder_id=group_id AND is_main=1",'left');
        $ProductGroupModel->select('group_id,group_name,image_hash');
        $ProductGroupModel->orderBy('group_name');
        $result=$ProductGroupModel->findAll();
        if( !$result ){
            return $this->failNotFound();
        }
        return $this->respond($result);
    }
}
