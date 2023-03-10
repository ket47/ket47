<?php
namespace App\Models;
use CodeIgniter\Model;

class ReactionModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'reaction_list';
    protected $primaryKey = 'reaction_id';
    protected $allowedFields = [
        'reaction_is_like',
        'reaction_is_dislike',
        ];

    protected $useSoftDeletes = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
    
    
    public function itemGet( int $reaction_id ){
        $this->permitWhere('r');
        $this->where('reaction_id',$reaction_id);
        return $this->get()->getRow();
    }
    





    private function itemTagsExpand($tagQuery){
        $expandedTagQuery='';
        $ReactionTagModel=model('ReactionTagModel');
        $tag_list=$ReactionTagModel->listParse( $tagQuery );
        foreach( $tag_list as $tag ){
            $parsed_tag=$ReactionTagModel->tagParse($tag);
            if($parsed_tag->tag_name=='product'){
                $expandedTagQuery.=$this->expandTagFromProduct($parsed_tag->tag_id);
            }
            if($parsed_tag->tag_name=='entry'){
                $expandedTagQuery.=$this->expandTagFromProduct(null, $parsed_tag->tag_id);
            }
        }
        return $expandedTagQuery;
    }

    private function expandTagFromProduct( $product_id=null, $entry_id=null ){
        $owner_id=session()->get('user_id');
        $EntryModel=model('EntryModel');
        if( $product_id ){
            $EntryModel->where('product_id',$product_id);
        } else
        if( $entry_id ){
            $EntryModel->where('entry_id',$entry_id);
        } else {
            return null;
        }

        $EntryModel->join('reaction_tag_list','tag_name="entry" AND tag_id=entry_id','left');
        $EntryModel->where('owner_id',$owner_id);
        $EntryModel->where('link_id IS NULL');
        $EntryModel->orderBy('updated_at');
        $EntryModel->limit(1);
        $EntryModel->select('entry_id,product_id');
        $entry=$EntryModel->get()->getRow();
        $entry_id=$entry->entry_id??0;
        $product_id=$entry->product_id??0;

        if( !$entry_id || !$product_id ){
            return null;
        }

        $ProductModel=model('ProductModel');
        $ProductModel->where('product_id',$product_id);
        $ProductModel->select('store_id');
        $store_id=$ProductModel->get()->getRow('store_id');
        if( !$store_id ){
            return null;
        }

        return " product:$product_id entry:$entry_id store:$store_id";
    }






    public function itemCreate( object $reaction, string $tagQuery ){
        if( !$this->permit(null,'w') ){
            return 'forbidden';
        }
        $tagQuery=$this->itemTagsExpand($tagQuery);
        if(!$tagQuery){
            return 'notfound';
        }
        $ReactionTagModel=model('ReactionTagModel');

        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='reaction_comment';
        $reaction->owner_id=session()->get('user_id');
        if($reaction->reaction_is_like??0){
            $reaction->reaction_is_dislike=0;
        }
        if($reaction->reaction_is_dislike??0){
            $reaction->reaction_is_like=0;
        }

        $this->transStart();
            $reaction_id=$this->insert($reaction,true);
            $tags_created=$ReactionTagModel->listCreate($reaction_id,$tagQuery);
            if($tags_created=='ok'){
                $this->transRollback();
                return 'notfound';
            }
        $this->transComplete();
        return $reaction_id;
    }
    
    public function itemUpdate(object $reaction){
        $this->permitWhere('w');
        if($reaction->reaction_is_like??0){
            $reaction->reaction_is_dislike=0;
        }
        if($reaction->reaction_is_dislike??0){
            $reaction->reaction_is_like=0;
        }
        $this->update($reaction->reaction_id,$reaction);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete($reaction_id){
        $this->permitWhere('w');
        $this->delete($reaction_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listGet( string $tagQuery ){
        return false;
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