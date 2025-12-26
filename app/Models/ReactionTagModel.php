<?php
namespace App\Models;
use CodeIgniter\Model;

class ReactionTagModel extends TagLayer{

    protected $table      = 'reaction_tag_list';



    public function customerRatingGet( int $user_id ){
        $this->where('tag_name','customer');
        $this->where('tag_option','rating');
        $this->where('tag_id',$user_id);
        $this->select("COUNT(*) customer_heart_count");
        return $this->get()->getRow('customer_heart_count');
    }

    public function postReactionsGet( int $user_id, int $post_id ){
        $this->where('tag_name','post');
        $this->where('tag_id',$post_id);
        $this->where('tag_option',$user_id);
        $this->select("GROUP_CONCAT(DISTINCT tag_type) reacted_tags");
        return $this->get()->getRow('reacted_tags');
    }
    
}