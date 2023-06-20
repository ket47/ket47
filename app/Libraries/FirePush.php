<?php
namespace App\Libraries;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Plokko\Firebase\FCM\Exceptions\FcmErrorException;
use Plokko\Firebase\FCM\Message;
use Plokko\Firebase\FCM\Request;
use Plokko\Firebase\FCM\Targets\Token;
use Plokko\Firebase\ServiceAccount;

class FirePush{
    private $serviceAccount;
    function __construct(){
        $serviceCredentials = dirname(__DIR__ ).'/../../firebase.conf';
        $this->serviceAccount = new ServiceAccount($serviceCredentials);
    }
    public function sendPush( $push, $atempt=1 ){
        $message = new Message();
        $message->setTarget(new Token($push->token));

        if($push->title??null){
            $message->notification->setTitle($push->title);
        }
        if($push->body??null){
            $message->notification->setBody($push->body);
        }
        //$message->webpush->notification->icon=getenv('firebase.icon');
        $message->webpush->fcm_options->link=$push->data->link??'';


        $push->data->vibrate='[200,100,200]';
        $message->data->fill((array)$push->data??[]);
        $request = new Request($this->serviceAccount);
        try{
            $message->send($request);
            return true;
        }
        catch(FcmErrorException $e){
            switch($e->getErrorCode()){
                case 'NOT_FOUND':
                    $MessageSubModel=model('MessageSubModel');
                    $MessageSubModel->where('sub_registration_id',$push->token)->delete();
                    break;
                case 'UNREGISTERED':
                case 'UNSPECIFIED_ERROR':
                case 'INVALID_ARGUMENT':
                case 'SENDER_ID_MISMATCH':
                case 'QUOTA_EXCEEDED':
                case 'APNS_AUTH_ERROR':
                case 'UNAVAILABLE':
                case 'INTERNAL':
                default:
                  if( $atempt<3 ){
                    sleep(1);
                    $this->sendPush($push,++$atempt);
                  } else {
                    log_message('error','FCM error ['.$e->getErrorCode().']: '.$e->getMessage());
                  }
            }
        }
        catch(RequestException $e){
            //HTTP response error
            $response = $e->getResponse();
            log_message('error','FCM Got an http response error:'.$response->getStatusCode().':'.$response->getReasonPhrase());
        }
        catch(GuzzleException $e){
            //GuzzleHttp generic error
            log_message('error','FCM Got an http error:'.$e->getMessage());
        }
        return false;
    }
}