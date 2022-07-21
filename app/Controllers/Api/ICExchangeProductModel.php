<?php

namespace App\Controllers\Api;
use CodeIgniter\Model;

class ICExchangeProductModel extends Model{
    private $unit_dict=[
        'Штука'=>'шт',
        'Килограмм'=>'кг',
        'Литр'=>'л',
        'Метр'=>'м',
    ];
    private $product_quantity_expiration_timeout=1;
    private function productUnitGet($xml_product){
        $unit='';
        if( isset(((array)$xml_product->БазоваяЕдиница)['@attributes']['НаименованиеПолное']) ){
            $unit_full_name=((array)$xml_product->БазоваяЕдиница)['@attributes']['НаименованиеПолное'];
            $unit=$this->unit_dict[$unit_full_name];
        }
        return $unit;
    }
    private function productNotChanged($existing_product,$updated_product){
        return 
        $existing_product->product_code==$updated_product->product_code
        && $existing_product->product_name==$updated_product->product_name
        && $existing_product->product_description==$updated_product->product_description
        && $existing_product->product_quantity==$updated_product->product_quantity
        && $existing_product->product_price==$updated_product->product_price;
    }
    public function productSave($xml_product,$holder_id){
        @list($product_1c_id, $variant_1c_id) = explode('#', $xml_product->Ид);
        $ProductModel=model('ProductModel');
        $existing_product=$ProductModel->where('product_external_id',$product_1c_id)->get()->getRow();
        if($xml_product->Статус == 'Удален'){
            if(!$existing_product){
                return true;
            }
            return $ProductModel->itemDelete($existing_product->product_id);
        }
        $updated_product=(object)[
            'product_code'=>$xml_product->Артикул,
            'product_name'=>$xml_product->Наименование,
            'product_description'=>$xml_product->Описание,
            'product_quantity'=>$xml_product->Количество,
            'product_price'=>$xml_product?->Цены?->Цена?->ЦенаЗаЕдиницу,
            'product_unit'=>$this->productUnitGet($xml_product),
            'product_quantity_expire_at'=>date("Y-m-d H:i:s",time()+60*60*$this->product_quantity_expiration_timeout)
        ];
        if($existing_product){
            if($this->productNotChanged($existing_product,$updated_product)){
                return true;
            }
            $updated_product->product_id=$existing_product->product_id;
            return $ProductModel->itemUpdate($updated_product);
        }
        $updated_product->product_external_id=$product_1c_id;
        $updated_product->store_id=$holder_id;
        $updated_product->is_disabled=1;
        return $ProductModel->itemCreate($updated_product);
    }
}