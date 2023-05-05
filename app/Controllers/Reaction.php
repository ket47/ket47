<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Reaction extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemSave(){
        $is_like=$this->request->getPost('is_like');
        $is_dislike=$this->request->getPost('is_dislike');
        $comment=$this->request->getPost('comment',FILTER_SANITIZE_SPECIAL_CHARS);
        $tagQuery=$this->request->getPost('tagQuery');

        if(!$tagQuery){
            return $this->fail('tagquery_required');
        }
        $reaction=(object)[];
        if($is_like!==null){
            $reaction->reaction_is_like=$is_like;
        }
        if($is_dislike!==null){
            $reaction->reaction_is_dislike=$is_dislike;
        }
        if($comment!==null){
            $reaction->reaction_comment=$comment;
        }

        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->itemSave($reaction,$tagQuery);
        if($result=='forbidden'){
            return $this->failForbidden($result);
        }
        if($result=='notfound'){
            return $this->failNotFound($result);
        }
        return $this->respondCreated($result);
    }
    
    public function itemDelete(){
        $reaction_id=$this->request->getPost('reaction_id');
        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->itemDelete($reaction_id);
        return $this->respondDeleted($result);
    }
    
    public function listGet(){
        $offset=$this->request->getPost('offset');
        $limit=$this->request->getPost('limit');
        $tagQuery=$this->request->getPost('tagQuery');
        $commentsOnly=$this->request->getPost('commentsOnly');

        $filter=[
            'offset'=>$offset,
            'limit'=>$limit,
            'tagQuery'=>$tagQuery,
            'commentsOnly'=>$commentsOnly
        ];
        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->listGet($filter);
        return $this->respond($result);
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

    public function itemListGet(){
        $offset=$this->request->getPost('offset');
        $limit=$this->request->getPost('limit');
        $name_query=$this->request->getPost('name_query');
        $target_type=$this->request->getPost('target_type');
        $target_id=$this->request->getPost('target_id');
        $filter=[
            'offset'=>$offset,
            'limit'=>$limit,
            'name_query'=>$name_query,
            'target_type'=>$target_type,
            'target_id'=>$target_id
        ];
        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->entryListGet($filter);

        return $this->respond($result);
    }
 
}
