<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Viber extends \App\Controllers\BaseController{

    use ResponseTrait;
    
    private $url_api = "https://chatapi.viber.com/pa/";

    public function get_account_info(){
        $result=$this->call_api('get_account_info', (object)[]);
        return $this->respond($result);
    }
    
    public function send_message(){
        $text=$this->request->getVar('text');
        
        $data['receiver']   = 'f0oWLRUl0/LFWzIFgNvXwA==';
        //$data['receiver']   = 'NMs14J/ldy1RmPy9ulR7HQ==';
        
        $data['sender'] = [
            'name' => getenv('viber.title'),
            'avatar' => getenv('viber.avatar')
        ];
        $data['type']   = 'text';
        $data['text']   = $text;
        $result=$this->call_api('send_message', $data);
        return $this->respond($result);
    }
    
    public function set_webhook(){
        $data['url']   = 'https://api.tezkel.com/Viber/webhook';
        return $this->call_api('set_webhook', $data);
    }

    private function call_api($method, $data){
        $url = $this->url_api.$method;

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nX-Viber-Auth-Token: ".getenv('viber.token')."\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            )
        );
        //p($options);
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return json_decode($response);
    }    
    public function webhook(){
        $data=$this->request->getJSON();
        
        $email = \Config\Services::email();
        $config=[
            'SMTPHost'=>getenv('email_server'),
            'SMTPUser'=>getenv('email_username'),
            'SMTPPass'=>getenv('email_password'),
            'mailType'=>'html',
        ];
        $email->initialize($config);
        $email->setFrom(getenv('email_from'), getenv('email_sendername'));
        $email->setTo('bay@nilsonmag.com');
        $email->setSubject("Viber webhook");
        $email->setMessage(json_encode($data));
        $email->send();
        
        return $this->respond(1);
    }
 
}
