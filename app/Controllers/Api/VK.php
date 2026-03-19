<?php

namespace App\Controllers\Api;

use \CodeIgniter\API\ResponseTrait;
use App\Libraries\VK\VKApi;

class VK extends \App\Controllers\BaseController
{
    private $VKApi;
    use ResponseTrait;
    
    public function __construct(){
        $this->VKApi = new \App\Libraries\VK\VKApi();
    }
    
    public function getAuthToken() {
      $userId = session()->get('user_id');
      $salt = getenv('vk.token');

      $hash = hash_hmac('sha256', (string)$userId, $salt);
      
      $number = hexdec(substr($hash, 0, 7));
      
      $shortCode = str_pad($number % 1000000, 6, '0', STR_PAD_LEFT);
      
      return $this->respond([
          "token" => $userId.'-'.$shortCode
      ]);
        
    }
}