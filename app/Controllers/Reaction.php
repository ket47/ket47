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
        $comment=$this->request->getPost('comment');//,FILTER_SANITIZE_SPECIAL_CHARS
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
        $this->onCommentSupplierNotify($tagQuery);
        return $this->respondCreated($result);
    }

    private function onCommentSupplierNotify( $tagQuery ){
        $ReactionModel=model('ReactionModel');
        $reaction=$ReactionModel->itemByTagGet($tagQuery);
        if( empty($reaction->reaction_comment) ){
            return;
        }

        $StoreModel=model('StoreModel');
        $StoreModel->join('reaction_tag_list','tag_name="store" AND tag_id=store_id');
        $StoreModel->where('member_id',$reaction->reaction_id);

        $ProductModel=model('ProductModel');
        $ProductModel->join('reaction_tag_list','tag_name="product" AND tag_id=product_id');
        $ProductModel->where('member_id',$reaction->reaction_id);

        $context['user']=session()->get('user_data');
        $context['reaction']=$reaction;
        $context['store']=$StoreModel->select('store_name,owner_ally_ids')->get()->getRow();
        $context['product']=$ProductModel->select('product_name,product_id')->get()->getRow();

        $reaction_sms=(object)[
            'message_transport'=>'telegram',
            'message_reciever_id'=>"-100,{$context['store']->owner_ally_ids}",//
            'template'=>'messages/events/on_customer_reaction_sms.php',
            'context'=>$context
        ];
        $notification_task=[
            'task_name'=>"Comment event",
            'task_programm'=>[
                    ['library'=>'\App\Libraries\Messenger','method'=>'listSend','arguments'=>[[$reaction_sms]]]
                ]
        ];
        jobCreate($notification_task);
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
