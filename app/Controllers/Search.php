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
        $mode='nearstores';
        $SearchModel=model('SearchModel');
        $result=$SearchModel->storeMatchesGet( $filter, $mode );
        if( $mode == 'nearstores' && !count($result) ){
            $mode = 'farstores';
            $result=$SearchModel->storeMatchesGet( $filter, $mode );
        }
        $response=[
            'mode'=>$mode,
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
        //bench('matchTableCreate Response');
        return $this->respond($result);
    }

    public function categoryListGet(){
        $parent_id=(int) $this->request->getVar('parent_id');
        $ProductGroupModel=model('ProductGroupModel');
        $ProductGroupModel->where('product_group_list.group_parent_id',$parent_id);
        $ProductGroupModel->join('image_list',"image_holder='product_group_list' AND image_holder_id=product_group_list.group_id AND is_main=1",'left');
        $ProductGroupModel->join('product_group_list pgl2',"pgl2.group_parent_id=product_group_list.group_id");
        $ProductGroupModel->join('product_group_member_list pgml',"pgl2.group_id=pgml.group_id");
        
        $ProductGroupModel->select('product_group_list.group_id,product_group_list.group_name,image_hash,COUNT(*) product_count');
        $ProductGroupModel->orderBy('product_count','DESC');
        $ProductGroupModel->groupBy('product_group_list.group_id');
        $result=$ProductGroupModel->findAll();
        if( !$result ){
            return $this->failNotFound();
        }
        return $this->respond($result);
    }
}
