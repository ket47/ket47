<?php

namespace App\Libraries\VK;

class VKApi{
    
    private $api_version = '5.81';
    private $api_endpoint = 'https://api.vk.com/method/';
    private $access_token = 'vk1.a.iphl_RrAiVN4POZdjNZd_enqjeC3kK-OeF-uS26N5ISck1iXTEca3tFhFzzgOIiGPEoxhGg4uQqHImcsqdpj-ZBmm6-hujWc4IAvfxt2Dj71B20GwOU4QTSKBcLqaD9Yc7n5ei7gv2S8VpaC6ghfM3fz1JzEUTvSSl08rOjYkqibaLGFaSEKKvCjvTNoJ76-FXwshepu134hxd5Fc3Lt9A';
    
    private $message = [
      "text" => 'Ok',
      "attachments" => '',
      "keyboard" => []
    ];
    public function messagesSend($peer_id) {
      if(empty($this->message['text'])){
        $this->message['text'] = 'Окей!';
      }
      $this->_call('messages.send', array(
        'peer_id'    => $peer_id,
        'message'    => $this->message['text'],
        'random_id'  => rand(1, 2147483647), 
        'keyboard'   => !empty($this->message['keyboard']) ? json_encode($this->message['keyboard']) : null
      ));
      $this->message['text'] = '';
      return true;
    }
    public function eventAnswerSend($eventId, $userId, $peerId, $eventData = null) {
      $params = [
          'event_id'   => $eventId,
          'user_id'    => $userId,
          'peer_id'    => $peerId,
          'access_token' => $this->access_token,
          'v'          => $this->api_version
      ];
  
      if ($eventData) {
          $params['event_data'] = json_encode($eventData);
      }
      return $this->_call('messages.sendMessageEventAnswer', $params);
  }
    
    public function usersGet($user_id) {
      return $this->_call('users.get', array(
        'user_id' => $user_id,
      ));
    }
    
    public function photosGetMessagesUploadServer($peer_id) {
      return $this->_call('photos.getMessagesUploadServer', array(
        'peer_id' => $peer_id,
      ));
    }
    
    public function photosSaveMessagesPhoto($photo, $server, $hash) {
      return $this->_call('photos.saveMessagesPhoto', array(
        'photo'  => $photo,
        'server' => $server,
        'hash'   => $hash,
      ));
    }
    
    public function docsGetMessagesUploadServer($peer_id, $type) {
      return $this->_call('docs.getMessagesUploadServer', array(
        'peer_id' => $peer_id,
        'type'    => $type,
      ));
    }
    
    function docsSave($file, $title) {
      return $this->_call('docs.save', array(
        'file'  => $file,
        'title' => $title,
      ));
    }
    
    public function _call($method, $params = array()) {
        
      $params['access_token'] = $this->access_token;
      $params['v'] = $this->api_version;
    
      $query = http_build_query($params);
      $url = $this->api_endpoint.$method.'?'.$query;
    
      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      $json = curl_exec($curl);
      $error = curl_error($curl);
      if ($error) {
        return false;
      }
    
      curl_close($curl);
    
      $response = json_decode($json, true);
      if (!$response || !isset($response['response'])) {
        return false;
      }
      if (isset($response['error'])) {
        return false;
    }
    
      return $response['response'];
    }
    
    function upload($url, $file_name) {
      if (!file_exists($file_name)) {
        throw new Exception('File not found: '.$file_name);
      }
    
      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, array('file' => new CURLfile($file_name)));
      $json = curl_exec($curl);
      $error = curl_error($curl);
      if ($error) {
        return "Failed {$url} request";
      }
    
      curl_close($curl);
    
      $response = json_decode($json, true);
      if (!$response) {
        return "Invalid response for {$url} request";
      }
    
      return $response;
    }

    public function generateAuthToken($user_id) {
      $salt = date('Y-m-d_H'); 
      $hash = substr(sha1($user_id . $salt), 0, 10);
      
      return $user_id . '.' . $hash;
    }

    /**
    * Проверяет токен и возвращает userId или false
    */
    public function validateAuthToken($token) {

      if (!str_contains($token, '-')) return false;

      $salt = getenv('vk.token');

      list($userId, $receivedCode) = explode('-', $token);
      $userId = (int) $userId;

      // 2. Повторяем логику генерации для этого ID
      // Используем ту же соль и тот же алгоритм hmac
      $hash = hash_hmac('sha256', (string)$userId, $salt);

      // Берем те же 7 символов и переводим в число
      $number = hexdec(substr($hash, 0, 7));

      // Получаем ожидаемый 6-значный код
      $expectedCode = str_pad($number % 1000000, 6, '0', STR_PAD_LEFT);

      // 3. Безопасное сравнение строк
      if (hash_equals($expectedCode, (string)$receivedCode)) {
          return $userId;
      }

      return false;
    }
    public function setText($text)
    {
        $this->message['text'] = $text;
        return true;
    }
    public function setKeyboard($keyboard)
    {
        $this->message['keyboard'] = $keyboard;
        return true;
    }
}