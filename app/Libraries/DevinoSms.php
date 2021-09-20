<?php
namespace App\Libraries;
class DevinoSms{
    
    private $gateway="https://integrationapi.net/rest";
    private $sender=null;
    private $user=null;
    private $pass=null;
    
    public function __construct( $user, $pass, $sender='INFO' ){
        $this->user=$user;
        $this->pass=$pass;
        $this->sender=$sender;
    }
    
    private function login(){
        if( !$this->user || !$this->pass ){
            throw new Exception("Devino SMS user and pass are not set!");
        }
        if (!in_array('https', stream_get_wrappers())) {
            throw new Exception("Sms can not be sent. https is not available!");
        }
        $session=session();
        if (time() - $session->get('devinoSmsSessionTime') * 1 > 24 * 60) {
            $sid = json_decode(file_get_contents("$this->gateway/user/sessionId?login=" . $this->user . "&password=" . $this->pass));
            if (!$sid) {
                throw new Exception("Authorization to SMS service failed!");
            }
            $session->set('devinoSmsSessionId',$sid);
            $session->set('devinoSmsSessionTime',time());
        }
        return $session->get('devinoSmsSessionId');
    }
    
    public function send( $number, $message ){
        $post_vars = array(
            'SessionID' => $this->login(),
            'SourceAddress' => $this->sender,
            'DestinationAddress' => $number,
            'Data' => $message
        );
        $opts = array(
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => "POST",
                'content' => http_build_query($post_vars)
            ]
        );
        
        try{
            $response = file_get_contents("$this->gateway/Sms/Send/", false, stream_context_create($opts));
            $msg_ids = json_decode($response);
        } catch (\Exception $ex) {
            return false;
        }
        
        if ( !isset($msg_ids[0]) ) {
            $session=session();
            $session->set('devinoSmsSessionId',null);
            return false;
        }
        return true;
    }
}