<?php

namespace App\Controllers;

class Maintain extends \App\Controllers\BaseController{

    public function taskExecute(){
        $this->taskPurge();
    }
    
    private function taskPurge(){
        model("ImageModel")->listPurge();
        model("ProductModel")->listPurge();
        model("StoreModel")->listPurge();
        model("UserModel")->listPurge();
    }
}
