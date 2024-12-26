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
        'Жевательная резинка и леденцы'=>'Жвачки',
        'Хозяйственные товары'=>'Уход за телом',
        'Блюда быстрого приготовления'=>'Макароны',
        'Овощи'=>'Овощи, грибы, зелень',
        'Чай, кофе'=>'Чай',
        'Напитки'=>'Соки',
        'разное'=>'',
        'Колбасные изделия'=>'Деликатес',
        'Салаты, зелень'=>'Овощи, грибы, зелень',
        'Хлеб и выпечка'=>'Хлеб',
        'Птица'=>'Птица',
        'Пакет'=>'',
        'Мороженое'=>'Мороженое',
        'Шоколад'=>'Шоколад',
        'Квас'=>'Газированные',
        'Масло растительное, соусы, приправы'=>'Масло и соусы',
        'Товары для дома'=>'',
        'Орехи, семечки, сухофрукты'=>'Орехи',
        'Товары для детей'=>'',
        'Печёное '=>'Пирожные',
        'Безалкогольное пиво'=>'',
        'Замороженные продукты'=>'Пельмени, вареники',
        'Хлеб и хлебобулочные изделия'=>'Хлеб',
        'Сигареты'=>'',
        'Замороженные продукты'=>'Замороженные овощи',
        'Электротовары'=>'Подрозетники',
        'Мед, варенье, джем'=>'Масло и соусы',
        'Кофе'=>'Кофе, какао',
        'Готовые блюда'=>'Горячие блюда',
        'Кисло-молочная продукция'=>'Кефир',
        'Майонез'=>'Масло и соусы',
        'Крупы'=>'Крупы',
        'Товары для животных'=>'Корм для животных',
        'Соки, нектары и морсы'=>'Соки',
        'Заварной кофе чай'=>'Чай',
        'Бытовая химия и косметика'=>'Уход за лицом',
        'Овощные консервы'=>'Овощная консервация',
        'Макаронные изделия'=>'Макароны',
        'Чай'=>'Чай',
        'Конфеты'=>'Конфеты, козинаки',
        'Салат'=>'Салаты',
        'Уголь'=>'',
        'Молочные продукты, сыры и яйцо'=>'Молоко и сливки',
        'Энергетические напитки'=>'Энергетики',
        'Рыбные консервы'=>'Консервы',
        'Средства гигиены'=>'Уход за телом',
        'Топпинг'=>'Масло и соусы',
        'Средства для уборки дома'=>'',
        'Табак и табачные изделия'=>'',
        'Специи'=>'Специи',
        'Сыр и творог'=>'Сыр',
        'Молоко и сливки сгущенные'=>'Молоко и сливки',
        'Мука, крахмал'=>'Мука, крахмал',
        'Кондитерские изделия'=>'Торты',
        'Воды минеральные и сладкие'=>'Газированные',
        'Рыба и морепродукты'=>'Рыба',
        'Фрукты, ягоды'=>'Фрукты',
        'Сухие завтраки, хлопья'=>'Сухие завтраки',
        'Табачные изделия'=>'',
        'Тесто и полуфабрикаты'=>'Тесто слоеное',
        'Масло сливочное, маргарин'=>'Сливочное масло и маргарин',
        'Мясные консервы'=>'Консервы',
        'Вафли'=>'Вафли и кексы',
        'Средства для стирки'=>'',
        'Яйца'=>'Яйца',
        'Мясо и полуфабрикаты'=>'Мясо',
        'Овощи и фрукты'=>'Фрукты',
        'Чипсы, сухарики, снэки'=>'Чипсы',
        'Консервы'=>'Консервы',
        'Копченная рыба'=>'Рыба',
        'Стаканы одноразовые'=>'',
        'Мясо, рыба, птица'=>'Мясо',
        'Игрушки'=>'',
        'Торты, пирожные'=>'Торты',
        'Ягоды'=>'Ягоды',
        'Какао'=>'Кофе, какао',
        'Бакалея'=>'Макароны',
        'Печенье, пряники'=>'Печенье',
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

        $promo_window=0.10;//10%
        $promo_depth=0.90;//-10% net discount
        $promo_add_min=110;//gross discount min
        $promo_add_max=130;//gross discount max

        $week=date('W');//week will start at sunday
        srand($week);
        $promo_start=date("Y-m-d H:i:s", strtotime('last monday'));
        $promo_finish=date('Y-m-d H:i:s',strtotime("{$promo_start} + 6 days"));

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

            $score=hexdec(substr(md5("{$week}-{$row->id}"),0,3))/hexdec('fff');
            if( $score<$promo_window ){//not in window
                $price_promo=$price_base*$promo_depth;
                $price_base=round($price_base*rand($promo_add_min,$promo_add_max)/100);
                $price_start=$promo_start;
                $price_finish=$promo_finish;
            }
            $filtered[]=[
                $row->id,
                $row->code,
                $this->normalizeName( $row->name ),
                $row->barcodes[0]??null,
                $this->dictUnit[$row->unit]??'',
                null,//$groupDict[$row->groupId]??null, skip this field
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