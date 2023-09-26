<?php

namespace App\Controllers\Api\v1;
use \CodeIgniter\API\ResponseTrait;

class Product extends \App\Controllers\BaseController{
    
    use ResponseTrait;

    private function authTokenGet(){
        foreach (getallheaders() as $name => $value) {
            if( $name=='Authorization' ){
                $chunks=explode('Bearer ',$value);
                return array_pop($chunks);
            }
        }
    }
    private function auth(){
        $token_hash=$this->authTokenGet();
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

    private function colconfigMake($data){
        $colconfig=[];
        foreach($data->cols as $i=>$col){
            $colconfig[$col]="C".($i+1);
        }
        return (object)$colconfig;
    }

    public function listSave(){
        $data=$this->request->getJSON();
        $result=$this->auth();
        if( $result!='ok' ){
            return $this->failForbidden();
        }
        $token_data=session()->get('token_data');

        $target='product';
        $holder='store';
        $holder_id=$token_data->token_holder_id;
        $colconfig=$this->colconfigMake($data);

        $ImporterModel=model('ImporterModel');
        $ImporterModel->itemCreateAsDisabled=false;








        
        $ImporterModel->olderItemsDeleteTresholdSet( date('Y-m-d H:i:s') );//delete all products that left in imported_list
        $ImporterModel->listCreate( $data->rows, $holder, $holder_id, $target, $external_id_index=0 );
        $result=$ImporterModel->listImport( $holder, $holder_id, $target, $colconfig );
        return $this->respond($result);
    }








    private $categories=[];
    private $bouquets=null;
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

    private function prodListGet(){
        try{
            $prodJson=file_get_contents("https://{$this->subdomain}.posiflora.com/shop/api/v1/products?page%5Bnumber%5D=1&page%5Bsize%5D=100");
        } catch(\Throwable $e){
            pl($e,true);
        }
        $this->prods=json_decode($prodJson);
    }

    private function productlistFill(){
        $this->bouquetListGet();
        $productList=[];

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

        $this->prodListGet();
        foreach($this->prods->data as $item){
            $in_stock=0;
            if($item->attributes->status=='on'){
                $in_stock=10;
            }
            $productList[]=[
                $item->id,
                '',
                $item->attributes->title,
                $item->attributes->description?$item->attributes->description:$item->attributes->title,
                $item->attributes->price,
                $in_stock,//product_quantity
                $item->attributes->logoShop,
                'Живые цветы',
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
        $ImporterModel->listCreate( $productList, $holder, $holder_id, $target, $external_id_index=0 );
        $result=$ImporterModel->listImport( $holder, $holder_id, $target, $colconfig );
        return $this->respond($result);
    }

}