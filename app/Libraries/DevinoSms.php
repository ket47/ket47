<?php

class DevinoSms{
    
    private $gateway="https://integrationapi.net/rest";
    private $smsSessionId=null;
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
        $this->smsSessionId=$session->set('devinoSmsSessionId');
    }
    
    public function send( $number, $message ){
        $post_vars = array(
            'SessionID' => $this->smsSessionId,
            'SourceAddress' => $this->sender,
            'DestinationAddresses' => $number,
            'Data' => $message
        );
        $opts = array(
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => "POST",
                'content' => http_build_query($post_vars)
            ]
        );
        $response = file_get_contents("$this->gateway/Sms/SendBulk/", false, stream_context_create($opts));
        $msg_ids = json_decode($response);
        if (!$msg_ids[0]) {
            $session=session();
            $session->set('devinoSmsSessionId',null);
            return false;
        }
        return true;
    }
    
}