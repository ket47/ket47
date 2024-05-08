<?php
namespace App\Libraries;
class CashierKitOnline{
    private $url_api = "https://api.kit-invest.ru/WebService.svc/";
    private $checkNumPrefix="#";
    public function print($order_all){
        helper('phone_number');
        $Check=[
            'CheckId'=>$this->checkNumPrefix.$order_all->order_id,
            'TaxSystemType'=>getenv('kitonline.TaxSystemType'),
            'CalculationType'=>'1',
            'Sum'=>$order_all->order_sum_total*100,
            'Phone'=>$order_all->customer->user_phone,
            'Pay'=>[
                'CashSum'=>0,
                'EMoneySum'=>$order_all->order_sum_total*100,
            ],
            'Subjects'=>[]
        ];
        $order_sum_calculated=0;
        $discount_modifier=1;
        if($order_all->order_sum_promo>0){
            $discount_modifier=(1-$order_all->order_sum_promo/$order_all->order_sum_product);
        }
        foreach($order_all->entries as $entry){
            if($entry->entry_quantity==0){
                continue;
            }
            if( $entry->entry_discount>0 && $entry->entry_discount<$entry->entry_price ){
                $entry->entry_price-=round($entry->entry_discount/$entry->entry_quantity,2);
            }
            $product_row=[
                'SubjectName'=>$entry->entry_text,
                'Price'=>round($entry->entry_price*100*$discount_modifier),
                'Quantity'=>$entry->entry_quantity,
                'Tax'=>6,//without VAT,
                'GoodsAttribute'=>1,//Products
                'unitOfMeasurement'=>$entry->product_unit,
            ];

            $tezkel_tax_num="9102283121";
            if( $tezkel_tax_num!=$order_all->store->store_tax_num ){//tezkel
                $product_row['supplierINN']=$order_all->store->store_tax_num;
                $product_row['supplierInfo']=[
                    'name'=>$order_all->store->store_company_name,
                    'phoneNumbers'=>[clearPhone($order_all->store->store_phone)]
                ];
                $product_row['agentType']=64;
                $product_row['agentInfo']=[
                    //'paymentAgentOperation'=>getenv('kitonline.paymentAgentOperation'),
                    'paymentAgentPhoneNumbers'=>[clearPhone(getenv('kitonline.paymentAgentPhoneNumbers'))]
                ];
            }
            $Check['Subjects'][]=$product_row;
            $order_sum_calculated+=round(round($entry->entry_price*100*$discount_modifier)*$entry->entry_quantity);
        }
        if($order_all->order_sum_delivery>0){
            $delivery_row=[
                'SubjectName'=>'Услуга доставки заказа',
                'Price'=>$order_all->order_sum_delivery*100,
                'Quantity'=>1,
                'Tax'=>6,//without VAT,
                'GoodsAttribute'=>4,//Service
                'unitOfMeasurement'=>"шт",
            ];
            if($order_all->print_delivery_as_agent){
                $delivery_row['supplierINN']=$order_all->store->store_tax_num;
                $delivery_row['supplierInfo']=[
                    'name'=>$order_all->store->store_company_name,
                    'phoneNumbers'=>[clearPhone($order_all->store->store_phone)]
                ];
                $delivery_row['agentType']=64;
                $delivery_row['agentInfo']=[
                    //'paymentAgentOperation'=>getenv('kitonline.paymentAgentOperation'),
                    'paymentAgentPhoneNumbers'=>[clearPhone(getenv('kitonline.paymentAgentPhoneNumbers'))]
                ];
            }
            $Check['Subjects'][]=$delivery_row;

            $order_sum_calculated+=round($order_all->order_sum_delivery*100);
        }
        if( !count($Check['Subjects']) ){
            return 'noentries';
        }
        $order_sum_error=$Check['Sum']-$order_sum_calculated;
        if( $order_sum_error!=0 ){
            $Check=$this->correcterEntryCreate($Check,$order_sum_error);
        }
        $data=[
            'Request'=>[
                'FiscalData'=>1,
                'Link'=>1,
                'QRCode'=>1,
            ],
            'Check'=>$Check
        ];
        $response=$this->apiExecute($this->checkNumPrefix.$order_all->order_id,'SendCheck',$data);
        if($response){
            //$response->Check=$Check;
        }
        return $response;
    }

    private function correcterEntryCreate($Check,$order_sum_error){
        if( $Check['Subjects'][0]['Quantity']==1 ){
            $Check['Subjects'][0]['Price']+=$order_sum_error;
            return $Check;
        }
        if( $Check['Subjects'][0]['Quantity']>1 ){
            $Check['Subjects'][0]['Quantity']--;
            $correcter_entry=$Check['Subjects'][0];
            $correcter_entry['Quantity']=1;
            $correcter_entry['Price']+=$order_sum_error;
            array_unshift($Check['Subjects'],$correcter_entry);
            return $Check;
        }
        if( $Check['Subjects'][0]['Quantity']<1 ){
            $target_subtotal=round($Check['Subjects'][0]['Quantity']*$Check['Subjects'][0]['Price'])+$order_sum_error;
            $row_quantity=$Check['Subjects'][0]['Quantity'];
            $row_price=$Check['Subjects'][0]['Price'];

            for($delta=0.001;$delta<$row_quantity;$delta+=0.001){
                $row_quantity=round($row_quantity-$delta,3);

                $correction_quantity=round($delta,3);
                $correction_price=round( ($target_subtotal-round($row_quantity*$row_price))/$correction_quantity );
                
                if($target_subtotal==round($row_quantity*$row_price)+round($correction_quantity*$correction_price)){
                    break;
                }
            }
            $Check['Subjects'][0]['Quantity']=$row_quantity;
            $correcter_entry=$Check['Subjects'][0];
            $correcter_entry['Quantity']=$correction_quantity;
            $correcter_entry['Price']=$correction_price;
            array_unshift($Check['Subjects'],$correcter_entry);
            return $Check;
        }
    }

    public function statusGet($CheckNumber){
        $data=[
            'Request'=>[
                'FiscalData'=>1,
                'Link'=>1,
                'QRCode'=>1,
            ],
            'CheckNumber'=>$CheckNumber
        ];
        return $this->apiExecute($CheckNumber,'StateCheck',$data);
    }

    public function printAndGet($order_all){
        $response=$this->print($order_all);
        if( !isset($response->ResultCode) || $response->ResultCode!=0 ){
            pl(["KIT ONLINE CHECK PRINT FAILED",$response],false);
            return $response;
        }
        $atempt_count=10;
        while($atempt_count>0){
            $atempt_count--;
            sleep(2);
            $response->Registration=$this->statusGet($this->checkNumPrefix.$order_all->order_id);
            if($response->Registration->ResultCode!=0){
                continue;
            }
            if($response->Registration->CheckState->State<1000){
                continue;
            }
            return $response;
        }
    }

    private function apiExecute($CheckNumber,$method,$data){
        $url = $this->url_api.$method;
        $data['Request']['CompanyId']=getenv('kitonline.CompanyId');
        $data['Request']['UserLogin']=getenv('kitonline.UserLogin');
        $data['Request']['Sign']=md5(getenv('kitonline.CompanyId').getenv('kitonline.Password').$CheckNumber);
        //echo json_encode($data);
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json",
                'method'  => 'POST',
                'content' => json_encode($data)
            )
        );
        $context  = stream_context_create($options);
        for($i=0;$i<5;$i++){
            try{
                $response_text = file_get_contents($url, false, $context);
                $response=json_decode($response_text);
                if(($response->ResultCode??'error')!=0){
                    pl($data);
                    log_message('critical',"CashierKitOnline on Order #{$CheckNumber} api ResultCode is:".$response->ResultCode);
                }
                return $response;
            }catch(\Throwable $e){
                log_message('error',"CashierKitOnline on Order #{$CheckNumber} try #{$i} api Error".$e->getMessage());
                sleep(2);
                continue;
            }
        }
    }
}