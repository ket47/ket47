<?php

namespace App\Controllers\Api;
use \CodeIgniter\API\ResponseTrait;

class PosifloraWorm extends \App\Controllers\BaseController{
    
    use ResponseTrait;

    private $categories=[];
    private $bouquets=null;
    private $specifications=null;
    private $productList=[];
    private $prods;
    private $subdomain;

    private function bouquetListGet(){
        try{
            $bouquetsJson=file_get_contents("https://{$this->subdomain}.posiflora.com/shop/api/v1/bouquets?page%5Bnumber%5D=1&page%5Bsize%5D=100");
        } catch(\Throwable $e){
            pl($e,true);
        }
        $this->bouquets=json_decode($bouquetsJson);
    }

    private function specificationListGet(){
        try{
            $specificationsJson=file_get_contents("https://{$this->subdomain}.posiflora.com/shop/api/v1/specifications?page%5Bnumber%5D=1&page%5Bsize%5D=100&include=specVariants,specVariants.variant");
        } catch(\Throwable $e){
            pl($e,true);
        }
        $this->specifications=json_decode($specificationsJson);
    }

    private function prodListGet(){
        try{
            $prodJson=file_get_contents("https://{$this->subdomain}.posiflora.com/shop/api/v1/products?page%5Bnumber%5D=1&page%5Bsize%5D=100");
        } catch(\Throwable $e){
            pl($e,true);
        }
        $this->prods=json_decode($prodJson);
    }

    private $categoryMap=[
        'цветы'=>'Цветы',
        'шары'=>'Шары',
        'сладости'=>'Шоколад',
        'игрушки'=>'Мягкие игрушки'
    ];
    private function categoryListGet(){
        try{
            $catJson=file_get_contents("https://{$this->subdomain}.posiflora.com/shop/api/v1/categories");
        } catch(\Throwable $e){
            pl($e,true);
        }
        $cats=json_decode($catJson);

        foreach($cats->data as $cat){
            if( $cat->relationships->parent->data??null ){
                continue;
            }
            $local_title=$this->categoryMap['цветы'];
            $remote_title=$cat->attributes->title??'';
            foreach($this->categoryMap as $key=>$val){
                if( mb_stripos($remote_title,$key)!==false ){
                    $local_title=$val;
                }
            }
            $this->categories[$cat->id]=$local_title;
        }
    }


    private function productlistFill(){
        $productList=[];
        $this->bouquetListGet();
        foreach($this->bouquets->data as $item){
            $in_stock=1;
            $productList[]=[
                $item->id,
                $item->attributes->docNo,
                $item->attributes->title,
                $item->attributes->description?$item->attributes->description:$item->attributes->title,
                $item->attributes->saleAmount,
                $in_stock,//product_quantity
                $item->attributes->logoShop,
                'Букеты',
            ];
        }
        
        $this->specificationListGet();
        foreach($this->specifications->data as $item){
            $in_stock=1;
            $productList[]=[
                $item->id,
                '',
                $item->attributes->title,
                $item->attributes->description?$item->attributes->description:$item->attributes->title,
                $item->attributes->totalAmount,
                $in_stock,//product_quantity
                $item->attributes->logoShop,
                'Шоколад ручной работы',
            ];
        }

        $this->categoryListGet();
        $this->prodListGet();
        foreach($this->prods->data as $item){
            $in_stock=0;
            if($item->attributes->status=='on'){
                $in_stock=10;
            }
            $category_id=$item->relationships->category->data->id??null;
            $category_title=$this->categories[$category_id]??'Цветы';
            $productList[]=[
                $item->id,
                '',
                $item->attributes->title,
                $item->attributes->description?$item->attributes->description:$item->attributes->title,
                $item->attributes->price,
                $in_stock,//product_quantity
                $item->attributes->logoShop,
                $category_title,
            ];
        }
        return $productList;
    }

    public function dig(){
        $this->subdomain=$this->request->getVar('subdomain');
        $token_hash=$this->request->getVar('token');

        $result=$this->auth($token_hash);
        if($result!=='ok' || !$this->subdomain){
            return $this->failForbidden($result);
        }
        $productList=$this->productListFill();
        $token_data=session()->get('token_data');

        $colconfig=(object)[
            'product_external_id'=>'C1',
            'product_code'=>'C2',
            'product_name'=>'C3',
            'product_description'=>'C4',
            'product_price'=>'C5',
            'product_quantity'=>'C6',
            'product_image_url'=>'C7',
            'product_category_name'=>'C8',
        ];
        $holder='store';
        $holder_id=$token_data->token_holder_id;
        $target='product';

        $ImporterModel=model('ImporterModel');
        $ImporterModel->itemCreateAsDisabled=false;
        $ImporterModel->itemImageCreateAsDisabled=false;
        $ImporterModel->listCreate( $productList, $holder, $holder_id, $target, $external_id_index=0 );
        $result=$ImporterModel->listImport( $holder, $holder_id, $target, $colconfig );
        return $this->respond($result);
    }

    private function auth( $token_hash ){
        $UserModel=model('UserModel');
        $result=$UserModel->signInByToken($token_hash,'store');
        if( $result=='ok' ){
            $user=$UserModel->getSignedUser();
            if( !$user ){
                return 'user_data_fetch_error';
            }
            session()->set('user_id',$user->user_id);
            session()->set('user_data',$user);
        }
        return $result;
    }
}