<?php
namespace App\Controllers\Api;

use \CodeIgniter\API\ResponseTrait;

class WBExchange extends \App\Controllers\BaseController {
    
    use ResponseTrait;

    private $externalToken;
    
    private $max_exec_time = 600;
    private $batch_limit = 1000;

    public function dig() {
        set_time_limit($this->max_exec_time);

        $token_hash = $this->request->getVar('apikey');
        $wbToken = $this->request->getVar('token'); 
        
        $result = $this->auth($token_hash);
        if ($result !== 'ok') {
            return $this->failForbidden($result);
        }
        
        $store_id = $this->getStoreIdByToken($token_hash);
        if (!$store_id) {
            return $this->failUnauthorized('Invalid internal API Key');
        }

        if (!$wbToken) {
            return $this->fail('wb_token_required');
        }
        $this->externalToken = $wbToken;
        
        $cursor = $this->getSavedCursor($store_id);


        $cards = $this->apiProductsGet($cursor);
        if (empty($cards['cards'])) {
            $this->saveCursor($store_id, null);
            return $this->respond(['message' => 'No products found or API error']);
        }

        $warehouses = $this->getWarehouses();
        $prices = $this->apiPricesGet();
        $stocks = $this->apiStocksGet($warehouses, $cards['cards']);

        $productList = $this->filter($cards['cards'], $prices, $stocks);

        $this->import($productList);

        return $this->saveCursor($store_id, $cards['next_cursor']);
    }

    public function warehouseStatsGet() {
        $url = 'https://seller-analytics-api.wildberries.ru/api/v2/stocks-report/offices';
        $request = [
            "currentPeriod" => [
                "start" => "2024-02-10",
                "end" => "2024-02-10"
            ]

        ];

        $response = $this->apiExecute($url, $request, 'GET');
        if (empty($response) || !is_array($response)) {
            return $this->respond([
                'status' => 'error',
                'message' => 'Не удалось получить список складов. Проверьте токен (нужны права "Маркетплейс").',
                'raw' => $response
            ]);
        }
        return $response;
    }
    
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

    private function apiPricesGet() {
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
                $priceMap[$item->nmID] = [
                    'price' => $item->sizes[0]->price ?? 0,
                    'promo' => $item->sizes[0]->discountedPrice ?? 0
                ];
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
        'Светильники'           => 'Потолочные',
        'Лампочки'              => 'Средние',
        'Датчики для умного дома и систем безопасности' => 'Аксессуары',
        'Дверные звонки'        => 'Накладные',
        'Патроны для ламп'      => 'Потолочные',
        'Светильники уличные'   => 'Уличные',
        'Фонари велосипедные'   => 'Уличные',
        'Розетки'               => 'Встраиваемые',
        'Удлинители'            => 'Удлинители',
        'Вилки силовые'         => 'Аксессуары',
        'Клеммники'             => 'Изоляция',
        'Наборы крепежа'        => 'Изоляция',
        'Торшеры напольные'     => 'Настольные',
        'Светодиодные ленты'    => 'Настольные',
        'Брелоки'               => 'Аксессуары',
        'Светильники переносные'=> 'Уличные',
        'Диммеры'               => 'Встраиваемые',
        'Фитолампы для растений и аксессуары' => 'Настольные',
        'Ночники'               => 'Настольные',
        'Шинопроводы'           => 'Изоляция',
        'Выключатели механические' => 'Встраиваемые',
        'Рамки для розеток и выключателей' => 'Встраиваемые',
        'Коннекторы шинопроводов' => 'Аксессуары',
        'Комплектующие для светильников' => 'Настольные',
        'Фильтры воздушные' => '',
        'Фильтры салонные' => '',
        'Фильтры масляные' => '',
        'Фильтры топливные' => '',
        'Коробки распределительные' => '',
        'Фильтры автомобильные' => '',
        'Колодки автомобильные' => '',
        'Тормозные диски автомобильные' => ''
    ];
    private function filter(array $cards, array $priceMap = [], array $stocksMap = []) {
        $filtered = [];

        foreach ($cards as $card) {
            $nmID = $card->nmID;
            $price = $priceMap[$nmID]['price'] ?? 0;
            $chrtId = $card->sizes[0]->chrtID ?? null;
            $quantity = ($chrtId && isset($stocksMap[$chrtId])) ? $stocksMap[$chrtId] : 0;
            
            $imageUrl = null;
            if (!empty($card->photos)) {
                $imageUrl = $card->photos[0]->big;
            }

            $filtered[] = [
                $nmID,                          // C1: external id
                $card->vendorCode,              // C2: 
                null,                           // C3: 
                $price,                         // C4: price
                $card->title ?? $card->vendorCode, // C5: name
                $card->description ?? '',       // C6: descr
                'шт',                           // C7: unit
            $this->categoryMap[$card->subjectName] ?? '', // C8: category
                $imageUrl,                      // C9: image_url
                1,                              // C10: is_counted
                $quantity,                      // C11: quantity
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
        ];

        $ImporterModel = model('ImporterModel');
        $ImporterModel->itemCreateAsDisabled = false;
        $ImporterModel->itemImageCreateAsDisabled=false;
        $ImporterModel->listCreate($productList, 'store', $holder_id, 'product', 0);
        $result = $ImporterModel->listImport('store', $holder_id, 'product', $colconfig);

        return $this->respond($result);
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
}