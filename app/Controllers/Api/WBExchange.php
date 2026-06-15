<?php
namespace App\Controllers\Api;

use \CodeIgniter\API\ResponseTrait;

class WBExchange extends \App\Controllers\BaseController {
    
    use ResponseTrait;

    private $externalToken;
    
    private $max_exec_time = 600;
    private $batch_limit = 1000;

    public function dig( $token_hash=null, $wbToken=null, $price_multiplier=null ) {
        set_time_limit($this->max_exec_time);

        if( !$token_hash ){//if called from redis job
            $token_hash = $this->request->getVar('apikey');
            $wbToken = $this->request->getVar('token'); 
            $price_multiplier = $this->request->getVar('price_multiplier'); 
        }
        
        $result = $this->auth($token_hash);
        if ($result !== 'ok') {
            pl('WBExchange auth fail');
            return $this->failForbidden($result);
        }
        
        $store_id = $this->getStoreIdByToken($token_hash);
        if (!$store_id) {
            pl('WBExchange Invalid store API Key');
            return $this->failUnauthorized('Invalid internal API Key');
        }

        if (!$wbToken) {
            pl('WBExchange wb_token_required');
            return $this->fail('wb_token_required');
        }
        if (!$price_multiplier) {
            pl('WBExchange price_multiplier_missing');
            return $this->fail('price_multiplier_missing');
        }
        $this->externalToken = $wbToken;
        
        $cursor = $this->getSavedCursor($store_id);

        $cardsData = $this->apiProductsGet($cursor);
        
        $fileName = "wb_cards_{$store_id}.json";

        if (!empty($cardsData['cards'])) {
            $existingCards = $this->readFile($fileName);

            $allCards = array_merge($existingCards, $cardsData['cards']);
            
            $this->writeFile($fileName, $allCards);

            $this->saveCursor($store_id, $cardsData['next_cursor']);

            $wbJob = [
                'task_programm' => [
                    [
                        'controller' => '\App\Controllers\Api\WBExchange',
                        'method' => 'dig',
                        'arguments' => [$token_hash,$wbToken,$price_multiplier]
                    ]
                ],
                'task_next_start_time'=>strtotime('+1 hour')
            ];
            jobCreate($wbJob);

            return $this->respond(['message' => 'Batch saved. Job for next batch created.']);
        } 
        
        $finalCards = $this->readFile($fileName);
        
        $filePath = WRITEPATH . 'uploads/' . $fileName;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        if (!empty($finalCards)) {
            $warehouses = $this->getWarehouses();
            $prices = $this->apiPricesGet($price_multiplier);
            $stocks = $this->apiStocksGet($warehouses, $finalCards);

            $productList = $this->filter($finalCards, $prices, $stocks);

            if (!empty($productList)) {
                $this->import($productList);
            }
        }

        $this->saveCursor($store_id, null);
        return $this->respond(['message' => 'All products fetched, filtered and imported successfully']);
    }

    // public function warehouseStatsGet() {
    //     $url = 'https://seller-analytics-api.wildberries.ru/api/v2/stocks-report/offices';
    //     $request = [
    //         "currentPeriod" => [
    //             "start" => "2024-02-10",
    //             "end" => "2024-02-10"
    //         ]

    //     ];
    //     $response = $this->apiExecute($url, $request, 'GET');
    //     if (empty($response) || !is_array($response)) {
    //         return $this->respond([
    //             'status' => 'error',
    //             'message' => 'Не удалось получить список складов. Проверьте токен (нужны права "Маркетплейс").',
    //             'raw' => $response
    //         ]);
    //     }
    //     return $response;
    // }
    
    private function apiProductsGet($startCursor) {
        $cards = [];
        $currentCursor = $startCursor;
        $iteration = 0;

        while (count($cards) < $this->batch_limit) {
            $iteration++;
            
            $cursorReq = ["limit" => 100];
            if (!empty($currentCursor['updatedAt']) && !empty($currentCursor['nmID'])) {
                $cursorReq['updatedAt'] = $currentCursor['updatedAt'];
                $cursorReq['nmID'] = (int)$currentCursor['nmID'];
            }

            $request = [
                "settings" => [
                    "cursor" => $cursorReq,
                    "filter" => ["withPhoto" => 1]
                ]
            ];

            $response = $this->apiExecute('https://content-api.wildberries.ru/content/v2/get/cards/list', $request, 'POST');

            if (empty($response->cards)) {
                break;
            }

            $cards = array_merge($cards, $response->cards);
            
            $currentCursor = [
                'updatedAt' => $response->cursor->updatedAt,
                'nmID' => $response->cursor->nmID
            ];

            if (count($response->cards) < 100) {
                break;
            }

            if ($iteration > 50) break; 
            usleep(3000); 
        }

        return [
            'cards' => $cards,
            'next_cursor' => $currentCursor
        ];
    }

    private function apiPricesGet( float $price_multiplier=1 ) {
        $priceMap = [];
        $limit = 1000;
        $offset = 0;
    
        while (true) {
            $request = [
                "limit" => $limit,
                "offset" => $offset
            ];
            
            $response = $this->apiExecute('https://discounts-prices-api.wildberries.ru/api/v2/list/goods/filter', $request, 'GET');
            
            if (empty($response->data->listGoods)) {
                break;
            }
            foreach ($response->data->listGoods as $item) {
                $base_price=$item->sizes[0]->price ?? 0;
                $discounted_price=($item->sizes[0]->discountedPrice ?? 0)*$price_multiplier;
                $is_promo_price=rand(1,10)<2;//10% chance
                if($is_promo_price){
                    $priceMap[$item->nmID] = [
                        'price' => $base_price,
                        'promo' => $discounted_price,
                    ];
                } else {
                    $priceMap[$item->nmID] = [
                        'price' => ($item->sizes[0]->discountedPrice ?? 0)*$price_multiplier,
                        'promo' => null,
                    ];
                }
            }
            if (count($response->data->listGoods) < $limit) {
                break;
            }
    
            $offset += $limit;
            usleep(2000);
        }
        return $priceMap;
    }
    public function getWarehouses() {
        $url = 'https://marketplace-api.wildberries.ru/api/v3/warehouses';
        
        $response = $this->apiExecute($url, null, 'GET');
        if (empty($response) || !is_array($response)) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Не удалось получить список складов. Проверьте токен (нужны права "Маркетплейс").',
                'raw' => $response
            ]);
        }
        return $response;
    }
    private function apiStocksGet(array $warehouses, array $cards) {
        $stocksMap = [];
        $chrtIds = [];
        foreach ($cards as $card) {
            if (!empty($card->sizes)) {
                foreach ($card->sizes as $size) {
                    if (!empty($size->chrtID)) {
                        $chrtIds[] = (int)$size->chrtID;
                    }
                }
            }
        }
        
        $chrtIds = array_unique($chrtIds);
        if (empty($chrtIds)) return [];
    
        $chrtIdsChunks = array_chunk($chrtIds, 1000);
    
        foreach ($warehouses as $warehouse) {
            $url = "https://marketplace-api.wildberries.ru/api/v3/stocks/{$warehouse->id}";
            
            foreach ($chrtIdsChunks as $chunk) {
                $request = ["chrtIds" => $chunk];
                $response = $this->apiExecute($url, $request, 'POST');
    
                if (isset($response->stocks) && !empty($response->stocks)) {
                    foreach ($response->stocks as $item) {
                        if (!isset($stocksMap[$item->chrtId])) {
                            $stocksMap[$item->chrtId] = 0;
                        }
                        $stocksMap[$item->chrtId] += (int)$item->amount;
                    }
                }
                usleep(100000); 
            }
        }
    
        return $stocksMap;
    }

    private $categoryMap=[ 
        'Светильники'           =>'Потолочные',
        'Лампочки'              =>'Светодиодные лампы',
        'Настольные лампы'      =>'Настольные',
        'Прожекторы'            =>'Уличные',
        'Фонарики бытовые'      =>'Фонарики бытовые',
        'Удлинители'            =>'Удлинители',
        'Лампы для бытовой техники'
                                =>'Специальные',
        'Датчики для умного дома и систем безопасности' 
                                =>'Контроллеры',
        'Обогреватели'          =>'Обогреватели',
        'Тепловентиляторы'      =>'Обогреватели',
        'Умные лампочки'        =>'Специальные',
        'Споты и трек-системы'  =>'Споты и трек-системы',
        'Шинопроводы'           =>'Споты и трек-системы',
        'Брелоки'               =>'Брелоки',
        'Светильники уличные'   =>'Уличные',
        'Стяжка'                =>'Стяжки',
        'Вилки силовые'         =>'Разъемы',
        'Патроны для ламп'      =>'Разъемы',
        'Выключатели'           =>'Встраиваемые',
        'Комплектующие для светильников' 
                                =>'Споты и трек-системы',
        'Розетки'               =>'Встраиваемые',
        'Люстры'                =>'Люстры',
        'Клеммники'             =>'Разъемы',
        'Аварийное освещение'   =>'Специальные светильники',
        'Рамки для розеток и выключателей' 
                                =>'Рамки',
        'Диммеры'               =>'Диммеры',
        'Сетевые фильтры'       =>'Удлинители',
        'Разветвители электрические'
                                =>'Удлинители',
        'Фонари велосипедные'   => 'Фонарики бытовые',
        'Дверные звонки'        => 'Звонки',
        'Наборы крепежа'        => 'Стяжки',
        'Торшеры напольные'     => 'Напольные',
        'Светодиодные ленты'    => 'Лента светодиодная',
        'Светильники переносные'=> 'Уличные',
        'Фитолампы для растений и аксессуары' 
                                => 'Специальные светильники',
        'Ночники'               => 'Фонарики бытовые',
        'Шинопроводы'           => 'Споты и трек-системы',
        'Выключатели механические' 
                                => 'Встраиваемые',
        'Коннекторы шинопроводов' 
                                => 'Споты и трек-системы',
        'Коробки распределительные' => 'Распределительные коробки',
        'Фильтры воздушные' => 'Фильтры для авто',
        'Фильтры салонные' => 'Фильтры для авто',
        'Фильтры масляные' => 'Фильтры для авто',
        'Фильтры топливные' => 'Фильтры для авто',
        'Фильтры автомобильные' => 'Фильтры для авто',
        'Колодки автомобильные' => 'Колодки для авто',
        'Тормозные диски автомобильные' => 'Колодки для авто'
    ];
    private function filter(array $cards, array $priceMap = [], array $stocksMap = []) {
        $filtered = [];
        $promo_start=date('Y-m-d H:i:s');
        $promo_finish=date('Y-m-d H:i:s',time()+7*24*60*60);

        foreach ($cards as $card) {
            $nmID = $card->nmID;
            $price = $priceMap[$nmID]['price'] ?? 0;
            $promo = $priceMap[$nmID]['promo'] ?? 0;
            $chrtId = $card->sizes[0]->chrtID ?? null;
            $quantity = ($chrtId && isset($stocksMap[$chrtId])) ? $stocksMap[$chrtId] : 0;
            
            $images = [];
            if (!empty($card->photos)) {
                foreach($card->photos as $photo){
                    $images[] = $photo->big;
                }
            }
            if($quantity < 1){
                continue;
            }

            $filtered[] = [
                $nmID,                          // C1: external id
                $card->vendorCode,              // C2: 
                null,                           // C3: 
                $price,                         // C4: price
                $card->title ?? $card->vendorCode, // C5: name
                $card->description ?? '',       // C6: descr
                'шт',                           // C7: unit
                $this->categoryMap[$card->subjectName] ?? null, // C8: category
                implode(',', $images),          // C9: image_url
                1,                              // C10: is_counted
                $quantity,                      // C11: quantity
                $promo,                         // C12: promo,
                $promo_start,                   // C13: promo_start
                $promo_finish,                  // C13: promo_finish
            ];
        }
        return $filtered;
    }

    private function import($productList) {
        $token_data = session()->get('token_data');
        $holder_id = $token_data->token_holder_id ?? 0;

        $colconfig = (object)[
            'product_external_id'        => 'C1',
            'product_code'               => 'C2',
            'product_price'              => 'C4',
            'product_name'               => 'C5',
            'product_description'        => 'C6',
            'product_unit'               => 'C7',
            'product_category_name'      => 'C8',
            'product_image_url'          => 'C9',
            'is_counted'                 => 'C10',
            'product_quantity'           => 'C11',
            'product_promo_price'        => 'C12',
            'product_promo_start'        => 'C13',
            'product_promo_finish'       => 'C14',
        ];

        $ImporterModel = model('ImporterModel');
        $ImporterModel->itemCreateAsDisabled = false;
        $ImporterModel->itemImageCreateAsDisabled=false;
        $ImporterModel->listCreate($productList, 'store', $holder_id, 'product', 0);
        $result = $ImporterModel->listImport('store', $holder_id, 'product', $colconfig);

        return $this->respond($result);
    }

    public function reloadMissingImages(){
        $token_data = session()->get('token_data');
        $holder_id = $token_data->token_holder_id ?? 0;

        $ProductModel=model('ProductModel');
        $ProductModel->select("product_id,C9 source_list");
        $ProductModel->join('image_list', 'image_holder_id = product_id','left');
        $ProductModel->join('imported_list', 'target_id=product_id','left');
        $ProductModel->where('store_id',$holder_id);
        $ProductModel->where('image_id IS NULL');
        $missing_images=$ProductModel->get()->getResult();
        foreach($missing_images as $image){
            $ProductModel->itemReloadImage($image->product_id,$image->source_list);
        }
    }

    private function apiExecute(string $url, array $request = null, string $method = 'POST') {
        $headers = [
            "Authorization: ".$this->externalToken,
            "Content-Type: application/json"
        ];

        $curl = curl_init();
        if ($method === 'GET' && $request) {
            $url .= '?' . http_build_query($request);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method
        ]);

        if ($method === 'POST' && $request) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
        }

        $result = curl_exec($curl);
        curl_close($curl);
        return json_decode($result);
    }

    private function auth($token_hash) {
        $UserModel = model('UserModel');
        $result = $UserModel->signInByToken($token_hash, 'store');
        if ($result == 'ok') {
            $user = $UserModel->getSignedUser();
            if (!$user) return 'user_data_fetch_error';
            session()->set('user_id', $user->user_id);
            session()->set('user_data', $user);
        }
        return $result;
    }
    private function getStoreIdByToken($token_hash) {
        $TokenModel = model('TokenModel');
        $token = $TokenModel->where('token_hash', $token_hash)->where('token_holder', 'store')->where('is_disabled', 0)->get()->getRow();
    
        return $token ? $token->token_holder_id : null;
    }
    private function getSavedCursor(int $store_id) {
        $StoreModel = model('StoreModel');
        
        $StoreModel->select("JSON_UNQUOTE(JSON_EXTRACT(store_data, '$.wb_last_cursor')) as `last_cursor` ");
        $StoreModel->where('store_id', $store_id);
        
        $row = $StoreModel->get()->getRow();
    
        if ($row && $row->last_cursor && $row->last_cursor !== 'null') {
            return json_decode($row->last_cursor, true);
        }
        return null;
    }
    private function saveCursor(int $store_id, $cursor) {
        $cursorJson = $cursor ? json_encode($cursor) : null;
        $StoreModel = model('StoreModel');
        $sql = "UPDATE store_list 
                SET store_data = JSON_SET(IFNULL(store_data, '{}'), '$.wb_last_cursor', CAST(? AS JSON)) 
                WHERE store_id = ?";
                
        return $StoreModel->query($sql, [$cursorJson, $store_id]);
    }

    private function writeFile(string $fileName, array $data): bool {
        $dirPath = WRITEPATH . 'uploads/';
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0777, true);
        }
        
        $filePath = $dirPath . $fileName;
        $jsonStr = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        return file_put_contents($filePath, $jsonStr) !== false;
    }

    private function readFile(string $fileName): array {
        $filePath = WRITEPATH . 'uploads/' . $fileName;
        
        if (!file_exists($filePath)) {
            return [];
        }
        
        $fileContent = file_get_contents($filePath);
        if (empty($fileContent)) {
            return [];
        }
        
        return json_decode($fileContent, false) ?? [];
    }
}