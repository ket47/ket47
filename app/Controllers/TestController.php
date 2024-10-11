<?php
namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

if(getenv('CI_ENVIRONMENT')!=='development'){
    die('!!!');
}


class TestController extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function schedule(){


        $store=model('StoreModel')->itemTimetableGet(119);


        $DeliveryJobPlan=new \App\Libraries\DeliveryJobPlan();
        $DeliveryJobPlan->scheduleFillShift();
        $DeliveryJobPlan->scheduleFillTimetable($store);

        $roundto=15*60;
        print_r($DeliveryJobPlan->schedule->firstGet());
        print_r($DeliveryJobPlan->schedule->tableGet());
        print_r($DeliveryJobPlan->schedule->swatchGet( $roundto ));
    }





    public function push(){
        $FirePush=new \App\Libraries\FirePushKreait;
        $result=$FirePush->sendPush((object)[
            'token'=>[
                //'ebzdBTFTu-Ob2sByx05HK_:APA91bHbf1fGGk7ogzaxPHOdHr1HVRyHmsD3PbBFvqJkXZZP38deNZe1GodM3ft1XguNIL2Oe3CQ77N1AdccKYxFvuvDUYZIAL8K1MwHusudB03RVZBhb9Z9QQwedVZNSHgP1ecdrgQo',//chrome
                //'fXF3H6KOobooZA-1h_Ttc0:APA91bGeqZyCTWxqV42R5-ez-c5GfSC7WcyXUpmCc6g-WBuAGPrZNtGD8edMNEwXpzGfn-kvwZVn_xfJVEquRJ0iUYMheJ_NJlW9YohUFjpvNmuSYwasOV55xpow4esoMH8aXMCc3qsZ',//ff
                //'cWA_U33JZ03Gs7k6xR64KD:APA91bH6usj5faG2y6slVaF7JeBR30BhVJoPFOTnKfyo6SWvhHsJc8u1iqafGJzr2jzC5QW0pZqeECnxc1NEx1yAM_xyZZ7StsWCrRa6Sw_xuHWUTMQZ34Q_iH26gacRSjFzR1TYX8nG',//edge
                'e4rGekudoEx-j0BBgIlW9C:APA91bFjTmjC7Qa_D7DQLjP4v71Nr1j3a9d7PZ7idTjeetnjyNshljwLze1Aj06F5thyBx3CDhdm2_bgegDZm9IEXE-_xlIuUcmAr2YkVE31QreEUcpSGsnzs3LmnU9bZqyc3aMq823_',//ios
                //'cpExRJbHRxm8iSR6VBWWwq:APA91bFJO85Ss1_AYy80vzCP5ZtMEUAV4fcHlmWE0T8pSq3_maG7Ii2IbqA905Ge-br-JcgVr6aZ6QZ8wDADsoK45pSIQMMq6cxBNoBerrGhGTecXeyZF-VArwWZxaQNgHeu0_H_csK4',//android
            ],
            'title'=>'TeSt PuSh',
            'body'=>'Test body '.date("H:i:s"),
            'data'=>[
                 'link'=>'/catalog/product-1615',
                 'tag'=>'#orderStatus',
                 'image'=>'https://api.tezkel.com/image/get.php/fafa5407eaf897fd8b2d378e6c011f42.600.600.jpg',
                 //'icon'=>'default_notification_icon',
                 'sound'=>'long.wav',

                //  'topic'=>'pushStageChanged',
                //  'order_id'=>2457,
                  'orderActiveCount'=>55,
                //  'stage'=>'customer_start',
            ],
        ]);

        header("Refresh:15");
        echo $result;
    }
    public function push2(){
        $Messenger=new \App\Libraries\Messenger;
        $result=$Messenger->itemSend((object)[
            'message_reciever_id'=>41,
            'message_transport'=>'push',
            'message_subject'=>'TeSt PuSh',
            'message_text'=>'Test body '.date("H:i:s"),
            'message_data'=>(object)[
                 'link'=>'https://tezkel.com/catalog/product-1615',
                 'tag'=>'#orderStatus',
                 'image'=>'https://api.tezkel.com/image/get.php/fafa5407eaf897fd8b2d378e6c011f42.600.600.jpg',
                 //'icon'=>'default_notification_icon',
                 'sound'=>'short.wav',

                  'topic'=>'pushStageChanged',
                //  'order_id'=>2457,
                  'orderActiveCount'=>55,
                  'stage'=>'customer_start',
            ],
        ]);

        header("Refresh:15");
        echo $result;
    }

    public function emailSend(){
        $Messenger=new \App\Libraries\Messenger;
        $result=$Messenger->itemSend((object)[
            'message_reciever_id'=>43,
            'message_transport'=>'email',
            'message_subject'=>'TeSt PuSh',
            'message_text'=>'Test body '.date("H:i:s"),
            'message_data'=>(object)[
                'link'=>"",
                'image'=>"https:\/\/tezkel.local\/image\/get.php\/57a7c251bc189a5ca8681176b3827d81.1000.1000.webp",
                'sound'=>""
            ],
        ]);

        //header("Refresh:15");
        echo $result;
    }
    public function telegramSend(){
        $Messenger=new \App\Libraries\Messenger;
        $result=$Messenger->itemSend((object)[
            'message_reciever_id'=>43,
            'message_transport'=>'telegram',
            'message_subject'=>'TeSt PuShs',
            'message_text'=>'Test body '.date("H:i:s"),
            'message_data'=>(object)[
                'link'=>"",
                'image'=>"https://api.tezkel.com/image/get.php/b3bf2e8c1b58c3f381b1d2c64f6ac844.150.150.webp",
                'sound'=>""
            ],
        ]);

        //header("Refresh:15");
        echo $result;
    }
    public function testMailingNightly(){
        
        $MailingModel=model('MailingModel');


        $result=$MailingModel->nightlyCalculate();
        

        //header("Refresh:15");
        echo $result;
    }
    

    public function shiftCalc(){
        $CourierShiftModel=model('CourierShiftModel');


        $result=$CourierShiftModel->itemReportSend(544);
        return $this->respond($result);
    }

    // private $order_id=4880;
    // public function rncbLink(){
    //     $OrderModel=model('OrderModel');

    //     $order_all=$OrderModel->itemGet($this->order_id);
    //     $Acquirer=\Config\Services::acquirer();
    //     $link=$Acquirer->linkGet($order_all);
    //     header("Location: $link");
    // }

    // public function rncbStatus(){
    //     $OrderModel=model('OrderModel');

    //     $order_all=$OrderModel->itemGet($this->order_id);
    //     $Acquirer=\Config\Services::acquirer();
    //     $paymentStatus=$Acquirer->statusGet($order_all->order_id);
    //     return $this->respond($paymentStatus);
    // }


    // public function rncbDo(){
    //     $OrderModel=model('OrderModel');
    //     $order_data=$OrderModel->itemDataGet($this->order_id);

    //     $order_sum=(float)$order_data->payment_card_fixate_sum;
    //     $refund=(float)105;
    //     $confirm=$order_sum-$refund;

    //     $isFullRefund=($refund==$order_sum)?1:0;
    //     $isFullConfirm=($confirm==$order_sum)?1:0;


    //     $Acquirer=\Config\Services::acquirer();
    //     $ref=$Acquirer->refund($order_data->payment_card_fixate_id,$refund,$isFullRefund);
    //     $con=$Acquirer->confirm($order_data->payment_card_fixate_id,$order_sum-$refund);

    //     $paymentStatus=$Acquirer->statusGet($this->order_id);
    //     p([$ref,$con,$paymentStatus,]);
    // }


    // public function rncbPay(){
    //     $OrderModel=model('OrderModel');
    //     $order_all=$OrderModel->itemGet($this->order_id);

    //     $order_sum=(float)50000;$order_all->order_sum_total;
    //     $refund=(float)405;
    //     $confirm=$order_sum-$refund;

    //     $isFullRefund=($refund==$order_sum)?1:0;
    //     $isFullConfirm=($confirm==$order_sum)?1:0;

    //     $Acquirer=new \App\Libraries\AcquirerRncb();

    //     $orderData=(object)[
    //         "payment_card_fixate_id"=>null,
    //     ];
    //     $OrderModel->fieldUpdateAllow('order_data');
    //     $OrderModel->itemDataUpdate($order_all->order_id,$orderData);



    //     $auth=$Acquirer->pay($order_all);
    //     if( $auth!='ok' ){
    //         return $this->fail($auth);
    //     }
    //     $order_data=$OrderModel->itemDataGet($this->order_id);

    //     $ref=$Acquirer->refund($order_data->payment_card_fixate_id,$refund,$isFullRefund);
    //     $con=$Acquirer->confirm($order_data->payment_card_fixate_id,$order_sum-$refund);

    //     $paymentStatus=$Acquirer->statusGet($this->order_id);
    //     p([$auth,$ref,$con,$paymentStatus,]);
    // }

        // public function courierTest(){
        //     $order_id=4943;
        //     $order_courier_id=12;

        //     $OrderGroupMemberModel=model('OrderGroupMemberModel');
        //     $OrderModel=model('OrderModel');
        //     $CourierModel=model('CourierModel');

        //     $OrderGroupMemberModel->leaveGroupByType($order_id,'delivery_search');

        //     $OrderModel->itemStageAdd($order_id,'delivery_search');

        //     $CourierModel->itemUpdateStatus($order_courier_id,'ready');
        // }

    // public function capgo(){
    //     $request=[
    //         "platform"=>"ios",
    //         "device_id"=>"UUID_of_device_unique_by_install",
    //         "custom_id"=>"your_custom_id_set_on_runtime",
    //         "plugin_version"=>"PLUGIN_VERSION",
    //         "version_build"=>"1.15",
    //         "version_code"=>"15",
    //         "version_name"=>"hastalavista",
    //         "version_os"=>"VERSION_OF_SYSYEM_OS",
    //         "is_emulator"=>0,
    //         "is_prod"=>1,
    //     ];
    //     $this->apiExecute("https://tezkel.com/yani.php?bundle=check_new",$request);
    // }



    // public function apiExecute( string $url, array $request=null, string $method='POST' ){
    //     $curl = curl_init(); 
    //     switch( $method ){
    //         case 'POST':
    //             curl_setopt($curl, CURLOPT_POST, 1);
    //             if( $request ){
    //                 curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
    //                 $headers[]="Content-Type: application/json";
    //             }
    //             break;
    //     }
    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //     curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    //     curl_setopt($curl, CURLINFO_HEADER_OUT, true);
    //     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    //    echo  $result = curl_exec($curl);
    //     //p(curl_getinfo($curl));
    //     if( curl_error($curl) ){
    //         log_message("ERROR","$url API Execute error: ".curl_error($curl));
    //         die(curl_error($curl));
    //     }
    //     curl_close($curl);
    //     return json_decode($result);
    // }


    public function courierNotify(){
        $DeliveryJobModel=model("DeliveryJobModel");
        $DeliveryJobModel->itemAvailableNotify();
    }


    public function color(){
        $clasterBoundaries=getenv("location.claster1");
        $claster=json_decode("[$clasterBoundaries]");
        $deltaX=$claster[1][0]-$claster[0][0];
        $deltaY=$claster[1][1]-$claster[0][1];

        // $lon=$claster[0][1]+$deltaX*rand(0,1000)/1000;
        // $lat=$claster[0][0]+$deltaY*rand(0,1000)/1000;

        $col=new \App\Libraries\Coords2Color();
        echo "<table cellspacing=0>";
        for($i=100;$i>=0;$i--){

            echo '<tr>';
            for($k=0;$k<=100;$k++){
                $lon=$claster[0][0]+$deltaX/100*$k;
                $lat=$claster[0][1]+$deltaY/100*$i;
                $color="";
                $in=$i-50;
                $kn=50-$k;
                $r=round(sqrt($in*$in+$kn*$kn));
                if( $r==0 || $r%5==0 ){
                    $color=$col->getColor('claster1',$lat,$lon);
                }
                echo "<td style='background-color:$color;width:10px;height:10px;'></td>";
            }
            echo '</tr>';
        }
        echo "</table>";
    }

    public function colorize(){
        $col=new \App\Libraries\Coords2Color();
        $DeliveryJobModel=model('DeliveryJobModel');

        $jobs=$DeliveryJobModel->get()->getResult();
        foreach($jobs as $job){
            $start_color=$col->getColor('claster1',$job->start_latitude,$job->start_longitude);
            $finish_color=$col->getColor('claster1',$job->finish_latitude,$job->finish_longitude);



            echo "$start_color=col->getColor('claster1',$job->start_latitude,$job->start_longitude);";

            $DeliveryJobModel->update($job->job_id,['start_color'=>$start_color,'finish_color'=>$finish_color]);
        }
    }

    public function chain(){
        $DeliveryJobModel=model('DeliveryJobModel');
        return $DeliveryJobModel->chainJobs();
    }




    private $job_order_id=5154;
    public function await(){
        $job=(object)[
            "job_data"=> "{\"is_shipment\":1,\"distance\":12004,\"finish_plan_scheduled\":0}",
            "job_name"=> "Посылка",
            "start_plan"=> "1712740255",
            "start_address"=> "ТЕСТ 45, улица Тав-Даир, Симферополь",
            "finish_address"=> "ТЕСТ 9, Лекарственная улица, Симферополь",
            "start_latitude"=> "44.93677551151808",
            "finish_latitude"=> "44.99798",
            "start_longitude"=> "34.039943263923064",
            "start_prep_time"=> null,
            "finish_longitude"=> "34.165643",
            "finish_arrival_time"=> 3936,
        ];
        $job->order_id=$this->job_order_id;
        $job->courier_id=12;





        $DeliveryJobModel=model('DeliveryJobModel');
        return $DeliveryJobModel->itemStageSet($job->order_id,'awaited',$job);
    }
    public function finish(){
        $job=json_decode('{}');

        $job->order_id=$this->job_order_id;
        $DeliveryJobModel=model('DeliveryJobModel');
        return $DeliveryJobModel->itemStageSet($job->order_id,'finished',$job);
    }
    public function cancel(){
        $job=json_decode('{}');

        $job->order_id=$this->job_order_id;
        $DeliveryJobModel=model('DeliveryJobModel');
        return $DeliveryJobModel->itemStageSet($job->order_id,'canceled',$job);
    }






    function take(){
        $store_id=260;
        // $StoreModel=model('StoreModel');
        // $balance=$StoreModel->itemBalanceGet($store_id);

        // p($balance);





        $TransactionModel=model('TransactionModel');
        $trans_id=$TransactionModel->itemCreate((object)[
            'trans_amount'=>1000,
            'trans_role'=>'supplier->profit',
            'trans_description'=>'test replenishment',
            'tags'=>"store:{$store_id}",
        ]);
        p($trans_id);
    }


    function cache(){
        set_time_limit(300);
        $StoreModel=model('StoreModel');

        p($StoreModel->itemCacheGroupCreate(119));
    }
    function perk(){
        set_time_limit(300);
        model('ProductModel')->nightlyCalculate();
        model('StoreModel')->nightlyCalculate();
    }

    function voice(){
        $Messenger=new \App\Libraries\Messenger();

        echo $Messenger->itemSend(
            (object)[
                'message_transport'=>'voice',
                'message_reciever_id'=>41,
                'message_text'=>"вас приветствует тез кель. вам поступил заказ"
            ]
        );
    }
    function sms(){
        $sms=new \App\Libraries\Sms4B();
        echo $sms->send('79186414455','test sms4b'.rand(1,1000));
    }
}
