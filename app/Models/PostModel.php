<?php
namespace App\Models;
class PostModel extends SecureModel{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'post_list';
    protected $primaryKey = 'post_id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'post_title',
        'post_content',
        'post_type',
        'post_route',
        'started_at',
        'finished_at',
        'updated_by'
        ];

    protected $useSoftDeletes = true;
    
    protected function initialize(){
        $this->query("SET character_set_results = utf8mb4, character_set_client = utf8mb4, character_set_connection = utf8mb4, character_set_database = utf8mb4, character_set_server = utf8mb4");
    }
    public function fieldUpdateAllow($field){
        $this->allowedFields[]=$field;
    }

    public function itemGet( int $post_id ){
        $post=$this->find($post_id);
        if( !$post ){
            return 'notfound';
        }
        $ImageModel=model('ImageModel');
        $post->is_writable=$this->permit($post_id,'w');
        $filter=[
            'image_holder'=>'post',
            'image_holder_id'=>$post_id,
            'is_disabled'=>$post->is_writable,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>5
        ];
        $post->images=$ImageModel->listGet($filter);
        if( $post->post_holder=='store' ){
            $StoreModel=model('StoreModel');
            $StoreModel->where('store_id',$post->post_holder_id);
            $post->holder=$StoreModel->select('store_id,store_name')->get()->getRow();
        }
        return $post;
    }
    
    public function itemCreate( object $post ){
        if( !$post ){
            return 'empty';
        }
        $post->owner_id=session()->get('user_id');
        $this->fieldUpdateAllow('owner_id');
        $post->started_at=date("Y-m-d H:i:s");
        $post->finished_at=date("Y-m-d H:i:s",time()+7*24*60*60);//1 week
        $post->updated_by=session()->get('user_id');
        return $this->insert($post,true);
    }
    
    public function itemUpdate( $post ){
        if( !$post || !isset($post->post_id) ){
            return 'noid';
        }
        if( sudo() ){
            $this->fieldUpdateAllow('is_promoted');
        }
        if(sudo() || stodo()){
            if(isset($post->post_holder) && $post->post_holder == 'store'){
                $StoreModel=model('StoreModel');
                $store=$StoreModel->itemGet($post->post_holder_id,'basic');
                $post->owner_ally_ids=$store->owner_ally_ids;
                $this->fieldUpdateAllow('owner_ally_ids');
                $this->fieldUpdateAllow('post_holder');
                $this->fieldUpdateAllow('post_holder_id');
            }
            $this->fieldUpdateAllow('is_disabled');
        } 
        $post->owner_id=session()->get('user_id');
        $this->fieldUpdateAllow('owner_id');
        $post->updated_by=session()->get('user_id');
        $this->update($post->post_id,$post);
        
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemDisable( int $post_id, $is_disabled ){
        if( !$this->permit($post_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['post_id'=>$post_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( $post_id ){
        if( !$post_id ){
            return 'noid';
        }
        $ImageModel=model('ImageModel');
        $ImageModel->permitWhere('w');
        $ImageModel->listDelete('post',[$post_id]);

        $this->where('post_id',$post_id)->delete();
        $result=$this->db->affectedRows()?'ok':'idle';
        return $result;
    }
    
    public function itemUnDelete( $post_id ){
        if( !$post_id ){
            return 'noid';
        }
        $ImageModel=model('ImageModel');
        $ImageModel->permitWhere('w');
        $ImageModel->listUnDelete('post',[$post_id]);

        $this->allowedFields[]='deleted_at';
        $this->update($post_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function listPurge( $olderThan=1 ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }

    public function listGet( array $filter ){
        if( !sudo() ){
            $filter['is_disabled']=0;
            $filter['is_deleted']=0;
            $filter['is_actual']=1;
        }
        if( $filter['is_actual'] ){
            $this->where("NOW()>started_at");
            $this->where("NOW()<finished_at");
        }
        if( isset($filter['is_promoted']) ){
            $this->where("is_promoted",$filter['is_promoted']);
        }
        if( $filter['post_type']??null ){
            $this->whereIn('post_type',explode(',',$filter['post_type']));
        }
        if( isset($filter['post_holder']) ){
            $this->where("post_holder",$filter['post_holder']);
        }
        if( isset($filter['post_holder_id']) ){
            $this->where("post_holder_id",$filter['post_holder_id']);
        }
        $this->filterMake($filter);
        $this->select('
            post_id,
            post_title,
            post_route,
            post_content,
            post_type,
            post_holder,
            post_holder_id,
            image_hash,
            post_list.updated_at
        ');
        $this->join('image_list',"image_holder='post' AND image_holder_id=post_id AND is_main=1",'left');
        $this->groupBy('post_id')->orderBy('post_title');
        $posts = $this->findAll($filter['limit']??30,$filter['offset']??0);
      
        foreach($posts as &$post){
            $post->is_writable=$this->permit($post->post_id,'w');
        }
        return $posts;
    }
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $image ){
        $limit=1;
        $image['is_disabled']=0;
        $image['owner_id']=session()->get('user_id');
        $post_id=$image['image_holder_id'];

        if( !$this->permit($post_id,'w') ){
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');
        return $ImageModel->itemCreate($image,$limit);
    }

    public function imageUpdate( $image ){
        $post_id=$image['image_holder_id'];
        if( !$this->permit($post_id,'w') ){
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');
        return $ImageModel->itemUpdate($image);
    }
    
    public function imageDisable( $image_id, $is_disabled ){
        if( !sudo() ){
            return 'forbidden';
        }
        $ImageModel=model('ImageModel');
        $ok=$ImageModel->itemDisable( $image_id, $is_disabled );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    
    public function imageDelete( $image_id ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $post_id=$image->image_holder_id;
        if( !$this->permit($post_id,'w') ){
            return 'forbidden';
        }
        $ImageModel->itemDelete( $image_id );
        $ok=$ImageModel->itemPurge( $image_id );
        if( $ok ){
            return 'ok';
        }
        return 'idle';
    }
    
    public function imageOrder( $image_id, $dir ){
        $ImageModel=model('ImageModel');
        $image=$ImageModel->itemGet( $image_id );
        
        $post_id=$image->image_holder_id;
        if( !$this->permit($post_id,'w') ){
            return 'forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
}