<?php

namespace App\Controllers\Api;
use \CodeIgniter\API\ResponseTrait;

class TildaWorm1 extends \App\Controllers\BaseController{
    
    use ResponseTrait;

    private $categories=[];
    private $bouquets=null;
    private $specifications=null;
    private $productList=[];
    private $prods;
    private $subdomain;

    private $categoryMap=[
        'Готовые букеты'=>'Букеты',
        'Моно букеты'=>'Букеты',
        'Авторские букеты'=>'Букеты',
        'Композиции в коробках'=>'Композиции',
        'Композиции в корзинах'=>'Композиции',
        'Букеты невесты'=>'Букеты невесты',
        
        'Гелиевые шары'=>'Шары',
        'Клубника в шоколаде'=>'Шоколад ручной работы',
        'Свечи'=>'Свечи',
        'Подарочные наборы'=>'Подарки',
        'Открытки'=>'Подарки',
        

        'Цветы домой'=>'Цветы',
        'Цветы предприятиям'=>'Цветы',
        'Розы'=>'Цветы',

        'Цветы домой'=>'Цветы',
        
        'Вкусные подарки'=>'Шоколад',
        'Игрушки'=>'Мягкие игрушки',
        
    ];

    private function categoryFind( array $categories, string $partuids ){
        $ids=json_decode($partuids);
        if( !count($ids) ){
            return '';
        }
        foreach( $ids as $id ){
            foreach($categories as $category){
                if( $id==$category->uid ){
                    return $this->categoryMap[$category->title];
                }
            }
        }
        return '';
    }
    private function imageFind( $prod ){
        $imgs=json_decode($prod->gallery);
        if( count($imgs) ){
            return $imgs[0]->img;
        }
        return $prod->editions[0]->img;
    }
    private function stockFind($prod, $category){
        if( $prod->quantity>0 ){
            return $prod->quantity;
        }
        if( in_array($category,['Цветы','Свечи','Шары']) ){
            return 100;
        }
        if( in_array($category,['Подарки','Шоколад']) ){
            return 10;
        }
        return 1;
    }
    private function priceFind($prod){
        if( $prod->editions[0]->price>0 ){
            return $this->priceParse($prod->editions[0]->price);
        }
        return $this->priceParse($prod->price);
    }

    private function priceParse( string $price):int{
        return (int)preg_replace("/[^0-9.]/", "", $price);
    }

    private function productListFill():array{
        //$url="https://store.tildaapi.com/api/getproductslist/?storepartuid=949752710411&recid=720272320&c=1716618962040&getparts=true&getoptions=true&slice=1&size=36";
        $url="https://store.tildaapi.com/api/getproductslist/?storepartuid=513610670921&getparts=true&getoptions=true&size=360";
        $json=@file_get_contents($url);
        if(!$json){
            return null;
        }
        $response=json_decode($json);
        $categories=$response->parts??[];
        $products=$response->products;
        $productList=[];
        foreach($products as $prod){
            $category=$this->categoryFind( $categories,  $prod->partuids );
            if( $prod->editions??null ){
                $this->productOptionFill($productList,$prod,$category);
            } else {
                $this->productFill($productList,$prod,$category);
            }
        }
        return $productList;
    }

    private function productFill( array &$productList, object $prod, string $category ):void{
        $price=$this->priceParse($prod->price);;
        $description=str_replace('<br />',"\n\n",$prod->text);
        $productList[]=[
            $prod->uid,
            null,
            $prod->sku,
            $prod->title,
            $description,
            $price,
            $this->stockFind( $prod, $category ),
            $this->imageFind( $prod ),
            $category,
        ];

    }

    private function productOptionFill( array &$productList, object $prod, string $category ):void{
        $description=str_replace('<br />',"\n\n",$prod->text);
        $parent_edition=array_shift($prod->editions);
        $price=$this->priceParse($parent_edition->price??0);;
        $option_confs=json_decode($prod->json_options);
        $oconf=$option_confs[0]??null;

        $productList[]=[
            $prod->uid,
            null,
            $prod->sku,
            $prod->title,
            $description,
            $price,
            $this->stockFind( $parent_edition, $category ),
            $this->imageFind( $prod ),
            $category,
            $oconf?"{$oconf->title} ".$parent_edition->{$oconf->title}:null,
        ];
        if(!$oconf){
            return;
        }
        foreach($prod->editions as $edition){
            $productList[]=[
                $edition->uid,
                $prod->uid,
                '',
                'OPTION',
                '',
                $this->priceParse($edition->price),
                $this->stockFind( $edition, $category ),
                '',
                $category,
                $oconf?"{$oconf->title} ".$edition->{$oconf->title}:null,
            ];
        }
    }


    public function dig(){
        $token_hash=$this->request->getVar('token');

        $result=$this->auth($token_hash);
        if($result!=='ok'){
            return $this->failForbidden($result);
        }
        $productList=$this->productListFill();
        if( !$productList ){//cant open url
            return ;
        }
        $token_data=session()->get('token_data');

        $colconfig=(object)[
            'product_external_id'=>'C1',
            'product_external_parent_id'=>'C2',
            'product_code'=>'C3',
            'product_name'=>'C4',
            'product_description'=>'C5',
            'product_price'=>'C6',
            'product_quantity'=>'C7',
            'product_image_url'=>'C8',
            'product_category_name'=>'C9',
            'product_option'=>'C10',
        ];
        $holder='store';
        $holder_id=$token_data->token_holder_id;
        $target='product';

        $ImporterModel=model('ImporterModel');
        $ImporterModel->itemCreateAsDisabled=false;
        $ImporterModel->itemImageCreateAsDisabled=false;
        $ImporterModel->listCreate( $productList, $holder, $holder_id, $target, $external_id_index=0, $external_parent_id_index=1 );
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