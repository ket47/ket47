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
                //'dbkB1s8n-kXNicm-IYBu9l:APA91bGCiCyCWviixtMusgf0AhDQWXx5GQItwcFs9JxSvpHGp0byVbpMaxXk3Miuwh9JUiFUcFsq1pR1c8-Drgnm6j1QTnxOeTHTAgjPK6qSN43BkgWfFw_a_Kc4tPpyxUuGi6n1Os1Y',//ios
                'dOhEdNawTfGqfQSB-QQ9ML:APA91bGAemzY63OOORYrsZdmBbpy5Y-19Hjio7W_ocfel7zYf0J-GB5C_huT9_DRXGe5Y7f9mOfYjSJq3946vf4G8jayNq2lKRfzPB2ODUDnL0yC0O0Ki3s-37F8rXNuzvrxnbh534X9',//android
                //'e3fOaaxrBblDF60jjF_N0n:APA91bERvrB4GmB5mjvO-OVvs-ynFpMZ3uX4_vjPOWi6lTDTizVWDsjPQc4lC9_v2TBLsOwyw7THOFLv9T9AWzLLatFFOHS5pVnJCr54PS3vMYLLGoCKjSeVzh6tQqD7x09kbcJWrW_d'
            ],
            'title'=>'TeSt PuSh😀😀😀😀',
            'body'=>'Test body '.date("H:i:s"),
            'data'=>[
                 'link'=>'/catalog/product-1615',
                 'tag'=>'#orderStatus',
                 'image'=>'https://api.tezkel.com/image/get.php/fafa5407eaf897fd8b2d378e6c011f42.600.600.jpg',
                 'icon'=>'default_notification_icon',
                 //'sound'=>'long.wav',

                 'topic'=>'pushStageChanged',
                 'order_id'=>2457,
                 'orderActiveCount'=>55,
                 'stage'=>'customer_start',
            ],
        ]);

        header("Refresh:5");
        echo $result;
    }
}
