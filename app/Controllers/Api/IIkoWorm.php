<?php
namespace App\Controllers\Api;
use \CodeIgniter\API\ResponseTrait;
class IIkoWorm extends \App\Controllers\BaseController{
    
    use ResponseTrait;

    private $externalToken;
    public function dig(){
        $token_hash=$this->request->getVar('token');
        $apikey=$this->request->getVar('apikey');
        $organizationId=$this->request->getVar('organizationId');
        $priceMultiplier=$this->request->getVar('priceMultiplier');



        // if( !$menu=@json_decode(file_get_contents('tmptmp')) ){
        //     $apikey="c964c5cce8e9433baaa5b48f8dbc2268";
        //     $organizationId="63b2053d-77c0-4a06-9371-c70cf1fe8674";//;"ad10041c-7f7c-49c9-8587-7826e427d59d"



        //     file_put_contents('tmptmp',json_encode($menu));
        // }
        $result=$this->auth($token_hash);
        if($result!=='ok'){
            return $this->failForbidden($result);
        }
        $this->externalToken=$this->apiLogin($apikey);
        if( !$this->externalToken ){
            return $this->fail('login_failed');
        }
        $menu=$this->apiMenuGet($organizationId);
        //p($menu);
        $productList=$this->filter($menu->products,1);
        return $this->import($productList);
    }

    private $dictUnit=[
        "шт"=>'шт',
        "порц"=>'порция вес',
    ];

    private function apiLogin($apikey){
        $request=[
            'apiLogin'=>$apikey
        ];
        $login=$this->apiExecute("https://api-ru.iiko.services/api/1/access_token",$request,'POST');
        return $login->token??null;
    }

    private function apiOrganizationsGet(){
        $auth_headers=["Authorization: Bearer $this->externalToken"];
        $request=['organizationIds' => null, 'returnAdditionalInfo' => 1, 'includeDisabled' => 0];
        return $this->apiExecute("https://api-ru.iiko.services/api/1/organizations",$request,'POST',$auth_headers);
    }

    private function apiMenuGet($organizationId){
        $auth_headers=["Authorization: Bearer $this->externalToken"];
        $request=['organizationId' => $organizationId, 'startRevision' => 0];
        return $this->apiExecute("https://api-ru.iiko.services/api/1/nomenclature",$request,'POST',$auth_headers);
    }

    private $groupsWhitelist=[
        'c6ef5a26-cb48-45b6-8eb8-e62511404400',//СЕТЫ
        'bc2fbbc6-62a5-4f77-9ed1-70dbb2f60804',//ПИЦЦЫ
        '1e74c9c0-1ac8-4a69-bb38-4cca34b8af2e',//ПИЦЦЫ варианты
        '213fabed-85bd-4bef-9ae7-3667aaf469c9',//ЯПОНСКАЯ КУХНЯ
        'd0325ed0-928c-480b-a6a2-210022f0eb67',//КОМБО НАБОРЫ
        '6b312245-78c3-47f8-bc51-c8ea9b5e976f',//ЕВРОПА 
        'c0a1df17-e52f-4367-a948-179a667ea6ce',//МАНГАЛ 
        'eced9847-0946-4b56-8872-14046c8d18de',//ДЕСЕРТЫ
        'a99d36fe-b99e-41eb-b2ac-4ac63264dccb',//БЛЮДА ЗА 280 РУБ
    ];
    private function filter( $products, float $priceMultiplier=1.33 ){
        $filtered=[];
        $optionLinks=[];
        foreach($products as $row){
            if( !in_array($row->parentGroup,$this->groupsWhitelist) ){//
                continue;
            }
            $facts="";
            if($row->fatAmount){
                $facts.="\nЖиры: $row->fatAmount";
            }
            if($row->proteinsAmount){
                $facts.="\nБелки: $row->proteinsAmount";
            }
            if($row->carbohydratesAmount){
                $facts.="\nУглеводы: $row->carbohydratesAmount";
            }
            if($row->energyAmount){
                $facts.="\nКкал: $row->energyAmount";
            }
            $description=$row->description.$facts;
            $price=$row->sizePrices[0]->price->currentPrice??null;
            
            if( empty($price) ){//May be with options
                if( isset($row->modifiers[0]) ){
                    $firstOption=$row->modifiers[0];
                    $is_parent=1;
                    foreach($row->modifiers as $option){
                        $optionLinks[$option->id]=[
                            'is_parent'=>$is_parent,
                            'parent_external_id'=>$firstOption->id,
                            'parent_product_name'=>$row->name,
                            'parent_product_description'=>$description,
                            'parent_product_image'=>$row->imageLinks[0]??null,
                        ];
                        $is_parent=0;
                        $row->name=null;
                        $description=null;
                    }
                }
                continue;
            }
            $price_base=round($price*$priceMultiplier);
            $filtered[]=[
                $row->id,//external id
                null,//parent external id
                null,//option
                $price_base,//price
                $row->name,//name
                $description,//descr
                $this->dictUnit[$row->measureUnit]??'',//unit
                $row->weight,//weight
                $row->imageLinks[0]??null,//image
                0,//is_counted
                1,//quantity
            ];
        }
        foreach($products as $row){
            if( !isset($optionLinks[$row->id]) ){
                continue;
            }
            $price=$row->sizePrices[0]->price->currentPrice??null;
            $price_base=round($price*$priceMultiplier);

            if( $optionLinks[$row->id]['is_parent'] ){//this is child
                $filtered[]=[
                    $row->id,//external id
                    $row->id,//parent external id
                    $row->name,//option
                    $price_base,//price
                    $optionLinks[$row->id]['parent_product_name'],//name
                    $optionLinks[$row->id]['parent_product_description'],//descr
                    $this->dictUnit[$row->measureUnit]??'',//unit
                    $row->weight,//weight
                    $row->imageLinks[0]??$optionLinks[$row->id]['parent_product_image']??null,//image
                    0,//is_counted
                    1,//quantity
                ];
                $optionLinks[$row->id]['is_child']=1;
            } else {//this is parent
                $filtered[]=[
                    $row->id,//external id
                    $optionLinks[$row->id]['parent_external_id'],//parent external id
                    $row->name,//option
                    $price_base,//price
                    '----',//name
                    null,//descr
                    null,//unit
                    null,//weight
                    null,//image
                    0,//is_counted
                    1,//quantity
                ];
            }
        }
        return $filtered;
    }

    private function import($productList){
        $token_data=session()->get('token_data');

        $colconfig=(object)[
            'product_external_id'=>'C1',
            'product_external_parent_id'=>'C2',
            'product_option'=>'C3',
            'product_price'=>'C4',
            'product_name'=>'C5',
            'product_description'=>'C6',
            'product_unit'=>'C7',
            'product_weight'=>'C8',
            'product_weight'=>'C8',
            'product_image_url'=>'C9',
            'is_counted'=>'C10',
            'product_quantity'=>'C11',
        ];
        $holder='store';
        $holder_id=$token_data->token_holder_id;
        $target='product';

        $ImporterModel=model('ImporterModel');
        $ImporterModel->itemCreateAsDisabled=true;
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

    private function normalizeName( $name ){
        if( !$name ){
            return $name;
        }
        $words=explode(" ",$name);
        for( $i=0; $i<count($words); $i++ ){
            if( $words[$i]===mb_strtoupper($words[$i]) ){
                $words[$i]=mb_convert_case($words[$i], MB_CASE_TITLE, "UTF-8");
            }
        }
        return implode(" ",$words);
    }

    public function apiExecute( string $url, array $request=null, string $method='POST', array $headers=[] ){
        $curl = curl_init(); 
        switch( $method ){
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                if( $request ){
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
                    $headers[]="Content-Type: application/json";
                }
                break;
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($curl);
        //pl($headers);
        //p(curl_getinfo($curl),0);
        if( curl_error($curl) ){
            log_message("error","$url API Execute error: ".curl_error($curl));
            die(curl_error($curl));
        }
        curl_close($curl);
        return json_decode($result);
    }

}