<?php
namespace App\Libraries;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\RawMessageFromArray;

class FirePushKreait{
    private $messaging;
    function __construct(){
        $factory = new Factory();
        $this->messaging = $factory
            ->withServiceAccount(dirname(__DIR__ ).'/../../firebase.conf')
            ->createMessaging();
    }

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
        ];
        if( $msg['data']['sound']??'' ){
            $msg['apns']['payload']['aps']['sound']=$msg['data']['sound'];
            $msg['apns']['headers']['apns-priority']='10';

            $msg['android']['notification']['sound']=$msg['data']['sound'];
            $msg['android']['priority']='high';
        }
        if( $push->data['tag']??'' ){
            $msg['apns']['headers']['apns-collapse-id']=$push->data['tag'];//ios
            $msg['collapse_key']=$push->data['tag'];//android
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