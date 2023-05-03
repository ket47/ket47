<?php
namespace App\Models;
use CodeIgniter\Model;

class ProductModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = '`product_list`';
    protected $primaryKey = 'product_id';
    protected $allowedFields = [
        'store_id',
        'product_parent_id',
        'product_option',
        'product_external_id',
        'product_code',
        'product_barcode',
        'product_name',
        'product_quantity',
        'product_quantity_min',
        'product_quantity_expire_at',
        'product_quantity_reserved',
        'product_description',
        'product_weight',
        'product_unit',
        'product_price',
        'product_net_price',
        'product_promo_price',
        'product_promo_start',
        'product_promo_finish',
        'is_counted',
        'deleted_at'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_id'         => 'required|numeric',
        'product_name'     => 'min_length[5]',
        'product_price'    => 'numeric',
        //'product_promo_price'    => 'numeric',
    ];
    public $itemCreateAsDisabled=false;
    public $itemImageCreateAsDisabled=false;
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $product_id, $mode='all' ){
        $this->permitWhere('r');
        $this->where('product_list.`product_id`',$product_id);
        $this->select("ROUND(IF(IFNULL(product_list.`product_promo_price`,0)>0 AND product_list.`product_price`>product_list.`product_promo_price` AND product_list.`product_promo_start` < NOW() AND product_list.`product_promo_finish` > NOW(),product_list.`product_promo_price`,product_list.`product_price`)) product_final_price");

        $this->join('product_list parent_pl','parent_pl.product_id=product_list.product_parent_id','left');
        $this->select("
            product_list.product_id,
            product_list.store_id,
            product_list.product_parent_id,
            product_list.product_external_id,
            product_list.product_code,
            product_list.product_barcode,
            product_list.product_option,
            product_list.product_quantity,
            product_list.product_quantity_min,
            product_list.product_quantity_expire_at,
            product_list.product_quantity_reserved,
            product_list.product_weight,
            product_list.product_price,
            product_list.product_net_price,
            product_list.product_promo_price,
            product_list.product_promo_start,
            product_list.product_promo_finish,
            product_list.is_counted,
            product_list.is_disabled,
            product_list.deleted_at,
            product_list.validity,

            COALESCE(parent_pl.product_name,product_list.product_name) product_name,
            COALESCE(parent_pl.product_description,product_list.product_description) product_description,
            COALESCE(parent_pl.product_unit,product_list.product_unit) product_unit
        ");
        $product = $this->get()->getRow();
        if( !$product ){
            return 'notfound';
        }
        if($mode=='basic'){
            return $product;
        }
        $product_parent_id=($product->product_parent_id?$product->product_parent_id:$product->product_id);
        $ProductGroupMemberModel=model('ProductGroupMemberModel');
        //$ProductGroupMemberModel->tableSet('product_group_member_list');
        $ImageModel=model('ImageModel');

        $product->is_writable=$this->permit($product_id,'w');
        $product->member_of_groups=$ProductGroupMemberModel->memberOfGroupsGet($product_parent_id);
        $filter=[
            'image_holder'=>'product',
            'image_holder_id'=>$product_parent_id,
            'is_disabled'=>$product->is_writable,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>5
        ];
        $product->images=$ImageModel->listGet($filter);
        if($product->product_parent_id??null){
            $product->options=$this->itemOptionGet( $product->product_parent_id, 'active_only' );
        }
        $product->store=$this->itemStoreMetaGet($product->store_id);




        return $product;
    }
    
    private function itemStoreMetaGet($store_id){
        $StoreModel=model('StoreModel');
        $ImageModel=model('ImageModel');

        $StoreModel->select('store_id,store_name');
        $StoreModel->where('store_id',$store_id);
        $store=$StoreModel->get()->getRow();

        $store->avatar=$ImageModel->listGet([
            'image_holder'=>'store_avatar',
            'image_holder_id'=>$store_id
        ]);
        return $store;
    }

    public function itemCreate( $product ){
        if( !$product ){
            return 'error_empty';
        }
        if( is_array($product) ){
            $product=(object)$product;
        }
        $store_id=$product->store_id;
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($store_id,'basic');
        if( $store=='notfound' ){
            return 'nostore';
        }
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        if( $this->itemCreateAsDisabled ){
            $product->is_disabled=1;
        }
        $product->owner_id=session()->get('user_id');
        $product->owner_ally_ids=$store->owner_ally_ids;
        $this->allowedFields[]='is_disabled';//if run many times allowedFields will contain duplicated values
        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='owner_ally_ids';
        if( isset($product->product_quantity) && !isset($product->product_quantity_expire_at) ){
            $expiration_timeout=8;//8 hours
            $product->product_quantity_expire_at=date("Y-m-d H:i:s",time()+60*60*$expiration_timeout);
        }
        $product_id=$this->insert($product,true);

        if($product->product_image_url??null){
            $this->itemCreateImage($product_id,$product->product_image_url);
        }
        if($product->product_category_name??null){
            $this->itemCreateCategory($product_id,$product->product_category_name);
        }
        return $product_id;
    }

    private function itemCreateImage($product_id,$source){
        $image_data=[
            'image_holder'=>'product',
            'image_holder_id'=>$product_id
        ];
        $image_hash=$this->imageCreate($image_data);

        if( $image_hash && $image_hash!=='limit_exeeded' ){
            jobCreate([
                'task_name'=>"Image download",
                'task_programm'=>[
                        ['library'=>'\App\Libraries\Utils','method'=>'fileDownloadImage','arguments'=>[$source,$image_hash]]
                    ]
            ]);
        }
    }

    private function itemCreateCategory($product_id,$product_category_name){
        $ProductGroupModel=model('ProductGroupModel');
        $ProductGroupModel->where('group_name',$product_category_name);
        $ProductGroupModel->where('group_parent_id<>0');
        $group_id=$ProductGroupModel->get()->getRow('group_id');
        if($group_id){
            $this->itemUpdateGroup($product_id,$group_id,true);
        }
    }

    public function itemUpdate( $product ){
        if( !$product || !isset($product->product_id) ){
            return 'error_empty';
        }
        $this->permitWhere('w');
        if( isset($product->product_quantity) && !isset($product->product_quantity_expire_at) ){
            $expiration_timeout=8;//8 hours
            $product->product_quantity_expire_at=date("Y-m-d H:i:s",time()+60*60*$expiration_timeout);
        }
        $this->update($product->product_id,$product);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUpdateGroup($product_id,$group_id,$is_joined){
        if( !$this->permit($product_id,'w') ){
            return 'forbidden';
        }
        $GroupModel=model('ProductGroupModel');
        $target_group=$GroupModel->itemGet($group_id);
        if( !$target_group ){
            return 'not_found';
        }
        $ProductGroupMemberModel=model('ProductGroupMemberModel');
        //$ProductGroupMemberModel->tableSet('product_group_member_list');
        $leave_other_groups=true;
        $ok=$ProductGroupMemberModel->itemUpdate( $product_id, $group_id, $is_joined, $leave_other_groups );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    
    public function itemDelete( $product_id ){
        $ImageModel=model('ImageModel');
        $ImageModel->permitWhere('w');
        $ImageModel->listDelete('product',[$product_id]);
        $this->delete($product_id);
        $result=$this->db->affectedRows()?'ok':'idle';

        $this->itemOptionChildrenDelete($product_id);
        return $result;
    }
    
    public function itemUnDelete( $product_id ){
        $ImageModel=model('ImageModel');
        $ImageModel->permitWhere('w');
        $ImageModel->listUnDelete('product',[$product_id]);
        $this->update($product_id,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDisable( $product_id, $is_disabled ){
        if( !$this->permit($product_id,'w','disabled') ){
            return 'forbidden';
        }
        $this->allowedFields[]='is_disabled';
        $this->update(['product_id'=>$product_id],['is_disabled'=>$is_disabled?1:0]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemOptionGet( $product_parent_id, $mode=null ){
        if(!$product_parent_id){
            return null;
        }
        $this->permitWhere('r');
        $this->where('product_parent_id',$product_parent_id);

        $this->select("ROUND(IF(IFNULL(product_promo_price,0)>0 AND `product_price`>`product_promo_price` AND product_promo_start<NOW() AND product_promo_finish>NOW(),product_promo_price,product_price)) product_final_price");
        $this->select('product_id,product_code,product_name,product_option,image_hash,product_list.deleted_at,(product_id=product_parent_id) is_parent');
        $this->join('image_list',"image_holder='product' AND image_holder_id=product_id AND is_main=1",'left');
        $this->orderBy('is_parent','DESC');
        if($mode=='active_only'){
            $this->where("product_list.deleted_at IS NULL AND product_list.is_disabled=0");
        }
        // $this->where("product_option IS NOT NULL AND product_option <>'' OR product_id=product_parent_id");        
        return $this->get()->getResult();
    }

    public function itemOptionSave( $product_id, $product_parent_id ){
        $this->permitWhere('w');
        $this->where('product_parent_id',$product_parent_id);
        $this->orWhere('product_id',$product_id);
        $this->update(null,['product_parent_id'=>$product_parent_id]);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemOptionDelete( $product_id ){
        $this->where('product_id',$product_id);
        $product_parent_id=$this->select('product_parent_id')->get()->getRow('product_parent_id');

        if( !$product_parent_id ){
            return 'ok';
        }
        if($product_id!==$product_parent_id){//delete only option
            return $this->itemDelete($product_id);
        }
        //parent product so delete all children
        $this->permitWhere('w');
        $this->where('product_id',$product_id);
        $this->update(null,['product_parent_id'=>null]);

        return $this->itemOptionChildrenDelete($product_parent_id);
    }

    private function itemOptionChildrenDelete($product_parent_id){
        $this->permitWhere('w');
        $this->where('product_parent_id',$product_parent_id);
        $option_ids=$this->select("GROUP_CONCAT(product_id) children_ids")->get()->getRow('children_ids');
        $option_id_array=explode(',',$option_ids);
        return $this->listDelete($option_id_array);
    }
    
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter=null ){
        if( $filter['group_id']??0 ){
            $this->where('group_id',$filter['group_id']);
        }
        if( $filter['store_id']??0 ){
            $this->where('store_id',$filter['store_id']);
            if( model('StoreModel')->permit($filter['store_id'],'w') ){
                $filter['is_disabled']=1;
                $filter['is_deleted']=1;
            } else {
                $filter['is_disabled']=0;
                $filter['is_deleted']=0;
                $this->where('validity>','50');
            }
        }
        $this->filterMake( $filter );
        $this->permitWhere('r');
        $this->orderBy("{$this->table}.updated_at",'DESC');
        $this->orderBy("product_final_price<>product_price",'DESC',false);
        $this->join('product_group_member_list','member_id=product_id','left');
        $this->join('image_list',"image_holder='product' AND image_holder_id=product_id AND is_main=1",'left');
        $this->select("product_list.*,image_hash,group_id");
        $this->select("ROUND(IF(IFNULL(product_promo_price,0)>0 AND `product_price`>`product_promo_price` AND product_promo_start<NOW() AND product_promo_finish>NOW(),product_promo_price,product_price)) product_final_price");
        //$this->select("IF(`product_parent_id`=`product_id`,(SELECT GROUP_CONCAT(DISTINCT product_option SEPARATOR '~|~') FROM product_list ppl WHERE ppl.product_parent_id=product_id AND is_disabled=0 AND deleted_at IS NULL),NULL) product_options");
        $this->where("(`product_parent_id` IS NULL OR `product_parent_id`=`product_id`)");
        $product_list= $this->get()->getResult();
        foreach($product_list as $product){
            if($product->product_parent_id==$product->product_id){
                $this->select("ROUND(IF(IFNULL(product_promo_price,0)>0 AND `product_price`>`product_promo_price` AND product_promo_start<NOW() AND product_promo_finish>NOW(),product_promo_price,product_price)) product_final_price");
                $this->select("product_id,product_option");
                $this->where("is_disabled","0");
                $this->where("deleted_at IS NULL");
                $this->where("product_option IS NOT NULL");
                $this->where("product_option <>''");
                $this->where("product_parent_id",$product->product_id);
                $product->options=$this->get()->getResult();
            }
        }
        return $product_list;
    }
    
    public function listCreate( $store_id, $productList ){
        foreach($productList as $product){
            $product->store_id=$store_id;
            $this->itemCreate($product);
        }
    }

    public function listUpdate( $store_id, $productList ){
        foreach($productList as $product){
            $product->store_id=$store_id;
            $this->itemUpdate($product);
        }
    }
    
    public function listUpdateValidity(int $store_id=null,int $product_id=null){
        if( !$store_id && !$product_id ){
            return false;
        }
        $sql="
            UPDATE
                product_list pl
                    LEFT JOIN
                image_list ON image_holder='product' AND image_holder_id=pl.product_id
            SET
                validity=
                1
                *IF(product_price>0,1,0)
                *IF(CHAR_LENGTH(product_name)>=5,1,0)
                *IF(image_id IS NOT NULL,1,0)
                *(
                50
                +IF(CHAR_LENGTH(product_description)>=30,10,0)
                +IF(CHAR_LENGTH(product_code)>=3,10,0)
                +IF(CHAR_LENGTH(product_unit)>=1,10,0)
                +IF(CHAR_LENGTH(product_barcode)=13,10,0)
                +IF(product_weight>0,10,0)
                )
            WHERE
            ";
        if($store_id){
            $sql.=" store_id='$store_id'";
        }
        if($product_id){
            $sql.=" product_id='$product_id'";
        }
        $this->query($sql);
    }
    
    public function listDelete( array $product_ids ){
        $this->permitWhere('w');
        $this->delete($product_ids);
        if( $this->db->affectedRows()>0 ){
            $ImageModel=model('ImageModel');
            $ImageModel->listDelete('product', $product_ids);
            return 'ok';
        }
        return 'idle'; 
    }
    
    public function listDeleteChildren( $store_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $this->where('deleted_at IS NULL AND is_disabled=0');
        $this->where('store_id',$store_id);
        $this->select('GROUP_CONCAT(product_id) product_ids');
        $trashed_product_ids_string=$this->get()->getRow('product_ids');
        $trashed_product_ids=explode(',',$trashed_product_ids_string);
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('product', $trashed_product_ids);

        $this->whereIn('product_id',$trashed_product_ids);
        $this->delete();
    }
    
    public function listUnDeleteChildren( $store_id ){
        if( !sudo() ){
            return 'forbidden';
        }
        $olderStamp= new \CodeIgniter\I18n\Time("-1 days");
        $this->where('deleted_at>',$olderStamp);
        $this->where('store_id',$store_id);
        $this->select('GROUP_CONCAT(product_id) product_ids');
        $untrashed_product_ids_string=$this->get()->getRow('product_ids');
        $untrashed_product_ids=explode(',',$untrashed_product_ids_string);
        
        $ImageModel=model('ImageModel');
        $ImageModel->listUnDelete('product', $untrashed_product_ids);
        
        $this->update($untrashed_product_ids,['deleted_at'=>NULL]);
    }
    
    public function listPurge( $olderThan=1 ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $data ){
        if( $this->itemImageCreateAsDisabled ){
            $data['is_disabled']=1;
        }
        $data['owner_id']=session()->get('user_id');
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemCreate($data);
        }
        return 0;
    }

    public function imageUpdate( $data ){
        if( $this->permit($data['image_holder_id'], 'w') ){
            $ImageModel=model('ImageModel');
            return $ImageModel->itemUpdate($data);
        }
        return 0;
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
        
        $product_id=$image->image_holder_id;
        if( !$this->permit($product_id,'w') ){
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
        
        $product_id=$image->image_holder_id;
        if( !$this->permit($product_id,'w') ){
            return 'forbidden';
        }
        $ok=$ImageModel->itemUpdateOrder( $image_id, $dir );
        if( $ok ){
            return 'ok';
        }
        return 'error';
    }
    /////////////////////////////////////////////////////
    //UTILS
    /////////////////////////////////////////////////////
    /**
     * This function returning all categories that have member products in them.
     * Format is tree.
     * Later caching must be done!
     */
    public function groupTreeGet($filter,$depth='all'){
        if($filter['store_id']??0){
            $this->where('store_id',$filter['store_id']);
            $StoreModel=model('StoreModel');
            if($StoreModel->permit($filter['store_id'],'w')){
                $filter['is_disabled']=1;
                $filter['is_deleted']=1;
            }
        }
        $this->filterMake( $filter );
        $this->select('pgl.group_id,pgl.group_parent_id,pgl.group_name,pgl.group_path,image_hash, MAX(product_price) mpprice');
        $this->join('product_group_member_list pgml','member_id=product_id');
        $this->join('product_group_list pgl','pgml.group_id=pgl.group_id');
        $this->join('image_list il',"image_holder='product_group_list' AND image_holder_id=pgl.group_id AND is_main=1",'left');
        $this->groupBy('pgl.group_id,image_id');
        $this->orderBy("mpprice",'DESC',false);
        $children_groups=$this->get()->getResult();
        $parent_groups=[];
        $order=0;
        
        $ImageModel=model("ImageModel");
        foreach($children_groups as $child){
            if( !isset($parent_groups[$child->group_parent_id]) ){
                $ImageModel->where('image_holder','product_group_list');
                $ImageModel->where('image_holder_id',$child->group_parent_id);
                $parent_groups[$child->group_parent_id]=[
                    'group_id'=>$child->group_parent_id,
                    'group_name'=>explode('/',$child->group_path)[1],
                    'image_hash'=>$ImageModel->get()->getRow('image_hash'),
                    'order'=>$order++
                ];
            }
            if( $depth=='all' ){
                $parent_groups[$child->group_parent_id]['children'][$child->group_id]=$child;
            }
        }
        return $parent_groups;
    }
}