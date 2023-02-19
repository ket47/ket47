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

        echo $Messenger->itemSend((object)[
            'message_reciever_id'=>41,
            'message_transport'=>'push',
            'message_text'=>'TETSTST TSET',
            'message_link'=>getenv('app.frontendUrl'),
            'message_tag'=>'#order',
            'message_subject'=>'The title of notification'
        ]);
    }
}
