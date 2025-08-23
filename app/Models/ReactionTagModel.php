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

    
}