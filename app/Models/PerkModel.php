<?php
namespace App\Models;
use CodeIgniter\Model;

class PerkModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'perk_list';
    protected $primaryKey = 'perk_id';
    protected $allowedFields = [

        ];

    protected $useSoftDeletes = true;
    
    
    public function itemGet(){
        return false;
    }
    
    public function itemCreate(){
        return false;
    }
    
    public function itemUpdate(){
        return false;
    }
    
    public function itemDelete(){
        return false;
    }
    
    public function listGet($holder,$holder_id){
        if($holder=='store'){
            return $this->storePerksGet($holder_id);
        }
        return false;
    }

    private function storePerksGet($store_id){//temporary function

        $has_promo_perk=false;

        $perks=[];
        $ProductModel=model('ProductModel');
        $ProductModel->select("ROUND(`product_promo_price`/`product_price`*100-100) product_discount");
        $ProductModel->where("IFNULL(product_promo_price,0)>0 AND `product_price`>`product_promo_price` AND product_promo_start<NOW() AND product_promo_finish>NOW()");
        $ProductModel->where("image_hash IS NOT NULL");
        $ProductModel->orderBy("product_discount");
        $promo_prod_list=$ProductModel->listGet(['store_id'=>$store_id,'limit'=>3]);
        foreach($promo_prod_list as $product){
            $perks[]=[
                'product_id'=>$product->product_id,
                'product_price'=>$product->product_price,
                'product_final_price'=>$product->product_final_price,
                'perk_label'=>$product->product_discount.'%',
                'perk_title'=>$product->product_name,
                'image_hash'=>$product->image_hash,
                'slot'=>'slider'
            ];
            $has_promo_perk=true;
        }

        if($has_promo_perk){
            $perks[]=[
                'perk_label'=>'',
                'image_url'=>'promo.png',
                'slot'=>'perk'
            ];
        }

        
        $StoreGroupMemberModel=model('StoreGroupMemberModel');
        $StoreGroupMemberModel->where('group_type','halal');
        $StoreGroupMemberModel->select('image_hash');
        $StoreGroupMemberModel->join('image_list',"image_holder='store_group_list' AND image_holder_id=group_id");
        $groups=$StoreGroupMemberModel->memberOfGroupsListGet($store_id);
        if($groups[0]??null){
            $perks[]=[
                'perk_label'=>'',
                'image_hash'=>$groups[0]->image_hash,
                'slot'=>'perk'
            ];
        }
        return $perks;
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