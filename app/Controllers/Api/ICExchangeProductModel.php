<?php

namespace App\Controllers\Api;
use CodeIgniter\Model;

class ICExchangeProductModel extends Model{

    private $ProductModel;
    public function productTransStart(){
        $this->ProductModel=model('ProductModel');
        $this->ProductModel->transBegin();
    }

    public function productTransComplete(){
        $this->ProductModel->transCommit();
    }

    private $store_exceptions=[
        '99'=>[
            'ИдСклада'=>'351455e8-d625-11ea-aa68-e4aaeab6caae'
        ],
        '68'=>[
            'price_multiplier'=>1.33
        ]
    ];
    private $unit_dict=[
        'Штука'=>'шт',
        'Штук'=>'шт',
        'Килограмм'=>'кг',
        'Литр'=>'л',
        'Метр'=>'м',
    ];
    private $product_quantity_expiration_timeout=1;
    // private function productFilterUnchanged($existing_product,$updated_product){
    //     if( isset($updated_product->product_code) && $existing_product->product_code==$updated_product->product_code ){
    //         unset($updated_product->product_code);
    //     }
    //     if( isset($updated_product->product_name_new) && $existing_product->product_name==$updated_product->product_name_new ){
    //         unset($updated_product->product_name_new);
    //     }
    //     if( isset($updated_product->product_description_new) && $existing_product->product_description==$updated_product->product_description_new ){
    //         unset($updated_product->product_description_new);
    //     }
    //     if( isset($updated_product->product_quantity) && $existing_product->product_quantity==$updated_product->product_quantity ){
    //         unset($updated_product->product_quantity);
    //     }
    //     if( isset($updated_product->product_price) && $existing_product->product_price==$updated_product->product_price ){
    //         unset($updated_product->product_price);
    //     }
    //     if( isset($updated_product->product_barcode) && $existing_product->product_barcode==$updated_product->product_barcode ){
    //         unset($updated_product->product_barcode);
    //     }
    //     return $updated_product;
    // }
    public function productSave($xml_product,$holder_id){
        @list($product_1c_id, $variant_1c_id) = explode('#', $xml_product->Ид);
        $existing_product=$this->ProductModel->where('product_external_id',$product_1c_id)->where('store_id',$holder_id)->get()->getRow();
        $product_status=$this->productStatusParse($xml_product);
        if($product_status == 'Удален'){
            if(!$existing_product){
                return true;
            }
            return $this->ProductModel->itemDelete($existing_product->product_id);
        }

        if($existing_product){
            $product_quantity=isset($xml_product->Количество)?(float)$xml_product->Количество:0;
            if(isset($xml_product->Склад)){
                foreach($xml_product->Склад as $store_xml){
                    if(isset($this->store_exceptions[$holder_id]['ИдСклада']) && $store_xml['ИдСклада'] == $this->store_exceptions[$holder_id]['ИдСклада']){
                        $product_quantity = $store_xml['КоличествоНаСкладе'];
                    }
                }
            }
            $product_price=isset($xml_product->Цены->Цена->ЦенаЗаЕдиницу)?(float)$xml_product->Цены->Цена->ЦенаЗаЕдиницу:0;
            $product_price_multiplier=$this->store_exceptions[$holder_id]['price_multiplier']??1;

            $updated_product=(object)[
                'product_id'=>$existing_product->product_id,
                'product_quantity'=>$product_quantity,
                'product_price'=>round($product_price*$product_price_multiplier),
            ];
            $result=$this->ProductModel->itemUpdate($updated_product);
            return $result;
        }

        $attributes=$this->productAttributesParse($xml_product);
        $created_product=(object)[
            'store_id'=>$holder_id,
            'is_disabled'=>1,
            'product_external_id'=>$product_1c_id,
            'product_code'=>(string) $xml_product->Артикул?$xml_product->Артикул:$attributes->product_code??'',
            'product_name'=>(string)$xml_product->Наименование,
            'product_description'=>(string)$xml_product->Описание,
            'product_barcode'=>(string)$xml_product->Штрихкод,
            'product_unit'=>$this->productUnitGet($xml_product),
            'product_price'=>0
        ];
        $result=$this->ProductModel->itemCreate($created_product);
        return $result;
    }

    public function productListValidate($store_id){
        $this->ProductModel->listUpdateValidity($store_id);
    }

    private function productAttributesParse($xml_product){
        $attributes=(object)[];
        if (isset($xml_product->ЗначенияРеквизитов->ЗначениеРеквизита)){
            foreach ($xml_product->ЗначенияРеквизитов->ЗначениеРеквизита as $r) {
                if($r->Наименование=='Код'){
                    $attributes->product_code=(string)$r->Значение;
                }
                // if($r->Наименование=='Полное наименование'){
                //     $attributes->product_name_full=$r->Значение;
                // }
                // if($r->Наименование=='Описание'){
                //     $attributes->product_description=$r->Значение;
                // }
            }
        }
        return $attributes;
    }
    private function productStatusParse($xml_product){
        if( isset($xml_product->Статус) ){
            return $xml_product->Статус;
        }
        $attrs=((array)$xml_product)['@attributes']??[];
        return $attrs['Статус']??'';
    }
    private function productUnitGet($xml_product){
        $unit='';
        if( isset(((array)$xml_product->БазоваяЕдиница)['@attributes']['НаименованиеПолное']) ){
            $unit_full_name=((array)$xml_product->БазоваяЕдиница)['@attributes']['НаименованиеПолное'];
            $unit=$this->unit_dict[$unit_full_name]??'шт';
        }
        return $unit;
    }
}