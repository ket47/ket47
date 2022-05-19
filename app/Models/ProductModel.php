<?php
namespace App\Models;
use CodeIgniter\Model;

class ProductModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'product_list';
    protected $primaryKey = 'product_id';
    protected $allowedFields = [
        'store_id',
        'product_code',
        'product_name',
        'product_quantity',
        'product_quantity_min',
        'product_description',
        'product_weight',
        'product_unit',
        'product_price',
        'product_promo_price',
        'product_promo_start',
        'product_promo_finish',
        'is_counted',
        'validity',
        'deleted_at'
        ];
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $validationRules    = [
        'store_id'         => 'required|numeric',
        'product_name'     => 'required|min_length[5]',
        'product_price'    => 'required|numeric',
        //'product_promo_price'    => 'numeric',
    ];
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $product_id, $mode='all' ){
        $this->permitWhere('r');
        $this->where('product_id',$product_id);
        $this->select("*");
        $this->select("IF(IFNULL(`product_promo_price`,0)>0 AND `product_price`>`product_promo_price` AND `product_promo_start` < NOW() AND `product_promo_finish` > NOW(),`product_promo_price`,`product_price`) product_final_price");

        $product = $this->get()->getRow();
        if( !$product ){
            return 'notfound';
        }
        if($mode=='basic'){
            return $product;
        }
        $ProductGroupMemberModel=model('ProductGroupMemberModel');
        $ProductGroupMemberModel->tableSet('product_group_member_list');
        $ImageModel=model('ImageModel');
        $product->is_writable=$this->permit($product_id,'w');
        $product->member_of_groups=$ProductGroupMemberModel->memberOfGroupsGet($product->product_id);
        $filter=[
            'image_holder'=>'product',
            'image_holder_id'=>$product->product_id,
            'is_disabled'=>$product->is_writable,
            'is_deleted'=>0,
            'is_active'=>1,
            'limit'=>5
        ];
        $product->images=$ImageModel->listGet($filter);
        return $product;
    }
    
    public function itemCreate( $product ){
        if( !$product ){
            return 'error_empty';
        }
        $store_id=$product['store_id'];
        $StoreModel=model('StoreModel');
        $store=$StoreModel->itemGet($store_id,'basic');
        if( $store=='notfound' ){
            return 'nostore';
        }
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        $product['owner_id']=session()->get('user_id');
        $product['owner_ally_ids']=$store->owner_ally_ids;
        $this->allowedFields[]='owner_id';
        $this->allowedFields[]='owner_ally_ids';
        return $this->insert($product);
    }
    
    public function itemUpdate( $product ){
        if( !$product || !isset($product->product_id) ){
            return 'error_empty';
        }
        $this->permitWhere('w');
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
        $ProductGroupMemberModel->tableSet('product_group_member_list');
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
        $ImageModel->listDelete('product',$product_id);
        $this->delete($product_id);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUnDelete( $product_id ){
        $ImageModel=model('ImageModel');
        $ImageModel->permitWhere('w');
        $ImageModel->listUnDelete('product',$product_id);
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
    
    
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet( $filter=null ){
        $this->filterMake( $filter );
        if( $filter['group_id']??0 ){
            $this->where('group_id',$filter['group_id']);
        }
        if( $filter['store_id']??0 ){
            $this->where('store_id',$filter['store_id']);
        }
        $this->permitWhere('r');
        $this->orderBy("{$this->table}.updated_at",'DESC');
        $this->join('product_group_member_list','member_id=product_id','left');
        $this->join('image_list',"image_holder='product' AND image_holder_id=product_id AND is_main=1",'left');
        $this->select("{$this->table}.*,image_hash,group_id");
        $this->select("IF(IFNULL(product_promo_price,0)>0 AND `product_price`>`product_promo_price` AND product_promo_start<NOW() AND product_promo_finish>NOW(),product_promo_price,product_price) product_final_price");
        $product_list= $this->get()->getResult();
        return $product_list;
    }
    
    public function listCreate( $store_id, $colconfig ){
        //ANALYSE MADE PREVIOUS SO NO NEED TO DOUBLE CHECK
        $StoreModel=model('StoreModel');
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        $ownerFilter=$this->permitWhereGet('w','item');
        $target_col_list="store_id,owner_id,owner_ally_ids,is_disabled";
        $src_col_list="$store_id,owner_id,owner_ally_ids,1";
        $delimeter=',';

        $skip_cols=['product_action_price','product_action_start','product_action_finish','product_categories'];
        foreach($colconfig as $target=>$src){
            if(in_array($target, $skip_cols)){
                continue;
            }
            $src_col_list.=$delimeter.$src;
            $target_col_list.=$delimeter.$target;
        }
        $sql="
            INSERT INTO product_list ($target_col_list) SELECT
                $src_col_list
            FROM
                imported_list il
            WHERE
                il.holder='store' 
                AND il.holder_id='$store_id'
                AND il.action='add'
                AND $ownerFilter
            ";
        $this->query($sql);
        if( $this->db->affectedRows()>0 ){
            $clear_imported_sql="
                UPDATE
                    imported_list il
                SET
                    `action`='done'
                WHERE
                    il.holder='store' 
                    AND il.holder_id='$store_id'
                    AND il.action='add'
                    AND $ownerFilter
                ";
            $this->query($clear_imported_sql);
            return 'ok';
        }
        return 'idle';
    }
    
    public function listUpdate( $holder,$store_id,$colconfig ){
        //ANALYSE MADE PREVIOUS SO NO NEED TO DOUBLE CHECK
        $StoreModel=model('StoreModel');
        $permission_granted=$StoreModel->permit($store_id,'w');
        if( !$permission_granted ){
            return 'forbidden';
        }
        $ownerFilter=$this->permitWhereGet('w','item');
        $set="pl.store_id=$store_id,pl.deleted_at=NULL";
        $delimeter=',';

        $skip_cols=['product_action_price','product_action_start','product_action_finish','product_categories'];
        foreach($colconfig as $target=>$src){
            if(in_array($target, $skip_cols)){
                continue;
            }
            $set.="$delimeter pl.$target=il.$src";
        }
        $sql="
            UPDATE
                product_list pl
                    JOIN 
                (SELECT * FROM
                    imported_list il 
                WHERE
                    il.holder='store' 
                    AND il.holder_id='$store_id'
                    AND il.action='update'
                    AND $ownerFilter) il ON pl.product_id=il.target_id
            SET
                $set
            ";
        $this->query($sql);
        $clear_imported_sql="
            UPDATE
                imported_list il
            SET
                `action`='done'
            WHERE
                il.holder='store' 
                AND il.holder_id='$store_id'
                AND il.action='update'
                AND $ownerFilter
            ";
        $this->query($clear_imported_sql);
        if( $this->db->affectedRows()>0 ){
            return 'ok';
        }
        return 'idle';
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
        $StoreModel=model('StoreModel');
        if( !$StoreModel->permit($store_id,'w') ){
            return 'forbidden';
        }
        $this->listDeleteChildrenDirectly($store_id);
        
        $this->where('deleted_at IS NULL AND is_disabled=0');
        $this->where('store_id',$store_id);
        $this->select('GROUP_CONCAT(product_id) product_ids');
        $product_ids=$this->get()->getRow('product_ids');
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDelete('product', $product_ids);
        $this->delete($product_ids);
    }
    
    public function listUnDeleteChildren( $store_id ){
        $StoreModel=model('StoreModel');
        if( !$StoreModel->permit($store_id,'w') ){
            return 'forbidden';
        }
        $olderStamp= new \CodeIgniter\I18n\Time("-".APP_TRASHED_DAYS." days");
        $this->where('deleted_at>',$olderStamp);
        $this->where('store_id',$store_id);
        $this->select('GROUP_CONCAT(product_id) product_ids');
        $product_ids=$this->get()->getRow('product_ids');
        
        $ImageModel=model('ImageModel');
        $ImageModel->listUnDelete('product', $product_ids);
        
        $this->update($product_ids,['deleted_at'=>NULL]);
    }
    
    private function listDeleteChildrenDirectly($store_id){
        /*
         * marking to purge directly items that are already deleted or disabled
         */
        $this->where('deleted_at IS NOT NULL OR is_disabled=1');
        $this->where('store_id',$store_id);
        $this->select('GROUP_CONCAT(product_id) product_ids');
        $trashed_product_ids=$this->get()->getRow('product_ids');
        $this->update($trashed_product_ids,['deleted_at'=>'2000-01-01 00:00:00']);
        
        $ImageModel=model('ImageModel');
        $ImageModel->listDeleteDirectly('product', $trashed_product_ids);
    }
    
    public function listPurge( $olderThan=APP_TRASHED_DAYS ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan hours");
        $this->where('deleted_at<',$olderStamp);
        return $this->delete(null,true);
    }
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function imageCreate( $data ){
        $data['is_disabled']=1;
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
        }
        $this->filterMake( $filter );
        $this->select('pgl.group_id,pgl.group_parent_id,pgl.group_name,pgl.group_path,image_hash');
        $this->join('product_group_member_list pgml','member_id=product_id');
        $this->join('product_group_list pgl','pgml.group_id=pgl.group_id');
        $this->join('image_list il',"image_holder='product_group_list' AND image_holder_id=pgl.group_id AND is_main=1",'left');
        $this->groupBy('pgl.group_id,image_id');
        $children_groups=$this->get()->getResult();
        $parent_groups=[];
        
        $ImageModel=model("ImageModel");
        foreach($children_groups as $child){
            if( !isset($parent_groups[$child->group_parent_id]) ){
                $ImageModel->where('image_holder','product_group_list');
                $ImageModel->where('image_holder_id',$child->group_parent_id);
                $parent_groups[$child->group_parent_id]=[
                    'group_id'=>$child->group_parent_id,
                    'group_name'=>explode('/',$child->group_path)[1],
                    'image_hash'=>$ImageModel->get()->getRow('image_hash')
                ];
            }
            if( $depth=='all' ){
                $parent_groups[$child->group_parent_id]['children'][$child->group_id]=$child;
            }
        }
        return $parent_groups;
    }

}