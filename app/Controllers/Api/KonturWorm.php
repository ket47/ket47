<?php
namespace App\Controllers\Api;
use \CodeIgniter\API\ResponseTrait;
class KonturWorm extends \App\Controllers\BaseController{
    
    use ResponseTrait;

    public function dig(){
        $gateway="https://api.kontur.ru/market/v1";
        $token_hash=$this->request->getVar('token');
        $apikey=$this->request->getVar('apikey');
        $priceMultiplier=$this->request->getVar('priceMultiplier');

        $result=$this->auth($token_hash);
        if($result!=='ok'){
            return $this->failForbidden($result);
        }
        $url="$gateway/shops";
        $headers[]="x-kontur-apikey: $apikey";
        $shops=$this->apiExecuteKontur($url,null,'GET',$headers);

        if( empty($shops->items[0]->id) ){
            die('no_shops');
        }
        $shopId=$shops->items[0]->id;

        $url="$gateway/shops/$shopId/product-groups";
        $groups=$this->apiExecuteKontur($url,null,'GET',$headers);

        $url="$gateway/shops/$shopId/products";
        $rows=$this->apiExecuteKontur($url,null,'GET',$headers);

        $this->auth($token_hash);
        $productList=$this->filter($rows,$groups,$priceMultiplier);
        return $this->import($productList);
    }

    private function import($productList){
        $token_data=session()->get('token_data');

        $colconfig=(object)[
            'product_external_id'=>'C1',
            'product_code'=>'C2',
            'product_name'=>'C3',
            'product_barcode'=>'C4',
            'product_unit'=>'C5',
            'product_category_name'=>'C6',
            'product_quantity'=>'C7',
            'product_price'=>'C8',
            'product_promo_price'=>'C9',
            'product_promo_start'=>'C10',
            'product_promo_finish'=>'C11',
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

    private $dictUnit=[
        "Piece"=>'шт',
        "Package"=>'уп',
        "Kilogram"=>'кг',
        "Gram"=>'г',
        "Liter"=>'л',
        "Milliliter"=>'мл',
    ];
    private $mapCategory=[
        'Сахар, соль, сода'=>'Мука, крахмал',
        'Молоко и молочная продукция'=>'Молоко и сливки',
        'Зефир'=>'Зефир, мармелад',
        'Детское питание'=>'',
        'Жевательная резинка и леденцы'=>'',
        'Хозяйственные товары'=>'',
        'Блюда быстрого приготовления'=>'',
        'Овощи'=>'Овощи, грибы, зелень',
        'Чай, кофе'=>'Чай',
        'Напитки'=>'Соки',
        'разное'=>'',
        'Колбасные изделия'=>'Деликатес',
        'Салаты, зелень'=>'Овощи, грибы, зелень',
        'Хлеб и выпечка'=>'Хлеб',
        'Птица'=>'Птица',
        'Пакет'=>'',
        'Мороженое'=>'',
        'Шоколад'=>'',
        'Квас'=>'',
        'Масло растительное, соусы, приправы'=>'',
        'Товары для дома'=>'',
        'Орехи, семечки, сухофрукты'=>'',
        'Товары для детей'=>'',
        'Печёное '=>'',
        'Безалкогольное пиво'=>'',
        'Замороженные продукты'=>'',
        'Хлеб и хлебобулочные изделия'=>'',
        'Сигареты'=>'',
        'Замороженные продукты'=>'',
        'Электротовары'=>'',
        'Мед, варенье, джем'=>'',
        'Кофе'=>'',
        'Готовые блюда'=>'',
        'Кисло-молочная продукция'=>'Кефир',
        'Майонез'=>'',
        'Крупы'=>'',
        'Товары для животных'=>'',
        'Соки, нектары и морсы'=>'',
        'Заварной кофе чай'=>'Чай',
        'Бытовая химия и косметика'=>'',
        'Овощные консервы'=>'',
        'Макаронные изделия'=>'',
        'Чай'=>'Чай',
        'Конфеты'=>'',
        'Салат'=>'',
        'Уголь'=>'',
        'Молочные продукты, сыры и яйцо'=>'Молоко и сливки',
        'Энергетические напитки'=>'',
        'Рыбные консервы'=>'',
        'Средства гигиены'=>'',
        'Топпинг'=>'',
        'Средства для уборки дома'=>'',
        'Табак и табачные изделия'=>'',
        'Специи'=>'Специи',
        'Сыр и творог'=>'Сыр',
        'Молоко и сливки сгущенные'=>'Молоко и сливки',
        'Мука, крахмал'=>'',
        'Кондитерские изделия'=>'',
        'Воды минеральные и сладкие'=>'',
        'Рыба и морепродукты'=>'',
        'Фрукты, ягоды'=>'Фрукты',
        'Сухие завтраки, хлопья'=>'',
        'Табачные изделия'=>'',
        'Тесто и полуфабрикаты'=>'',
        'Масло сливочное, маргарин'=>'',
        'Мясные консервы'=>'',
        'Вафли'=>'',
        'Средства для стирки'=>'',
        'Яйца'=>'Яйца',
        'Мясо и полуфабрикаты'=>'Мясо',
        'Овощи и фрукты'=>'Фрукты',
        'Чипсы, сухарики, снэки'=>'',
        'Консервы'=>'',
        'Копченная рыба'=>'',
        'Стаканы одноразовые'=>'',
        'Мясо, рыба, птица'=>'Мясо',
        'Игрушки'=>'',
        'Торты, пирожные'=>'',
        'Ягоды'=>'Ягоды',
        'Какао'=>'',
        'Бакалея'=>'',
        'Печенье, пряники'=>'',
        'Сыр'=>'Сыр',
        'Без группы'=>'',
        'Без группы'=>'',
    ];

    private function filter( $rows, $groups, float $priceMultiplier=1.33 ){
        $whitelistCodes=explode("\n",file_get_contents(WRITEPATH."/minimarketWhitelist.txt"));
        $groupDict=[];
        foreach($groups->items as $group){
            $groupDict[$group->id]=$this->mapCategory[$group->name];
        }
        $filtered=[];
        foreach($rows->items as $row){
            if( empty($row->sellPricePerUnit) ){
                continue;
            }
            if( !in_array($row->code,$whitelistCodes) ){
                continue;
            }

            $in_stock=3;
            $price_base=round($row->sellPricePerUnit*$priceMultiplier);
            $price_promo=null;
            $price_start=null;
            $price_finish=null;
            if( rand(1,10)==1 ){//10% chance
                $price_promo=$price_base;
                $price_base=round($price_base*rand(110,130)/100);
                $price_start=date('Y-m-d H:i:s');
                $price_finish=date('Y-m-d H:i:s',time()+24*60*60);
            }
            $filtered[]=[
                $row->id,
                $row->code,
                $this->normalizeName( $row->name ),
                $row->barcodes[0]??null,
                $this->dictUnit[$row->unit]??'',
                $groupDict[$row->groupId]??null,
                $in_stock,//product_quantity
                $price_base,
                $price_promo,
                $price_start,
                $price_finish,
            ];
        }
        return $filtered;
    }

    private function normalizeName( $name ){
        $words=explode(" ",$name);
        for( $i=0; $i<count($words); $i++ ){
            if( $words[$i]===mb_strtoupper($words[$i]) ){
                $words[$i]=mb_convert_case($words[$i], MB_CASE_TITLE, "UTF-8");
            }
        }
        return implode(" ",$words);
    }

    public function apiExecuteKontur( string $url, array $request=null, string $method='POST', array $headers=[] ){
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
        //p(curl_getinfo($curl));
        if( curl_error($curl) ){
            log_message("error","$url API Execute error: ".curl_error($curl));
            die(curl_error($curl));
        }
        curl_close($curl);
        return json_decode($result);
    }

}