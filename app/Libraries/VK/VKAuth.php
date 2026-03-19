<?php

namespace App\Libraries\VK;

class VKAuth {
    protected $api;

    public function __construct($api) {
        $this->api = $api;
    }

    public function trySignIn($client_id) {
        $this->sessionSetup($client_id);

        $user = model("UserModel")
            ->where("JSON_UNQUOTE(JSON_EXTRACT(user_data, '$.vkClientId')) =", (string)$client_id)
            ->first();

        if ($user) {
            $this->setSession($user['user_id']);
            return $user['user_id'];
        }
        return false;
    }
    
    private function sessionSetup($client_id){
        $curr_session_id=session_id();
        $chat_session_id=md5("vkbot.{$client_id}");
        if( $chat_session_id!==$curr_session_id ){
            if(session_status() === PHP_SESSION_ACTIVE){
                session_destroy();
            }
            session_id($chat_session_id);
            session_start();
        }
        session()->set('chat_id',$client_id);
    }

    public function setSession($user_id) {
        $PermissionModel=model('PermissionModel');
        $PermissionModel->listFillSession();
        $UserModel = model("UserModel");
        session()->set('user_id', $user_id);

        $user_data = $UserModel->itemGet($user_id);
        session()->set('user_data', $user_data);
    }
    public function canLink($client_id, $user_id) {
        $UserModel = model("UserModel");

        $checkVk = $UserModel->where("JSON_UNQUOTE(JSON_EXTRACT(user_data, '$.vkClientId')) =", (string)$client_id)->first();
        
        if ($checkVk && $checkVk['user_id'] != $user_id) {
            return "⚠️ Этот аккаунт VK уже привязан к другому пользователю.";
        }

        $currentUser = $UserModel->find($user_id);
        if (!$currentUser) return "⚠️ Пользователь не найден в системе.";

        $userData = json_decode($currentUser['user_data'] ?? '{}', true);
        if (!empty($userData['vkClientId'])) {
            return "⚠️ К вашему профилю уже привязан другой аккаунт VK.";
        }

        return true; 
    }
    public function link($client_id, $user_id) {
        $sql = "UPDATE user_list SET user_data = JSON_SET(COALESCE(user_data, '{}'), '$.vkClientId', ?) WHERE user_id = ?";
        return model("UserModel")->query($sql, [(string)$client_id, $user_id]);
    }
    
    private function isClientAlreadyLinked($client_id, $user_id) {
        $user = model("UserModel")->where("JSON_UNQUOTE(JSON_EXTRACT(user_data, '$.vkClientId')) =", (string)$client_id)->first();
        return ($user && $user['user_id'] != $user_id);
    }
    
    private function isUserAlreadyLinked($user_id) {
        $user = model("UserModel")->find($user_id);
        $data = json_decode($user['user_data'] ?? '{}', true);
        return !empty($data['vkClientId']);
    }
    
}