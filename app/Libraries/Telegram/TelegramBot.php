<?php
namespace App\Libraries\Telegram;

use CodeIgniter\CLI\CLI;
function w($text){
    //CLI::write("W HELPER:".json_encode($text));
    print_r($text);
}
function clearPhone( $phone_number ){
    return '7'.substr(preg_replace('/[^\d]/', '', $phone_number),-10);
}
class TelegramBot{
    use CourierTrait;
    use OrderTrait;
    use SupplierTrait;
    use SystemTrait;

    private $commandButtonMap=[
        '/orderlist'=>"Активные заказы"
    ];
    public $Telegram;
    public function onMessage(){
        $text=$this->Telegram->Text();
        if($text=='/signout'){
            return $this->userSignOut();
        }
        if($text=='/start'){
            return $this->sendMainMenu();
        }
        if($text=='/orderlist'){
            if( method_exists($this,'onOrderListGet') ){
                return $this->{'onOrderListGet'}(1);
            }
        }
        $text=$this->commandButtonMap[$text]??$text;
        $is_known_command=$this->buttonExecute($text);
        if(!$is_known_command){
            //$this->sendMainMenu();
        }
    }
    public function onContact(){
        $incoming=$this->Telegram->getData();
        $fromTelegramUserId=$incoming['message']['from']['id'];
        $contactTelegramUserId=$incoming['message']['contact']['user_id'];

        if($fromTelegramUserId!==$contactTelegramUserId){
            $this->sendText("Нужен ваш контакт");
            $this->userPhoneRequest();
            return;
        }
        $user_phone=$this->Telegram->getContactPhoneNumber();
        $this->userSignUp($user_phone);
    }
    public function onLocation(){
        $user_location=$this->Telegram->Location();
        if( $user_location && $this->isCourier() ){
            $this->onCourierUpdateLocation($user_location);
        }
    }
    public function onEdited_message(){
        $user_location=$this->Telegram->Location();
        if( $user_location && $this->isCourier() ){
            $this->onCourierUpdateLocation($user_location);
        }
    }
    public function onCallback_query(){
        $callbackQuery=$this->Telegram->Callback_Data();
        if(!$callbackQuery){
            return false;
        }
        $command=explode('-',$callbackQuery);
        if( substr($command[0],0,2)=='onNoop' ){
            return true;
        }
        if( substr($command[0],0,2)!=='on' ){
            pl("calback query forbidden must begin with 'on' ".implode($command));
        }
        if( method_exists($this,$command[0]) ){
            return $this->{$command[0]}(...explode(',',$command[1]));
        }
        pl("calback query not executed ".implode($command),0);
    }
    public function onPhoto(){
        $photo=$this->Telegram->IncomingData()['photo']??null;
        if( !is_array($photo) ){
            return $this->sendText("Не смог загрузить фото");
        }
        $file=array_pop($photo);
        $file_path=$this->Telegram->getFile($file['file_id']);
        $file_url="https://api.telegram.org/file/bot".getenv('telegram.token')."/{$file_path['result']['file_path']}";

        $order_id=session()->get('opened_order_id');
        if(!$order_id){
            return $this->sendText("Сначала откройте заказ к которому надо добавить фото");
        }
        $result=$this->orderPhotoDownload($order_id,$file_url);
        if( $result==='forbidden' ){
            return $this->sendText("Нет доступа");
        }
        if( $result==='limit_exeeded' ){
            return $this->sendText("Уже загружено максимально количество фото");
        }
        return $this->sendText("Фото добавлено");
    }













    private function sessionSetup($chat_id){
        $curr_session_id=session_id();
        $chat_session_id=md5("telegrambot.{$chat_id}");
        if( $chat_session_id!==$curr_session_id ){
            if($curr_session_id){
                session_destroy();
            }
            session_id($chat_session_id);
            session_start();
        }
        session()->set('chat_id',$chat_id);
    }

    public function dispatch($Telegram){
        $this->Telegram=$Telegram;
        $type=$Telegram->getUpdateType();
        $chat_id=$this->Telegram->ChatID();
        //w([$type,$chat_id]);
        $this->sessionSetup($chat_id);

        $handler="on".ucfirst($type);
        if( $handler==='onContact' ){
            return $this->onContact($Telegram);
        }
        $signin_ok=$this->userSignIn();
        if(!$signin_ok){
            return false;
        }
        if( !method_exists($this,$handler) ){
            w("Incoming message type is unknown ".$type);
            $this->sendMainMenu();
            return false;
        }
        try{
            $this->$handler($Telegram);
        } catch(\Throwable $e){
            pl("Telegram bot ERR: ".$e->getMessage()." File:".$e->getFile()." Line:".$e->getLine());
        }
    }


    public function sendNotification($ChatID,$content,$options=null){
        session()->set('chat_id',$ChatID);//Dont use incoming session !!!
        
        $opts=(array)($options->opts??[]);
        if( $options->buttons??null ){
            $menu=array_merge(
                $this->buttonInlineRowBuild( $options->buttons )
            );
            $opts['reply_markup']=$this->Telegram->buildInlineKeyBoard(array_chunk($menu,2), $onetime=true);
        }
        if( $options->disable_web_page_preview??null ){
            $opts['disable_web_page_preview']=1;
        }
        if( $options->is_location??null ){
            $content=$opts;
            $content['chat_id']=$ChatID;
            return $this->sendLocation($content,$opts);
        }
        return $this->sendHTML($content,$opts);
    }
    public function sendText( $text, $opts=null, $permanent_message_name=null ){
        return $this->sendMessage(['text'=>$text],$opts, $permanent_message_name);
    }
    public function sendHTML( $text , $opts=null, $permanent_message_name=null){
        return $this->sendMessage(['text'=>$text,'parse_mode'=>'HTML'],$opts, $permanent_message_name);
    }

    public function sendLocation( $content, $opts=null, $permanent_message_name=null ){
        $content['message_id']=null;
        if( $permanent_message_name ){
            $content['message_id']=session()->get($permanent_message_name);
        }
        if($content['message_id']??null){
            $result=$this->Telegram->editMessageLiveLocation($content);
        } else {
            $result=$this->Telegram->sendLocation($content);
        }
        if( $permanent_message_name ){
            if( ($result['error_code']??null)=='400' ){
                $this->Telegram->deleteMessage($content);
                if(isset($opts['message_id'])){
                    unset($opts['message_id']);
                }
                session()->remove($permanent_message_name);
                $result=$this->Telegram->sendLocation($content);
            }
            session()->set($permanent_message_name,$result['result']['message_id']??null);
        }
        if( ($result['error_code']??null)=='400' ){
            pl("SEND LOCATION ERROR: ".$result['description'],false);
        }
        return $result;
    }


    public function sendMessage( $content, $opts=null, $permanent_message_name=null ){
        if( $opts ){
            $content=array_merge($content,$opts);
        }
        $content['chat_id']=session()->get('chat_id');

        $content['message_id']=null;
        if( $permanent_message_name ){
            $content['message_id']=session()->get($permanent_message_name);
        }
        if($content['message_id']??null){
            $result=$this->Telegram->editMessageText($content);
        } else {
            if(!empty($content['photo'])){
                $result=$this->Telegram->sendPhoto($content);
            } else {
                $result=$this->Telegram->sendMessage($content);
            }
        }

        if( $permanent_message_name ){
            if( ($result['error_code']??null)=='400' ){
                $this->Telegram->deleteMessage($content);
                if(isset($opts['message_id'])){
                    unset($opts['message_id']);
                }
                session()->remove($permanent_message_name);
                $result=$this->Telegram->sendMessage($content);
            }
            session()->set($permanent_message_name,$result['result']['message_id']??null);
        }
        if( ($result['error_code']??null)=='400' ){
            w("SEND MESSAGE ERROR: ".$result['description']);
        }
        return $result;
    }
    public function pinMessage($message_id){
        $pinned_message_id=session()->get('pinned_message_id');
        if($pinned_message_id==$message_id){
            return true;
        }
        session()->set('pinned_message_id',$message_id);
        $content['chat_id']=session()->get('chat_id');
        $content['message_id']=$message_id;
        return $this->Telegram->pinChatMessage($content);
    }
    public function unpinMessage($message_id){
        $pinned_message_id=session()->get('pinned_message_id');
        if($pinned_message_id!=$message_id){
            return true;
        }
        $content['chat_id']=session()->get('chat_id');
        $content['message_id']=$message_id;
        return $this->Telegram->unpinChatMessage($content);
    }

    public function sendMainMenu(){
        $courierStatusHTML=$this->courierStatusGet();
        $supplierStatusHTML=$this->supplierStatusGet();
        $systemStatusHTML=$this->systemStatusGet();
        $menu_2col=array_merge(
            $this->buttonInlineRowBuild( $this->orderButtons ),
            $this->buttonInlineRowBuild( $this->courierButtons ),
            $this->buttonInlineRowBuild( $this->systemButtonsGet() )
        );
        $menu_1col=array_merge(
            $this->buttonInlineRowBuild( $this->supplierButtonsGet() )
        );
        $menu=array_merge(
            array_chunk($menu_2col,2),
            array_chunk($menu_1col,1)
            );
        $opts=[
            'reply_markup' => $this->Telegram->buildInlineKeyBoard( $menu, $onetime=false),
            'disable_web_page_preview'=>1,
        ];
        $context=[
            'user'=>$this->userGet(),
            'courierStatusHTML'=>$courierStatusHTML,
            'supplierStatusHTML'=>$supplierStatusHTML,
            'systemStatusHTML'=>$systemStatusHTML,
        ];
        $html=View('messages/telegram/mainMenu',$context);
        $this->sendHTML($html,$opts,'mmenu_message');
    }
    private function buttonInlineRowBuild( $buttons ){
        $row=[];
        foreach($buttons as $button){
            $filter=$button[0];
            $action=$button[1];
            $name=$button[2];
            if( $this->buttonFilter($filter) ){
                $row[]=$this->Telegram->buildInlineKeyboardButton($name,'',"{$action}-");
            }
        }
        return $row;
    }
    private function buttonFilter($filter){
        if(!$filter){
            return true;
        }
        if( method_exists($this,$filter) && $this->{$filter}() ){
            return true;
        }
        return false;
    }
    private function buttonExecute($command){
        $all_buttons=array_merge($this->courierButtons,$this->orderButtons);
        foreach($all_buttons as $button){
            if( $button[2][0]!=$command ){
                continue;
            }
            if( method_exists($this,$button[1]) ){
                $this->{$button[1]}();
                return true;
            }
        }
        return false;
    }

    private function isUserSignedIn(){
        if( session()->get('user_id')>0 ){
            return true;
        }
        return false;
    }
    private $user;
    private function userSignOut(){
        $chat_id=$this->Telegram->ChatID();
        $this->sessionSetup($chat_id);
    }
    private function userSignIn(){
        if( $this->isUserSignedIn() ){
            return true;
        }
        $telegramChatId=session()->get('chat_id');
        if($telegramChatId==getenv('telegram.adminChatId')){
            $PermissionModel=model('PermissionModel');
            $PermissionModel->listFillSession();
            session()->set('user_id',-100);
            session()->set('user_data',null);
            return true;
        }
        $UserModel=model("UserModel");
        $UserModel->where("JSON_EXTRACT(user_data,\"$.telegramChatId\")='$telegramChatId'")->select('user_id');
        $user_id=$UserModel->get()->getRow('user_id');
        if(!$user_id){
            $this->userPhoneRequest();
            return false;
        }
        $PermissionModel=model('PermissionModel');
        $PermissionModel->listFillSession();
        session()->set('user_id',$user_id);
        session()->set('user_data',null);
        return true;
    }
    private function userGet(){
        $user_id=session()->get('user_id');
        $user=session()->get('user_data');
        if(!$user){
            $UserModel=model('UserModel');
            $user=$UserModel->itemGet($user_id);
            // if( !is_object($user) ){
            //     $this->userPhoneRequest();
            //     return null;
            // }
            session()->set('user_data',$user);
        }
        return $user;
    }
    private function userSignUp($user_phone){
        $this->userSignOut();

        $telegramChatId=$this->Telegram->ChatID();
        $user_phone_cleared=clearPhone($user_phone);
        $UserModel=model("UserModel");
        $user_id=$UserModel->where('user_phone',$user_phone_cleared)->get()->getRow('user_id');
        if(!$user_id){
            return $this->sendText("Вам необходимо сначала зарегистрироваться номером $user_phone_cleared на сайте https://tezkel.com");

        }
        $UserModel->query("UPDATE user_list SET user_data=JSON_SET(COALESCE(user_data,'{}'),'$.telegramChatId','$telegramChatId') WHERE user_id='$user_id'");
        $this->sessionSetup($telegramChatId);
        $signin_ok=$this->userSignIn();
        if($signin_ok){
            $user=$this->userGet();
            $this->sendText("Приветствую вас, {$user->user_name}",['reply_markup' => null,]);
            return $this->sendMainMenu();
        }
    }
    private function userPhoneRequest(){
        $this->userSignOut();
        $option=[
            [$this->Telegram->buildKeyboardButton("⏩⏩⏩ НАЖМИ СЮДА ⏪⏪⏪",true)]
        ];
        $keyb = $this->Telegram->buildKeyBoard($option, $onetime=true);
        $content=[
            'chat_id'=>$this->Telegram->ChatID(),
            'reply_markup' => $keyb,
            'text' => "Нажмите на кнопку \"НАЖМИ СЮДА\" внизу, чтобы начать общение. Мне необходим ваш номер телефона учетной записи на https://tezkel.com",
            'disable_web_page_preview'=>1,
        ];
        $this->Telegram->sendMessage($content);
    }
}