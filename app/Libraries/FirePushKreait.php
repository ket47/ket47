<?php
namespace App\Libraries;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Exception\Messaging;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\ApnsConfig;

class FirePushKreait{
    private $messaging;
    function __construct(){
        $factory = new Factory();
        $this->messaging = $factory
            ->withServiceAccount(dirname(__DIR__ ).'/../../firebase.conf')
            ->createMessaging();
    }


    public function sendPush( $push, $atempt=1 ){
        $msg=[
            'notification' => [
                'title' => $push->title,
                'body' => $push->body,
                'image' => $push->image??'',
            ],
            'data' => $push->data??[],
            'apns'=>[],
        ];

        if($push->data['tag']??''){
            $msg['apns']['headers']['apns-collapse-id']=$push->data['tag'];
            $msg['collapse_key']=$push->data['tag'];
            $msg['notification']['tag']=$push->data['tag'];
        }

        if($push->data['link']??''){
            $msg['notification']['link']=$push->data['link'];
        }
        $message = CloudMessage::fromArray($msg)->withDefaultSounds();
        if( is_array($push->token) ){
            $deviceTokens=$push->token;
        } else {
            $deviceTokens=[$push->token];
        }
        $report = $this->messaging->sendMulticast($message, $deviceTokens);
        $invalidTargets=array_merge($report->unknownTokens(),$report->invalidTokens());
        if( $invalidTargets ){
            $MessageSubModel=model('MessageSubModel');
            $MessageSubModel->whereIn('sub_registration_id',$invalidTargets)->delete();
        }
        return $report->successes()->count()?1:0;
    }

}