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
        'reaction_comment',
        'sealed_at',
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $autoSealingTimeout="1 DAY";
    
    
    public function itemGet( int $reaction_id ){
        $this->permitWhere('r');
        $this->where('reaction_id',$reaction_id);
        return $this->get()->getRow();
    }
    
    public function itemByTagGet($tagQuery){
        $this->permitWhere('r');
        $ReactionTagModel=model('ReactionTagModel');
        $where=$ReactionTagModel->tagWhereGet($tagQuery);
        $this->join('reaction_tag_list','member_id=reaction_id');
        $this->where($where);
        $this->select('reaction_id,reaction_is_like,reaction_is_dislike,reaction_comment,sealed_at');
        return $this->get()->getRow();
    }
    private function itemTagsExpand($tagQuery){
        $expandedTagQuery='';
        $ReactionTagModel=model('ReactionTagModel');
        $tag_list=$ReactionTagModel->listParse( $tagQuery );
        foreach( $tag_list as $tag ){
            $parsed_tag=$ReactionTagModel->tagParse($tag);
            // if($parsed_tag->tag_name=='store'){
            //     $expandedTagQuery.=$this->expandTagFromProduct(null,null,$parsed_tag->tag_id);
            // }
            if($parsed_tag->tag_name=='product'){
                $expandedTagQuery.=$this->expandTagFromProduct($parsed_tag->tag_id);
            }
            if($parsed_tag->tag_name=='entry'){
                $expandedTagQuery.=$this->expandTagFromProduct(null, $parsed_tag->tag_id);
            }
        }
        return $expandedTagQuery;
    }

    private function expandTagFromProduct( $product_id=null, $entry_id=null, $store_id=null ){
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
        //$EntryModel->where('link_id IS NULL');
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

    public function itemSave( object $reaction, string $tagQuery ){
        $existing_reaction=$this->itemByTagGet($tagQuery);
        if($existing_reaction){
            $reaction->reaction_id=$existing_reaction->reaction_id;
            return $this->itemUpdate($reaction);
        }
        return $this->itemCreate($reaction,$tagQuery);
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
        $reaction->sealed_at= date('Y-m-d H:i:s', strtotime("+{$this->autoSealingTimeout}"));
        $this->transStart();
            $reaction_id=$this->insert($reaction,true);
            $tags_created=$ReactionTagModel->listCreate($reaction_id,$tagQuery);
            if($tags_created!=='ok'){
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
        if( isset($reaction->reaction_comment) && $reaction->reaction_comment=='' ){
            $reaction->reaction_comment=null;
        }

        $this->where('sealed_at>NOW()');
        $this->update($reaction->reaction_id,$reaction);
        $result=$this->db->affectedRows()?'ok':'idle';

        $this->where('reaction_is_like',0);
        $this->where('reaction_is_dislike',0);
        $this->where('reaction_comment',null);
        $this->itemDelete($reaction->reaction_id);
        
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete($reaction_id){
        $this->permitWhere('w');
        $this->delete($reaction_id,true);//should we permanently delete???
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listGet( array $filter ){
        if(!$filter['tagQuery']){
            return 'notags';
        }
        $this->filterMake($filter);

        $user_id=session()->get('user_id');
        $ReactionTagModel=model('ReactionTagModel');
        $where=$ReactionTagModel->tagWhereGet($filter['tagQuery']);
        $this->join('reaction_tag_list','member_id=reaction_id');
        $this->join('user_list','reaction_list.owner_id=user_id');
        $this->orderBy("`reaction_list`.`owner_id`='{$user_id}'","DESC",false);
        $this->orderBy('`reaction_list`.`created_at`', 'DESC');
        $this->where($where);
        if($filter['commentsOnly']??0){
            $this->where("reaction_comment IS NOT NULL");
        }
        $this->select("(SELECT 
                image_hash
            FROM
                image_list
                    JOIN
                reaction_tag_list 
                    ON image_holder = 'product' AND tag_name='product' AND image_holder_id = tag_id
            WHERE
                member_id=reaction_id) image_hash");
        $this->select('reaction_comment,reaction_is_like,user_name');

        $result=$this->get()->getResult();
        return $result;
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
    
    public function summaryGet($tagQuery){
        $ReactionTagModel=model('ReactionTagModel');
        $where=$ReactionTagModel->tagWhereGet($tagQuery);
        $this->where($where);
        $user_id=session()->get('user_id');

        $select="
            SUM(reaction_is_like) sum_is_like,
            SUM(reaction_comment IS NOT NULL) sum_comment,
            MAX(IF(`owner_id`='{$user_id}',reaction_is_like,0)) reaction_is_like,
            MAX(IF(`owner_id`='{$user_id}',reaction_is_dislike,0)) reaction_is_dislike
        ";
        $this->select($select);
        $this->join('reaction_tag_list','member_id=reaction_id');
        $result=$this->get()->getRow();


        if($result){
            $this->where('reaction_comment IS NOT NULL');
            $this->where($where);
            $this->select('reaction_comment');
            $this->join('reaction_tag_list','member_id=reaction_id');
            $this->orderBy('created_at', 'DESC');
            $this->limit(1);
            $result->last_comment=$this->get()->getRow('reaction_comment');
        }
        
        return $result;
    }

    public function entryListGet($filter){
        $owner_id=session()->get('user_id');
        $supplier_finish_group_id=8;
        /**
         * supplier finish suitable for marketplace and delivery
         */

        $EntryModel=model('EntryModel');
        $EntryModel->join('order_group_member_list','order_entry_list.order_id=order_group_member_list.member_id');
        $EntryModel->join('product_list','product_id');
        $EntryModel->join('image_list','image_holder_id=product_id AND image_holder="product" AND is_main=1','left');
        $EntryModel->join('reaction_tag_list','tag_name="entry" AND tag_id=entry_id','left');
        $EntryModel->join('reaction_list','reaction_id=reaction_tag_list.member_id','left');
     
        $EntryModel->where('order_entry_list.owner_id',$owner_id);
        $EntryModel->where('group_id',$supplier_finish_group_id);
        $EntryModel->orderBy('order_entry_list.updated_at DESC');
        if($filter['target_type']=='product'){
            $select="
            product_id AS target_id,
            entry_id,
            entry_text,
            order_entry_list.updated_at,
            image_hash,
            IFNULL(sealed_at<NOW(),0) is_sealed,
            reaction_id,
            reaction_is_like,
            reaction_is_dislike,
            reaction_comment";
            $EntryModel->where('product_id',$filter['target_id']);
            $EntryModel->select($select);
        }
        if($filter['target_type']=='store'){
            $select="
            product_id AS target_id,
            entry_id,
            entry_text,
            order_entry_list.updated_at,
            image_hash,
            IFNULL(sealed_at<NOW(),0) is_sealed,
            reaction_id,
            reaction_is_like,
            reaction_is_dislike,
            reaction_comment";
            $EntryModel->where('store_id',$filter['target_id']);
            $EntryModel->select($select);
        }
        $filter['name_query_fields']="entry_text";
        $EntryModel->filterMake($filter);
        $result=$EntryModel->get()->getResult();
        return $result;
    }
}