<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Reaction extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        $is_like=$this->request->getVar('is_like');
        $is_dislike=$this->request->getVar('is_dislike');
        $comment=$this->request->getVar('comment',FILTER_SANITIZE_SPECIAL_CHARS);
        $tagQuery=$this->request->getVar('tagQuery');

        $reaction=(object)[
            'reaction_is_like'=>$is_like,
            'reaction_is_dislike'=>$is_dislike,
            'reaction_comment'=>$comment
        ];
        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->itemCreate($reaction,$tagQuery);
        if($result=='forbidden'){
            return $this->failForbidden($result);
        }
        if($result=='notfound'){
            return $this->failNotFound($result);
        }
        return $this->respondCreated($result);
    }
    
    public function itemUpdate(){
        $reaction_id=$this->request->getVar('reaction_id');
        $is_like=$this->request->getVar('is_like');
        $is_dislike=$this->request->getVar('is_dislike');

        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->itemUpdate((object)[
            'reaction_is_like'=>$is_like,
            'reaction_is_dislike'=>$is_dislike,
            'reaction_id'=>$reaction_id
        ]);
        return $this->respondUpdated($result);
    }
    
    public function itemDelete(){
        $reaction_id=$this->request->getVar('reaction_id');
        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->itemDelete($reaction_id);
        return $this->respondDeleted($result);
    }
    
    public function listGet(){
        $offset=$this->request->getVar('offset');
        $limit=$this->request->getVar('limit');
        $tagQuery=$this->request->getVar('tagQuery');

        $filter=[
            'offset'=>$offset,
            'limit'=>$limit,
            'tagQuery'=>$tagQuery
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
        $search_query=$this->request->getVar('search_query');
        $target_type=$this->request->getVar('target_type');
        $target_id=$this->request->getVar('target_id');
        $filter=[
            'search_query'=>$search_query,
            'target_type'=>$target_type,
            'target_id'=>$target_id
        ];
        $ReactionModel=model('ReactionModel');
        $result=$ReactionModel->entryListGet($filter);

        return $this->respond($result);
    }
 
}
