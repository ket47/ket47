<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Webhooks extends \App\Controllers\BaseController{
    use ResponseTrait;

    public function telegramWebhook(){
        $telegramToken=getenv('telegram.token');
        $Telegram=new \App\Libraries\Telegram\Telegram($telegramToken);
        $Tbot=new \App\Libraries\Telegram\TelegramBot();
        $Tbot->dispatch($Telegram);
    }

    public function telegramPoll(){
        $telegramToken=getenv('telegram.token');
        $Telegram=new \App\Libraries\Telegram\Telegram($telegramToken);
        $Tbot=new \App\Libraries\Telegram\TelegramBot();
        $Telegram->getUpdates($offset = 0, $limit = 3, 1, $update = true);
        for ($i = 0; $i < $Telegram->UpdateCount(); $i++) {
            $Telegram->serveUpdate($i);
            $Tbot->dispatch($Telegram);
        }
    }
    public function VKWebhook() {
        try {
            $raw = $this->request->getBody();
            $data = json_decode($raw, true);
            
            if (!$data) {
                return $this->respond('Error: Empty or invalid JSON', 400);
            }

            if (isset($data['type']) && $data['type'] === 'confirmation') {
                return $this->respond(getenv('vk.confirmationCode'));
            }
    
            if (!class_exists('\App\Libraries\VK\VKBot')) {
                return $this->respond('Error: Class \App\Libraries\VK\VKBot not found', 500);
            }
    
            $VKBot = new \App\Libraries\VK\VKBot();
            
            $VKBot->dispatch($data);
    
            return $this->respond('success');
    
        } catch (\Throwable $e) {
            return $this->respond('PHP Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
        }
    }


    public function VKPoll(){
        $VKBot=new \App\Libraries\VK\VKBot();
        echo $VKBot->getUpdates();
    }


    public function telegramPing(){
        if(PHP_SAPI !== 'cli'){
            return false;
        }

        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );
        while(1){
            //$result=$this->telegramPoll();
            $result=@file_get_contents('http://tezkel.local/WebHooks/telegramPoll', false, stream_context_create($arrContextOptions));
            if($result){
                \CodeIgniter\CLI\CLI::write("W HELPER:".$result);
            }
        }
    }
    // Обновите VKPing (теперь он тянет данные с вашего прокси)
    public function VKPing() {
        if (PHP_SAPI !== 'cli') return false;
    
        $remoteUrl = 'https://api.tezkel.com/vk/vk_handler.php';
        $localUrl = 'http://tezkel.local/WebHooks/VKWebhook';
    
        \CodeIgniter\CLI\CLI::write("Пинг VK запущен... Ожидание событий.", 'cyan');
    
        while (1) {
            $events = @file_get_contents($remoteUrl);
            
            if (!empty($events)) {
                $rows = explode(PHP_EOL, trim($events));
                
                foreach ($rows as $row) {
                    if (empty(trim($row))) continue;
                    
                    try {
                        $client = \Config\Services::curlrequest();
                        
                        // ВАЖНО: ставим 'http_errors' => false, чтобы прочитать тело ошибки 500
                        $response = $client->post($localUrl, [
                            'body'        => $row,
                            'headers'     => ['Content-Type' => 'application/json'],
                            'timeout'     => 10,
                            'http_errors' => false 
                        ]);
    
                        $body = $response->getBody();
                        $status = $response->getStatusCode();
    
                        if ($status === 200 && $body === 'success') {
                            \CodeIgniter\CLI\CLI::write("✅ [" . date('H:i:s') . "] Событие обработано", 'green');
                        } else {
                            \CodeIgniter\CLI\CLI::error("❌ ОШИБКА СЕРВЕРА (Код: $status)");
                            
                            // Пытаемся распарсить JSON с деталями ошибки из Webhooks.php
                            $errorData = json_decode($body, true);
                            
                            if (json_last_error() === JSON_ERROR_NONE && isset($errorData['error'])) {
                                \CodeIgniter\CLI\CLI::write("Сообщение: " . $errorData['error'], 'yellow');
                                \CodeIgniter\CLI\CLI::write("Файл: " . $errorData['file'] . " (Линия: " . $errorData['line'] . ")", 'light_gray');
                                // Если хочешь видеть весь путь ошибки:
                                // \CodeIgniter\CLI\CLI::write($errorData['trace'], 'dark_gray');
                            } else {
                                \CodeIgniter\CLI\CLI::write("Сырой ответ сервера: " . $body, 'red');
                            }
                            \CodeIgniter\CLI\CLI::write("-----------------------------------");
                        }
                    } catch (\Exception $e) {
                        \CodeIgniter\CLI\CLI::error("Ошибка Curl: " . $e->getMessage());
                    }
                }
            }
            
            usleep(500000); 
        }
    }
    
    public function viberWebhook(){
        $Viber= new \App\Libraries\Viber();
        $data=$this->request->getJSON();
        $response=null;
        if( isset($data->event) ){
            $this->incoming=$data;
            $eventName="on{$data->event}";
            if( method_exists($Viber,$eventName) ){
                $response=$Viber->$eventName($data->sender??null,$data->message??'');
            } else {
                $email = \Config\Services::email();
                $config=[
                    'SMTPHost'=>getenv('email_server'),
                    'SMTPUser'=>getenv('email_username'),
                    'SMTPPass'=>getenv('email_password'),
                    'mailType'=>'text',
                ];
                $email->initialize($config);
                $email->setFrom(getenv('email_from'), getenv('email_sendername'));
                $email->setTo(getenv('email_admin'));
                $email->setSubject(getenv('app.baseURL').' Viber webhook');
                $email->setMessage(json_encode($data));
                $email_send_ok=$email->send();
            }
        }
        if( is_array($response) || is_object($response) ){
            return $this->respond($response);
        }
        $webhook_response['status']=0;
        $webhook_response['status_message']="ok";
        return $this->respond($webhook_response);
    }
    
    public function viberSetWebhook(){
        if( !sudo() ){
            die("Access denied!");
        }
        $Viber= new \App\Libraries\Viber();
        $request=[
            "url"=>getenv('app.baseURL').'WebHooks/viberWebhook',
            "event_types"=>[
                "delivered",
                "seen",
                "failed",
                "subscribed",
                "unsubscribed",
                "conversation_started"
            ],
            "send_name"=>false,
            "send_photo"=>false
        ];
        $response=$Viber->call_api('set_webhook',$request);
    }
}
