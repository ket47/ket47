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
            'message_subject'=>'The title of notification',
            'message_data'=>(object)[
                //'badge'=>'https://tezkel.com/img/icons/monochrome.png',
                'tag'=>'order',
                //'vibrate'=>'[200,100,200]',
                'link'=>'https://tezkel.com/order/order-850'
            ]
        ]);
    }
}
