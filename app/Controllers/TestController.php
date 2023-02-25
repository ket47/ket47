<?php
namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

if(getenv('CI_ENVIRONMENT')!=='development'){
    die('!!!');
}


class TestController extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    public function push(){



        $Messenger=new \App\Libraries\Messenger;
        $Messenger->itemSend((object)[
            'message_reciever_id'=>-100,
            'message_transport'=>'telegram',
            'message_text'=>'user signin',
        ]);
    }
}
