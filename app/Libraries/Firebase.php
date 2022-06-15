<?php
namespace App\Libraries;
class Firebase{
    private $url_api = "https://fcm.googleapis.com/v1/projects/359468869452/messages:";

    public function call_api($method, $data){
        $url = $this->url_api.$method;

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json\r\nAuthorization:key=".getenv('firebase.apikey')."\r\n",
                'method'  => 'POST',
                'content' => json_encode($data)
            )
        );
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        return json_decode($response);
    }
    
    public function send_message($to,$notification){
        // $request= [
        //     'notification' => $notification,
        //     'data' => $data,
        //     //"time_to_live" => "600", // Optional
        //     'to' => $to // Replace 'mytargettopic' with your intended notification audience
        //   ];

        return $this->call_api('send', $notification);
    }
}