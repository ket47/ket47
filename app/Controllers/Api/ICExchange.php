<?php

namespace App\Controllers\Api;

class ICExchange extends \App\Controllers\BaseController
{
    private $max_product_count=100;
    public function __construct(){
        $this->dir = WRITEPATH . 'uploads/';

        $this->start_time = microtime(true);
        $this->max_exec_time = min(30, @ini_get("max_execution_time"));
    }

    public function index(){
        $this->authenticateByToken();
        $this->prepareSubfolder();
        $this->methodExecute();
    }

    private function authenticateByToken(){
        $token_holder = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : "";
		$token_hash = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : "";
        $TokenModel=model('TokenModel');
        $token_data=$TokenModel->itemAuth($token_hash,$token_holder);
        if(!$token_data){
            http_response_code(401);
            die('fail');
        }
        $token_data->token_hash='';
        session()->set('auth_token_data',$token_data);
        session()->set('user_id',$token_data->owner_id);
    }

    private function prepareSubfolder(){
        $token_data=session()->get('auth_token_data');
        $filename_prefix=$token_data->token_holder.'-'.$token_data->token_holder_id;
        $this->filename_subfolder=$this->dir.'ic_exchange/'.$filename_prefix.'/';
        if( !is_dir($this->filename_subfolder) ){
            mkdir($this->filename_subfolder,0750,true);
        }
    }

    private function methodExecute(){
        $type = $this->request->getVar('type');
        $mode = $this->request->getVar('mode');
        $methodName = $type . ucfirst($mode);
        if (method_exists($this, $methodName)) {
            log_message('alert', '1C EXECUTION FUNCTION ' . $methodName);
            //log_message('critical','input '.file_get_contents('php://input'));
            $this->{$methodName}();
        } else {
            log_message('error', '1C EXECUTION ERROR: Undefined function' . $methodName);
        }
    }

    private function catalogCheckauth(){
        print "success\n";
        print session_name() . "\n";
        print session_id();
    }

    private function catalogInit(){
        $tmp_files = glob($this->filename_subfolder . '*.*');
        if (is_array($tmp_files)){
            foreach ($tmp_files as $v) {
                if($v=='..'||$v=='.'){
                    continue;
                }
                //is_dir($v)?rmdir($v):unlink($v);
            }
        }
        unset($_SESSION['last_1c_imported_variant_num']);
        unset($_SESSION['last_1c_imported_product_num']);
        unset($_SESSION['features_mapping']);
        unset($_SESSION['categories_mapping']);
        unset($_SESSION['brand_id_option']);
        print "zip=yes\n";
        print "file_limit=1000000\n";
    }

    private function catalogFile(){
        $filename_ic = $this->request->getVar('filename');
        $dirname=dirname($filename_ic);
        if($dirname=='.'){
            $dirname='';
        }
        $filename=$this->filename_subfolder.$filename_ic;

        if( str_contains('..',$filename_ic) ){
            die('illegal_filename');
        }
        if( !is_dir($this->filename_subfolder.$dirname) ){
            mkdir($this->filename_subfolder.$dirname,0750,true);
        }
        file_put_contents($filename,file_get_contents('php://input'));

        if( str_contains($filename_ic,'.zip') ){
            $this->catalogFileUnzip($filename);
        }
        print "success\n";
    }

    private function catalogFileUnzip($filename){
        $zip = new \ZipArchive;
        if ($zip->open($filename) === TRUE) {
            $zip->extractTo($this->filename_subfolder);
            $zip->close();
            echo 'success';
            unlink($filename);
        } else {
            echo 'failure';
        }
        die;
    }

    private function catalogImport($filename=null){
        $filename_ic = $this->request->getVar('filename');
        $filename=$this->filename_subfolder.$filename_ic;

        $holder_id=session()->get('auth_token_data')?->token_holder_id;
        $ICExchangeProductModel=new ICExchangeProductModel();

        if (str_contains($filename,'import')) {
            // Товары 			
            $z = new \XMLReader;
            $z->open($filename);
            while ($z->read() && $z->name !== 'Товар');


            $current_product_num = 0;// Номер текущего товара
            $last_product_num = 0;// Последний товар, на котором остановились
            if (isset($_SESSION['last_1c_imported_product_num'])){
                $last_product_num = $_SESSION['last_1c_imported_product_num'];
            }
            $ICExchangeProductModel->transStart();
            while ($z->name === 'Товар' && $current_product_num<$this->max_product_count) {
                if ($current_product_num >= $last_product_num) {
                    $xml = new \SimpleXMLElement($z->readOuterXML());
                    $ICExchangeProductModel->productSave($xml,$holder_id);
                    // Товары
                    $exec_time = microtime(true) - $this->start_time;
                    if ($exec_time + 1 >= $this->max_exec_time) {
                        header("Content-type: text/xml; charset=utf-8");
                        print "\xEF\xBB\xBF";
                        print "progress\r\n";
                        print "Выгружено товаров: $current_product_num\r\n";
                        $_SESSION['last_1c_imported_product_num'] = $current_product_num;
                        $ICExchangeProductModel->transComplete();
                        exit();
                    }
                }
                $z->next('Товар');
                $current_product_num++;
            }
            $ICExchangeProductModel->transComplete();
            $z->close();
            print "success";
            unlink($filename);
            unset($_SESSION['last_1c_imported_product_num']);
        }
        elseif (str_contains($filename,'offers')) {
            // Варианты			
            $z = new \XMLReader;
            $z->open($filename);
            while ($z->read() && $z->name !== 'Предложение');

            $current_variant_num = 0;// Номер текущего товара
            $last_variant_num = 0;// Последний вариант, на котором остановились
            if (isset($_SESSION['last_1c_imported_variant_num'])){
                $last_variant_num = $_SESSION['last_1c_imported_variant_num'];
            }

            $ICExchangeProductModel->transStart();
            while ($z->name === 'Предложение' && $current_variant_num<$this->max_product_count) {
                if ($current_variant_num >= $last_variant_num) {
                    $xml = new \SimpleXMLElement($z->readOuterXML());
                    // Варианты
                    $ICExchangeProductModel->productSave($xml,$holder_id);
                    $exec_time = microtime(true) - $this->start_time;
                    if ($exec_time + 1 >= $this->max_exec_time) {
                        header("Content-type: text/xml; charset=utf-8");
                        print "\xEF\xBB\xBF";
                        print "progress\r\n";
                        print "Выгружено ценовых предложений: $current_variant_num\r\n";
                        $_SESSION['last_1c_imported_variant_num'] = $current_variant_num;
                        $ICExchangeProductModel->transComplete();
                        exit();
                    }
                }
                $z->next('Предложение');
                $current_variant_num++;
            }
            $ICExchangeProductModel->transComplete();
            $z->close();
            print "success";
            unlink($filename);
            unset($_SESSION['last_1c_imported_variant_num']);
        }
    }


    ////////////////////////////////////////////////////////////
    //ORDER SYNC
    ////////////////////////////////////////////////////////////
    private function saleCheckauth(){
        print "success\n";
        print session_name()."\n";
        print session_id();
    }

    private function saleInit(){
        $tmp_files = glob($this->filename_subfolder . '*.*');
        if (is_array($tmp_files)){
            foreach ($tmp_files as $v) {
                if($v=='..'||$v=='.'){
                    continue;
                }
                //is_dir($v)?rmdir($v):unlink($v);
            }
        }
        print "zip=no\n";
        print "file_limit=1000000\n";
    }


    private function saleFinalStageDetect($xml_order){
        if (isset($xml_order->ЗначенияРеквизитов->ЗначениеРеквизита)){
            foreach ($xml_order->ЗначенияРеквизитов->ЗначениеРеквизита as $r) {
                if($r->Наименование=='Проведен' && $r->Значение == 'true'){
                    return 'supplier_finish';
                }
                if($r->Наименование=='ПометкаУдаления' && $r->Значение == 'true'){
                    return 'supplier_rejected';
                }
            }
        }
    }

    private function saleCorrectionsDetect($xml_order){
        $order_id=(int) $xml_order->Номер;
        $EntryModel=model('EntryModel');
        $EntryModel->listGetSelectedFields.=',product_external_id,product_code';
        $entries=$EntryModel->listGet($order_id);

        $entry_corrections=[];
        foreach($entries as $entry){
            $entry_absent_in_1c=true;
            foreach ($xml_order->Товары->Товар as $xml_product) {
                @list($product_1c_id, $variant_1c_id) = explode('#', $xml_product->Ид);
                if($entry->product_external_id==$product_1c_id || $entry->product_code==$xml_product->Артикул){
                    $entry_absent_in_1c=false;
                    if($entry->entry_quantity>$xml_product->Количество){
                        $entry_corrections[]=(object)[
                            'entry_id'=>$entry->entry_id,
                            'entry_quantity'=>$xml_product->Количество
                        ];
                    }
                }
            }
            if($entry_absent_in_1c==true){
                $entry_corrections[]=(object)[
                    'entry_id'=>$entry->entry_id,
                    'entry_quantity'=>0
                ];
            }
        }
        return $entry_corrections;
    }

    private function saleFile(){
        $filename_ic = $this->request->getVar('filename');
        $filename=$this->filename_subfolder.$filename_ic;
        if( str_contains('..',$filename_ic) ){
            die('illegal_filename');
        }
        file_put_contents($filename,file_get_contents('php://input'));

        $OrderModel=model('OrderModel');
        $EntryModel=model('EntryModel');
        $xml = simplexml_load_file($filename);
        foreach ($xml->Документ as $xml_order) {
            $order_id=(int) $xml_order->Номер;
            $order=$OrderModel->itemGet($order_id);
            if($order=='notfound'){
                continue;
            }
            if( !in_array($order->stage_current,['customer_start','supplier_start','supplier_corrected','supplier_finish']) ){
                continue;
            }
            $order_final_stage=$this->saleFinalStageDetect($xml_order);
            if($order_final_stage=='supplier_rejected'){
                $OrderModel->itemStageCreate($order_id,'supplier_rejected');
                continue;
            }

            if( $order->stage_current=='customer_start' ){
                $result=$OrderModel->itemStageCreate($order_id,'supplier_start');
                if($result=='ok'){
                    $order->stage_current='supplier_start';
                }
            }

            $entry_corrections=$this->saleCorrectionsDetect($xml_order);
            print_r($entry_corrections);
            if( $entry_corrections ){
                if( in_array($order->stage_current,['supplier_start','supplier_finish']) ){
                    $result=$OrderModel->itemStageCreate($order_id,'supplier_corrected');
                    if($result=='ok'){
                        $order->stage_current='supplier_corrected';
                    }
                }
                foreach($entry_corrections as $correction){//IF quantity is 0 ??? then dont delete to show customer that entry was nulled
                    $EntryModel->itemUpdate($correction);
                }
            }
            if($order_final_stage=='supplier_finish'){
                $OrderModel->itemStageCreate($order_id,'supplier_finish');
            }
        }
        print 'success';
    }
    private function saleImport(){
        print "success";
    }

    private function saleSuccess(){
        print "success";
    }

    private function saleQuery(){
        $store_id=session()->get('auth_token_data')?->token_holder_id;
        $filter=[
            'order_store_id'=>$store_id,
        ];
        $OrderModel=model('OrderModel');
        $EntryModel=model('EntryModel');
        $StoreModel=model('StoreModel');

        $OrderModel->where('order_list.updated_at>',date('Y-m-d H:i:s',time()-1*24*60*60));//one day ago
        $orders=$OrderModel->listGet($filter);
        $store=$StoreModel->itemGet($store_id,'basic');

        $no_spaces = '<?xml version="1.0" encoding="utf-8"?>
							<КоммерческаяИнформация ВерсияСхемы="2.04" ДатаФормирования="' . date ( 'Y-m-d' )  . '"></КоммерческаяИнформация>';
		$xml = new \SimpleXMLElement ( $no_spaces );
		foreach($orders as $order){
            //Новый Принят Доставлен Отменен
            $order_status="skip";
            if(in_array($order->stage_current,['customer_start'])){
                $order_status="new";
            } else 
            if(in_array($order->stage_current,['supplier_start','supplier_corrected','supplier_finish'])){
                $order_status="accepted";
            } else 
            if(in_array($order->stage_current,['customer_finish'])){
                $order_status="delivered";
            } else 
            if(in_array($order->stage_current,['supplier_rejected','supplier_reclaimed','delivery_rejected','delivery_no_courier','customer_disputed','customer_rejected'])){
                $order_status="canceled";
            }
            if( $order_status=='skip' ){
                continue;
            }
			$date = new \DateTime($order->created_at);
            $store_commission_sum=$order->order_sum_total*(1- ($store->store_commission??0)/100);
            $payment_method = "Перечисление на расчетный счет";
            $delivery_method = "Tezkel Экспресс доставка";
            $order_comment=$order->order_description."\n".$order->order_objection."\n-------------------\n"."Услуги доставки: ".$store_commission_sum;

            $doc = $xml->addChild ("Документ");
			$doc->addChild ( "Ид", $order->order_id);
			$doc->addChild ( "Номер", $order->order_id);
			$doc->addChild ( "Дата", $date->format('Y-m-d'));
			$doc->addChild ( "ХозОперация", "Заказ товара" );
			$doc->addChild ( "Роль", "Продавец" );
			$doc->addChild ( "Валюта", 'RUB');
			$doc->addChild ( "Курс", "1" );
			$doc->addChild ( "Сумма", $order->order_sum_product);
			$doc->addChild ( "Время",  $date->format('H:i:s'));
			$doc->addChild ( "Комментарий", $order_comment);

			// Контрагенты
			$k1 = $doc->addChild ( 'Контрагенты' );
			$k1_1 = $k1->addChild ( 'Контрагент' );
			$k1_1->addChild ( "Ид", 'Конечный потребитель (через Тезкель)');
			$k1_1->addChild ( "Наименование", 'Конечный потребитель (через Тезкель)');
			$k1_1->addChild ( "Роль", "Покупатель" );
			$k1_1->addChild ( "ПолноеНаименование", 'Конечный потребитель (через Тезкель)' );

            //Entries
            $EntryModel->listGetSelectedFields.=',product_external_id,product_code';
            $entries=$EntryModel->listGet($order->order_id);
			$t1 = $doc->addChild ( 'Товары' );
			foreach($entries as $entry){
                $product_id=$entry->product_external_id??$entry->product_id;
                $t1_1 = $t1->addChild ( 'Товар' );
				$t1_2 = $t1_1->addChild ( "Ид", $product_id);
                $t1_2 = $t1_1->addChild ( "Артикул", $entry->product_code);
                $t1_2 = $t1_1->addChild ( "Наименование", $entry->entry_text);
                $t1_2 = $t1_1->addChild ( "ЦенаЗаЕдиницу", $entry->entry_price);
                $t1_2 = $t1_1->addChild ( "Количество", $entry->entry_quantity);
                $t1_2 = $t1_1->addChild ( "Сумма", $entry->entry_sum);
                $t1_2 = $t1_1->addChild ( "ЗначенияРеквизитов" );
                $t1_3 = $t1_2->addChild ( "ЗначениеРеквизита" );
                $t1_4 = $t1_3->addChild ( "Наименование", "ВидНоменклатуры" );
                $t1_4 = $t1_3->addChild ( "Значение", "Товар" );

                $t1_2 = $t1_1->addChild ( "ЗначенияРеквизитов" );
                $t1_3 = $t1_2->addChild ( "ЗначениеРеквизита" );
                $t1_4 = $t1_3->addChild ( "Наименование", "ТипНоменклатуры" );
                $t1_4 = $t1_3->addChild ( "Значение", "Товар" );
            }
        
            // Доставка
            // if( $store_commission_sum>0 ){
            //     $t1 = $t1->addChild ( 'Товар' );
            //     $t1->addChild ( "Ид", 'ORDER_DELIVERY');
            //     $t1->addChild ( "Наименование", 'Доставка');
            //     $t1->addChild ( "ЦенаЗаЕдиницу", $store_commission_sum);
            //     $t1->addChild ( "Количество", 1 );
            //     $t1->addChild ( "Сумма", $store_commission_sum);
            //     $t1_2 = $t1->addChild ( "ЗначенияРеквизитов" );
            //     $t1_3 = $t1_2->addChild ( "ЗначениеРеквизита" );
            //     $t1_4 = $t1_3->addChild ( "Наименование", "ВидНоменклатуры" );
            //     $t1_4 = $t1_3->addChild ( "Значение", "Услуга" );

            //     $t1_2 = $t1->addChild ( "ЗначенияРеквизитов" );
            //     $t1_3 = $t1_2->addChild ( "ЗначениеРеквизита" );
            //     $t1_4 = $t1_3->addChild ( "Наименование", "ТипНоменклатуры" );
            //     $t1_4 = $t1_3->addChild ( "Значение", "Услуга" );
            // }
                
            // Способ оплаты и доставки
            $s1_2 = $doc->addChild ( "ЗначенияРеквизитов");
            if($payment_method){
                $s1_3 = $s1_2->addChild ( "ЗначениеРеквизита");
                $s1_3->addChild ( "Наименование", "Метод оплаты" );
                $s1_3->addChild ( "Значение", $payment_method );
            }
            if($delivery_method){
                $s1_3 = $s1_2->addChild ( "ЗначениеРеквизита");
                $s1_3->addChild ( "Наименование", "Способ доставки" );
                $s1_3->addChild ( "Значение", $delivery_method);
            }
            $s1_3 = $s1_2->addChild ( "ЗначениеРеквизита");
            $s1_3->addChild ( "Наименование", "Заказ оплачен" );
            $s1_3->addChild ( "Значение", 'true' );


            // Статус			
            if($order_status == 'new'){
                $s1_3 = $s1_2->addChild ( "ЗначениеРеквизита" );
                $s1_3->addChild ( "Наименование", "Статус заказа" );
                $s1_3->addChild ( "Значение", "Новый" );
            }
            if($order_status == 'accepted'){
                $s1_3 = $s1_2->addChild ( "ЗначениеРеквизита" );
                $s1_3->addChild ( "Наименование", "Статус заказа" );
                $s1_3->addChild ( "Значение", "[N] Принят" );
            }
            if($order_status == 'delivered'){
                $s1_3 = $s1_2->addChild ( "ЗначениеРеквизита" );
                $s1_3->addChild ( "Наименование", "Статус заказа" );
                $s1_3->addChild ( "Значение", "[F] Доставлен" );
            }
            if($order_status == 'canceled'){
                $s1_3 = $s1_2->addChild ( "ЗначениеРеквизита" );
                $s1_3->addChild ( "Наименование", "Отменен" );
                $s1_3->addChild ( "Значение", "true" );
            }
        }
		header( "Content-type: text/xml; charset=utf-8" );
		print "\xEF\xBB\xBF";
		print $xml->asXML();
    }
}
