<?php
namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

if(getenv('CI_ENVIRONMENT')!=='development'){
    die('!!!');
}


class TestController extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function push(){
        $FirePush=new \App\Libraries\FirePushKreait;
        $result=$FirePush->sendPush((object)[
            'token'=>[
                //'ebzdBTFTu-Ob2sByx05HK_:APA91bHbf1fGGk7ogzaxPHOdHr1HVRyHmsD3PbBFvqJkXZZP38deNZe1GodM3ft1XguNIL2Oe3CQ77N1AdccKYxFvuvDUYZIAL8K1MwHusudB03RVZBhb9Z9QQwedVZNSHgP1ecdrgQo',//chrome
                //'fXF3H6KOobooZA-1h_Ttc0:APA91bGeqZyCTWxqV42R5-ez-c5GfSC7WcyXUpmCc6g-WBuAGPrZNtGD8edMNEwXpzGfn-kvwZVn_xfJVEquRJ0iUYMheJ_NJlW9YohUFjpvNmuSYwasOV55xpow4esoMH8aXMCc3qsZ',//ff
                //'c8aL3e93u7QEvPUU8tfZfW:APA91bFK-oKAhRY3j0JNId8Kwg8bWywW0GAstUGZcLOC2Nd0E63RmVoVWfgSx2Y2e-qm7FtpQUslFBsqDI92Ib3Fjyp_QYh7H0FZy6DCZDOK26yp0R5RCJGswlpGkspbJXhuRrm0zUmv',//edge
                //'eXo5AfJf6EAWit3PHXlcVa:APA91bE7kV-HOEAm6JR1kzHClF7wJwHuPYAAqlAPsprL41hQBdRJyU14ItERjy2m5alcwj-2BKjx29rn1pBXuw5aThUEB8h0M4VAS4Qd8gd_KCz52lxPYzdHw2FV0SexGClekNj_rLcv',//ios
                'eaqq_3W7S46Ia-gIGnwSiG:APA91bH86Nh8aTDPazDa86dlrZsJhvFD1FxFDMYlS8w8chLWtV7mnpSldUBfv5eKIbeimE60AB8TUGoRmPfOu84Erjt_DXTwCITqvuVhwg_tZHEYmW8Q6Yo79ENjIeHdG6HocDSRwkUL',//android
                //'cJNIK-OXTQm5hhpCTJhzIk:APA91bH2QlbVmgIdxHdJ7HF1wRzLs6DE8KCZGSTXhkROB-fch4K2zM1iXm3S0ywLr38P8J8RWxd8qqKDatLZ2URTUUn4u1tTceEmQEZvbZ6KIlzePdLZR2jAKnYdSuS0karyfKWAGnLR'
            ],
            'title'=>'TeSt PuSh😀😀😀😀',
            'body'=>'Test body '.date("H:i:s"),
            'data'=>[
                 'link'=>'/catalog/product-1615',
                 //'tag'=>'#orderStatus',
                 'image'=>'https://api.tezkel.com/image/get.php/fafa5407eaf897fd8b2d378e6c011f42.600.600.jpg',
                 //'icon'=>'default_notification_icon',
                 'sound'=>'long.wav',

                //  'topic'=>'pushStageChanged',
                //  'order_id'=>2457,
                //  'orderActiveCount'=>55,
                //  'stage'=>'customer_start',
            ],
        ]);

        header("Refresh:15");
        echo $result;
    }

    // public function shiftCalc(){
    //     $courier_id=40;
    //     $CourierShiftModel=model('CourierShiftModel');

    //     $result=$CourierShiftModel->itemClose($courier_id);
    //     return $this->respond($result);
    // }
}
