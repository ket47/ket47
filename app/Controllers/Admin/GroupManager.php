<?php
namespace App\Controllers\Admin;
use \CodeIgniter\API\ResponseTrait;

class GroupManager extends \App\Controllers\BaseController {
    use ResponseTrait;
    
    public function itemCreate(){
        $group_table=$this->request->getVar('group_table');
        $group_name=$this->request->getVar('group_name');
        $group_parent_id=$this->request->getVar('group_parent_id');
        
        if($group_table=='product_group_list'){
            $GroupModel=model('ProductGroupModel');
        } else if($group_table=='store_group_list'){
            $GroupModel=model('StoreGroupModel');
        } else if($group_table=='user_group_list'){
            $GroupModel=model('UserGroupModel');
        } else if($group_table=='order_group_list'){
            $GroupModel=model('OrderGroupModel');
        } else if($group_table=='trans_group_list'){
            $GroupModel=model('TransGroupModel');
        } else if($group_table=='courier_group_list'){
            $GroupModel=model('CourierGroupModel');
        } else if($group_table=='location_group_list'){
            $GroupModel=model('LocationGroupModel');
        }
        $result=$GroupModel->itemCreate( $group_parent_id, $group_name, '');
        if( is_numeric($result) ){
            return $this->respondCreated($result);
        }
        return $this->fail($result);
    }

    public function itemUpdate(){
        $data= $this->request->getJSON();
        if($data->group_table=='product_group_list'){
            $GroupModel=model('ProductGroupModel');
        } else if($data->group_table=='store_group_list'){
            $GroupModel=model('StoreGroupModel');
        } else if($data->group_table=='user_group_list'){
            $GroupModel=model('UserGroupModel');
        } else if($data->group_table=='order_group_list'){
            $GroupModel=model('OrderGroupModel');
        } else if($data->group_table=='trans_group_list'){
            $GroupModel=model('TransGroupModel');
        } else if($data->group_table=='courier_group_list'){
            $GroupModel=model('CourierGroupModel');
        } else if($data->group_table=='location_group_list'){
            $GroupModel=model('LocationGroupModel');
        }
        $result=$GroupModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $GroupModel->errors() ){
            return $this->failValidationErrors(json_encode($GroupModel->errors()));
        }
        return $this->respondUpdated($result);     
    }
    
    public function itemDelete(){
        $group_id=$this->request->getVar('group_id');
        $group_table=$this->request->getVar('group_table');
        if($group_table=='product_group_list'){
            $GroupModel=model('ProductGroupModel');
        } else if($group_table=='store_group_list'){
            $GroupModel=model('StoreGroupModel');
        } else if($group_table=='user_group_list'){
            $GroupModel=model('UserGroupModel');
        } else if($group_table=='order_group_list'){
            $GroupModel=model('OrderGroupModel');
        } else if($group_table=='trans_group_list'){
            $GroupModel=model('TransGroupModel');
        } else if($group_table=='courier_group_list'){
            $GroupModel=model('CourierGroupModel');
        } else if($group_table=='location_group_list'){
            $GroupModel=model('LocationGroupModel');
        }
        $result=$GroupModel->itemDelete($group_id);
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    
    
    public function index(){
        if( !sudo() ){
            die('Access denied!');
        }
        $ProductGroupModel=model('ProductGroupModel');
        $StoreGroupModel=model('StoreGroupModel');
        $OrderGroupModel=model('OrderGroupModel');
        $UserGroupModel=model('UserGroupModel');
        $CourierGroupModel=model('CourierGroupModel');
        $TransGroupModel=model('TransGroupModel');
        $LocationGroupModel=model('LocationGroupModel');
        
        $tables=[];
        $tables[]=(object)[
                'name'=>'Product groups',
                'type'=>'product',
                'entries'=>$ProductGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'Order groups',
                'type'=>'order',
                'entries'=>$OrderGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'Store groups',
                'type'=>'store',
                'entries'=>$StoreGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'User groups',
                'type'=>'user',
                'entries'=>$UserGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'Trans groups',
                'type'=>'trans',
                'entries'=>$TransGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'Courier statuses',
                'type'=>'courier',
                'entries'=>$CourierGroupModel->listGet()
                ];
        $tables[]=(object)[
                'name'=>'Location types',
                'type'=>'location',
                'entries'=>$LocationGroupModel->listGet()
                ];
        return view('admin/group_manager.php',['tables'=>$tables]);
    }
    
    public function listGet(){
        $group_table=$this->request->getVar('group_table');
        $GroupModel=model('GroupModel');
        $GroupModel->tableSet($group_table);
        $group_list=$GroupModel->listGet();
        if( $GroupModel->errors() ){
            return $this->failValidationErrors(json_encode($GroupModel->errors()));
        }
        return $this->respond($group_list);
    }
    
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload(){
        $group_table=$this->request->getVar('group_table');
        $image_holder_id=$this->request->getVar('image_holder_id');
        $items = $this->request->getFiles();
        if(!$items){
            return $this->failResourceGone('no_files_uploaded');
        }
        foreach($items['files'] as $file){
            $type = $file->getClientMimeType();
            if(!str_contains($type, 'image')){
                continue;
            }
            if ($file->isValid() && ! $file->hasMoved()) {
                $result=$this->fileSaveImage($group_table,$image_holder_id,$file);
                if( $result!==true ){
                    return $result;
                }
            }
        }
        return $this->respondCreated('ok');
    }
    
    private function fileSaveImage( $group_table, $image_holder_id, $file ){
        $image_data=[
            'image_holder'=>$group_table,
            'image_holder_id'=>$image_holder_id
        ];
        if($group_table=='product_group_list'){
            $GroupModel=model('ProductGroupModel');
        } else if($group_table=='order_group_list'){
            $GroupModel=model('OrderGroupModel');
        } else if($group_table=='store_group_list'){
            $GroupModel=model('StoreGroupModel');
        } else if($group_table=='user_group_list'){
            $GroupModel=model('UserGroupModel');
        } else if($group_table=='courier_group_list'){
            $GroupModel=model('CourierGroupModel');
        } else if($group_table=='location_group_list'){
            $GroupModel=model('LocationGroupModel');
        }
        $image_hash=$GroupModel->imageCreate($image_data);
        if( !$image_hash ){
            return $this->failForbidden('forbidden');
        }
        if( $image_hash === 'limit_exeeded' ){
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH.'images/', $image_hash.'.webp');
        
        return \Config\Services::image()
        ->withFile(WRITEPATH.'images/'.$image_hash.'.webp')
        ->resize(1024, 1024, true, 'height')
        ->convert(IMAGETYPE_WEBP)
        ->save();
    }
    
    public function imageDelete(){
        if(!sudo()){
            return $this->failForbidden('forbidden');
        }
        $image_id=$this->request->getVar('image_id');
        
        $ImageModel=model('ImageModel');
        $ImageModel->itemDelete( $image_id );
        $result=$ImageModel->itemPurge( $image_id );
        if( $result===true ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    
}