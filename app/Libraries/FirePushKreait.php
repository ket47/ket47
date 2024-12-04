<?php
namespace App\Libraries;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\RawMessageFromArray;
use Kreait\Firebase\Exception\Messaging\NotFound;

class FirePushKreait{
    private $messaging;
    function __construct(){
        $factory = new Factory();
        $this->messaging = $factory
            ->withServiceAccount(dirname(__DIR__ ).'/../../firebase.conf')
            ->createMessaging();
    }
    // public function sendPush2( $push ){
    //     $push->data=(array)($push->data??[]);
    //     $push->data['title']=$push->title;
    //     $push->data['body']=$push->body;
    //     $msg=[
    //         'data' => $push->data,
    //         'android' => [
    //             'notification' => [
    //                 'title' => $push->title,
    //                 'body' => $push->body,
    //                 'image'=>$push->data['image']??null,
    //                 'icon'=>$push->data['icon']??null,
    //                 'click_action'=>'NOTIF_ACTIVATE',
    //             ],
    //         ],
    //         'apns' => [
    //             'payload' => [
    //                 'aps' => [
    //                     'alert' => [
    //                         'title' => $push->title,
    //                         'body' => $push->body,
    //                     ],
    //                 ],
    //             ],
    //         ],
    //         'webpush'=>[
    //             'notification' => [
    //                 'title' => $push->title,
    //                 'body' => $push->body,
    //                 'image'=>$push->data['image']??null,
    //                 'icon'=>getenv('firebase.icon'),
    //                 'vibrate'=> '[200, 100, 200]'
    //             ],
    //         ]
    //     ];
    //     if( $msg['data']['sound']??null ){
    //         $msg['apns']['payload']['aps']['sound']=$msg['data']['sound'];
    //         $msg['apns']['headers']['apns-priority']='10';

    //         $msg['android']["priority"]='high';
    //         $msg['webpush']["headers"]["Urgency"]='high';
    //         if(str_contains($msg['data']['sound'],'long')){
    //             $msg['android']['notification']['sound']='longsound';//backward compability
    //             $msg['android']['notification']['channelId']='com.tezkel.urgent';
    //         }
    //         if(str_contains($msg['data']['sound'],'medium')){
    //             $msg['android']['notification']['sound']='mediumsound';//backward compability
    //             $msg['android']['notification']['channelId']='com.tezkel.high';
    //         }
    //         if(str_contains($msg['data']['sound'],'short')){
    //             $msg['android']['notification']['sound']='shortsound';//backward compability
    //             $msg['android']['notification']['channelId']='com.tezkel.normal';
    //         }
    //     }
    //     if( $push->data['tag']??'' ){
    //         $ttl=60;
    //         $expirationDate = time() + $ttl;
    //         $msg['apns']['headers']['apns-expiration']=(string) $expirationDate;//ios
    //         $msg['android']["ttl"]="{$ttl}s";
    //         $msg['webpush']["headers"]["TTL"]="$ttl";

    //         $msg['apns']['headers']['apns-collapse-id']=$push->data['tag'];//ios
    //         $msg['android']['collapse_key']=$push->data['tag'];//android
    //     }
    //     $deviceTokens=is_array($push->token)?$push->token:[$push->token];
    //     $sent_count=0;
    //     foreach($deviceTokens as $token){
    //         try{
    //             $msg['token']=$token;
    //             $message = new RawMessageFromArray($msg);
    //             $this->messaging->send($message);
    //             $sent_count++;
    //         } 
    //         catch (NotFound $e) {
    //             $MessageSubModel=model('MessageSubModel');
    //             $MessageSubModel->where('sub_registration_id',$token)->delete();
    //         }
    //         catch( \Throwable $e  ){
    //             echo $e->getMessage();
    //         }
    //     }
    //     return $sent_count;
    // }

    public function sendPush( $push ){
        $push->data=(array)$push->data??[];
        $push->data['title']=$push->title;
        $push->data['body']=$push->body;
        $msg=[
            'data' => $push->data,
            'android' => [
                'notification' => [
                    'title' => $push->title,
                    'body' => $push->body,
                    'image'=>$push->data['image']??null,
                    'icon'=>$push->data['icon']??null,
                    'click_action'=>'NOTIF_ACTIVATE',
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $push->title,
                            'body' => $push->body,
                        ],
                    ],
                ],
            ],
            'webpush'=>[]
        ];
        if( $msg['data']['sound']??'' ){
            $msg['apns']['payload']['aps']['sound']=$msg['data']['sound'];
            $msg['apns']['headers']['apns-priority']='10';

            $msg['android']["priority"]='high';
            $msg['webpush']["headers"]["Urgency"]='high';
            if(str_contains($msg['data']['sound'],'long')){
                $msg['android']['notification']['sound']='longsound';//backward compability
                $msg['android']['notification']['channelId']='com.tezkel.urgent';
            }
            if(str_contains($msg['data']['sound'],'medium')){
                $msg['android']['notification']['sound']='mediumsound';//backward compability
                $msg['android']['notification']['channelId']='com.tezkel.high';
            }
            if(str_contains($msg['data']['sound'],'short')){
                $msg['android']['notification']['sound']='shortsound';//backward compability
                $msg['android']['notification']['channelId']='com.tezkel.normal';
            }
        }
        if( $push->data['tag']??'' ){
            $ttl=60;
            $expirationDate = time() + $ttl;
            $msg['apns']['headers']['apns-expiration']=(string) $expirationDate;//ios
            $msg['android']["ttl"]="{$ttl}s";
            $msg['webpush']["headers"]["TTL"]="$ttl";

            $msg['apns']['headers']['apns-collapse-id']=$push->data['tag'];//ios
            $msg['android']['collapse_key']=$push->data['tag'];//android
        }
        $message = new RawMessageFromArray($msg);
        try{
            $deviceTokens=is_array($push->token)?$push->token:[$push->token];
            $report = $this->messaging->sendMulticast($message, $deviceTokens);
            $invalidTargets=array_merge($report->unknownTokens(),$report->invalidTokens());
            if( $invalidTargets ){
                $MessageSubModel=model('MessageSubModel');
                $MessageSubModel->whereIn('sub_registration_id',$invalidTargets)->delete();
            }
            return $report->successes()->count()?1:0;
        }catch( \Throwable $e  ){
            return 0;
        }
    }
}