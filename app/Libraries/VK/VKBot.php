<?php

namespace App\Libraries\VK;

class VKBot 
{
    use CourierTrait, OrderTrait;

    public $api;
    public $auth;
    protected $client_id;
    protected $user_id;

    public function __construct() {
        $this->api = new \App\Libraries\VK\VKApi();
        $this->auth = new \App\Libraries\VK\VKAuth($this->api);
    }

    public function dispatch($update) {
        $type = $update['type'] ?? '';
        $obj = $update['object'] ?? [];
        
        $this->client_id = $obj['from_id'] ?? $obj['message']['from_id'] ?? $obj['user_id'] ?? null;
        
        if (!$this->client_id) return;

        $this->user_id = $this->auth->trySignIn($this->client_id);
        if (!$this->user_id) {
            $text = $obj['text'] ?? $obj['message']['text'] ?? null;
            if($text){
                $this->user_id = $this->handleSignUp($text);
            }
            
            if(!$this->user_id){
                $this->api->setText("Пожалуйста, авторизуйтесь через приложение, чтобы пользоваться ботом. Для этого введите код авторизации из приложения.");
                return $this->api->messagesSend($this->client_id);
                
            }

            $name = session()->get('user_data')->user_name ?? 'друг';
            
            $this->api->setText("Рад познакомиться, $name! Ваш аккаунт успешно привязан.");
            return $this->sendMainMenu();
        }
        switch ($type) {
            case 'message_new':
                $message = $obj['message'] ?? $obj;
                if(isset($message['geo'])){
                    return $this->onLocation($message['geo']);
                }
                $this->onMessage($message);
                break;
            case 'message_event':
                $this->onButton($obj);
                break;
        }
    }

    private function handleSignUp($token) {
        $userIdFromToken = $this->api->validateAuthToken($token);
        if (!$userIdFromToken) return false;

        $check = $this->auth->canLink($this->client_id, $userIdFromToken);
        
        if ($check === true) {
            $this->auth->link($this->client_id, $userIdFromToken);
            $this->auth->setSession($userIdFromToken);
            return $userIdFromToken;
        } else {
            $this->api->setText($check);
            $this->api->messagesSend($this->client_id);
            return false;
        }
    }

    public function onMessage($data) {

        $payload = json_decode($data['payload'] ?? '{}', true);
        $command = $payload['command'] ?? '';

        if ($command === 'start') {
            if($this->user_id){
                $this->auth->setSession($this->user_id);
            }
            $this->api->setText("С возвращением! Чем могу помочь?");
        } else {
            $this->api->setText("Чего изволите?");
        }

        $this->sendMainMenu();
    }

    private function onButton($event) {
        $this->api->eventAnswerSend($event['event_id'], $event['user_id'], $event['peer_id']);
       
        $payload = $event['payload'] ?? [];
        $command = explode('-', $payload['command'])[0] ?? '';
        $params = explode('-', $payload['command'])[1] ?? '';
        $result = true;
        if( method_exists($this, $command) ){
            $result = $this->{$command}(...explode(',',$params));
        }
        
        if(!$result){
            return;
        }
        $this->sendMainMenu();
    }
    public function onLocation($event){
        $user_location=$event['coordinates'];
        if( $user_location && $this->isCourier() ){
            $result = $this->onCourierUpdateLocation($user_location);
            $this->api->setText($this->courierStatusGet());
        }
        $this->sendMainMenu();
    }

    public function sendMainMenu() {
        $buttons = array_merge(
            $this->courierButtonsGet()
        );
        $rows = array_chunk($buttons, 2);
        
        $keyboard = [
            "one_time" => false,
            "inline" => false,
            "buttons" => $rows
        ];

        $this->api->setKeyboard($keyboard);
        $this->api->messagesSend($this->client_id);
    }

    public function sendNotification($client_id, $message){
        $this->auth->trySignIn($client_id);
        
        if(!empty($message->template)){
            $text = View($message->template, $message->context);
        } else {
            $text = $message->message_text;
        }
        
        $keyboard = [
            "one_time" => false,
            "inline" => true,
            "buttons" => []
        ];
        if(!empty($message->telegram_options->buttons)){
            foreach($message->telegram_options->buttons as $button){
                $keyboard['buttons'][] = [$this->createButton($button[2], $button[1])];
            }
            $this->api->setKeyboard($keyboard);
        }
        $this->api->setText(strip_tags($text));
        return $this->api->messagesSend($client_id);
    }

    public function createButton($label, $data, $is_link = false)
    {
        if($is_link){
            return [
                "action" => [
                    "type" => "open_link",
                    "label" => $label,
                    "link" => $data
                ]
            ];
        } else {
            return [
                "action" => [
                    "type" => "callback",
                    "label" => $label,
                    "payload" => json_encode(["command" => $data])
                ]
            ];
        }
    }

    private function userGet(){
        $user_id=session()->get('user_id');
        $user=session()->get('user_data');
        if(!$user){
            $UserModel=model('UserModel');
            $user=$UserModel->itemGet($user_id);
            session()->set('user_data',$user);
        }
        return $user;
    }
    private function isUserSignedIn(){
        if( session()->get('user_id')>0 ){
            return true;
        }
        return false;
    }
}