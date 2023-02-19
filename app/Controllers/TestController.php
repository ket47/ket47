<?php
namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

if(getenv('CI_ENVIRONMENT')!=='development'){
    die('!!!');
}


class TestController extends \App\Controllers\BaseController{
    use ResponseTrait;

    public function trans(){
        $trans=(object)[
            'trans_id'=>123,
            'trans_amount'=>333.33,
            'trans_role'=>'capital.profit->supplier',
            'trans_tags'=>"#orderCommission",
            'trans_links'=>'store:25 type::orderComission courier:6 customer:45',
            'trans_description'=>'test test test',
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>'-100,-1',
            'is_disabled'=>0,
            'trans_holder'=>'order',
            'trans_holder_id'=>333
        ];






        $TransactionHolderModel=model('TransactionHolderModel');
        $TransactionHolderModel->itemLink($trans->trans_id,$trans);
    }
    public function del(){



        $trans=(object)[
            'trans_id'=>123,
            'trans_amount'=>333.33,
            'trans_role'=>'capital.profit->supplier',
            'trans_tags'=>"#orderCommission",
            'trans_links'=>'store:25 type::orderComission courier:6 customer:45',
            'trans_description'=>'test test test',
            'owner_id'=>0,//customer should not see
            'owner_ally_ids'=>'-100,-1',
            'is_disabled'=>0,
            'trans_holder'=>'order',
            'trans_holder_id'=>333
        ];
        $TransactionModel=model('TransactionModel');

        $TransactionModel->itemCreate($trans);
    }
}
