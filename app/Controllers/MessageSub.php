<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class MessageSub extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    public function itemGet(){
        return false;
    }

    public function itemCreate(){
        $registration_id=$this->request->getVar('registration_id');
        $type=$this->request->getVar('type');
        $user_agent=$this->request->getVar('user_agent');
        $MessageSubModel=model('MessageSubModel');
        $result=$MessageSubModel->itemCreate($registration_id,$type,$user_agent);
        if( $result=='notauthorized' ){
            return $this->failUnauthorized('notauthorized');
        }
        return $this->respond($result);
    }

    public function listGet($user_id){
        $user_id=$this->request->getVar('user_id');
        $MessageSubModel=model('MessageSubModel');

        $result=$MessageSubModel->listGet($user_id);
        return $this->respond($result);
    }


    public function test(){
        // require '../vendor/autoload.php';
        // $client = new \Fcm\FcmClient('AIzaSyDHDeSPsSoJHE_HYKQ_vgOvSfJIN_8Y2Uc', '359468869452');

        // $notification = new \Fcm\Push\Notification();
        // $notification
        //     ->addRecipient('du4ZE6b0UYFYQ0m8aw81na:APA91bF4dtVAFJ3E1t9t0Drj17_eSyeh1w3mYBx9snv7dSwiocVIMxSdMj17dNMRluzSQ8XzTmkDTjIaRfKMcJjlwFpfeAlq9LZc9W6DY_eDMvYMGjgtrhoLQwQpCgB')
        //     //->addRecipient($deviceGroupID)
        //     //->addRecipient($arrayIDs)
        //     ->setTitle('Hello from php-fcm!')
        //     ->setColor('#20F037')
        //     ->setSound("default")
        //     ->setBadge(11)
        //     ->addData("key","value");
        
        // // Shortcut function:
        // // $notification = $client->pushNotification('The title', 'The body', $deviceId);
        
        // $response = $client->send($notification);
    }

    public function sendMessage($title="tessssssst",$message="first push",$player_id=41,$image=null) {
        $content      = array(
    
            "en" => $message,
    
        );
    
        $headings = array(
    
            'en' => $title
    
        );
    
        $fields = array(
            'app_id' => '1490b865-b284-4879-90c3-a29216360c41',
            //'include_player_ids' => array($player_id),
            'include_external_user_ids'=>['41'],
            'data' => array( 'foo' => 'bar' ),
            'contents' => $content,
            'headings' => $headings,
            "web_url" => "https://howi.in",
            //"chrome_web_image" => $image
        );
    
        $fields = json_encode( $fields );
    

        $apikey="N2JmMGEyODktN2E4ZS00OWNkLThhOTYtNTM1NmE2MzY4ODU5";

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications' );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 
            'Accept: application/json',
            'Authorization: Basic '. $apikey,
            'Content-Type: application/json', ] );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        curl_setopt( $ch, CURLOPT_HEADER, FALSE );
        curl_setopt( $ch, CURLOPT_POST, TRUE );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
    
        echo $response = curl_exec( $ch );
        curl_close( $ch );
    }
}
