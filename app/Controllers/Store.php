<?php

namespace App\Controllers;
use \CodeIgniter\API\ResponseTrait;

class Store extends \App\Controllers\BaseController{
    use ResponseTrait;
    
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    public function listGet(){
        $filter=[
            'name_query'=>$this->request->getVar('name_query'),
            'name_query_fields'=>$this->request->getVar('name_query_fields'),
            'is_disabled'=>$this->request->getVar('is_disabled'),
            'is_deleted'=>$this->request->getVar('is_deleted'),
            'is_active'=>$this->request->getVar('is_active'),
            'limit'=>$this->request->getVar('limit'),
            'offset'=>$this->request->getVar('offset'),
            'owner_id'=>$this->request->getVar('owner_id'),
            'owner_ally_ids'=>$this->request->getVar('owner_ally_ids'),
            'order'=>$this->request->getVar('order'),
            'reverse'=>$this->request->getVar('reverse'),
        ];
        $StoreModel=model('StoreModel');
        $store_list=$StoreModel->listGet($filter);
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respond($store_list);
    }
    public function listGroupGet(){
        $StoreGroupModel=model('StoreGroupModel');
        $result=$StoreGroupModel->listGet();
        return $this->respond($result);
    }


    private $appStoreVersionFilter='2.0.8';
    private $appStoreWhitelist=[63,111,68,130,110,155,147];

    private function appStoreFilter( $result ){
        $platform=$this->request->getPost('platform');
        $version=$this->request->getPost('version');
        if( $this->appStoreVersionFilter==$version && in_array('ios',$platform) && in_array('capacitor',$platform)){
            $filtered=[];
            foreach($result as $store){
                if( in_array($store->store_id??0,$this->appStoreWhitelist) ){
                    $filtered[]=$store;
                }
            }
            return $filtered;
        } else {
            $filtered=[];
            foreach($result as $store){
                if( !in_array($store->store_id??0,$this->appStoreWhitelist) ){
                    $filtered[]=$store;
                }
            }
            return $filtered;
        }
        return $result;
    }

    public function listNearGet(){
        $location_id=$this->request->getPost('location_id');
        $location_latitude=$this->request->getPost('location_latitude');
        $location_longitude=$this->request->getPost('location_longitude');
        $response=$this->listNearCache($location_id,$location_latitude,$location_longitude);
        if( !is_array($response['store_list']) ){
            madd('home','get','error');
            return $this->failNotFound('notfound');
        }
        //$result=$this->appStoreFilter($result);

        madd('home','get','ok');
        return $this->respond($response);
    }

    private function listNearCache( int $location_id=null, float $location_latitude=null, float $location_longitude=null ){
        $cachehash=md5("$location_id,$location_latitude,$location_longitude");
        $storenearcache=session()->get('storenearcache')??[];
        if( isset($storenearcache[$cachehash]['expired_at']) && $storenearcache[$cachehash]['expired_at']>time() ){
            return $storenearcache[$cachehash];
        }
        $cache_live_time=15*60;//minutes
        $till_end_of_hour=(60-date('i'))*60-1;//till the hh:59:59 when store can close
        $expired_at=time()+min($cache_live_time,$till_end_of_hour);

        $StoreModel=model('StoreModel');
        $store_list=$StoreModel->listNearGet(['location_id'=>$location_id,'location_latitude'=>$location_latitude,'location_longitude'=>$location_longitude]); 
        $store_groups_htable=[];
        $product_groups_htable=[];

        foreach($store_list as $store){
            $store->cache_groups=json_decode($store->cache_groups);
            if( !$store->cache_groups || $store->cache_groups->expired_at>time() ){
                $store->cache_groups=$StoreModel->itemCacheGroupCreate($store->store_id);
            }
            foreach($store->cache_groups->store_groups as $group_id){
                $store_groups_htable[$group_id]=1;
            }
            foreach($store->cache_groups->product_groups as $group_id){
                $product_groups_htable[$group_id]=1;
            }
        }

        $ProductGroupModel=model('ProductGroupModel');
        $ProductGroupModel->join('image_list','group_id=image_holder_id');
        $ProductGroupModel->where('image_holder','product_group_list');
        $ProductGroupModel->whereIn('group_id',array_keys($product_groups_htable));
        $product_groups=$ProductGroupModel->select('group_id,group_type,group_name,image_hash')->get()->getResult();

        $StoreGroupModel=model('StoreGroupModel');
        $StoreGroupModel->join('image_list','group_id=image_holder_id');
        $StoreGroupModel->where('image_holder','store_group_list');
        $StoreGroupModel->whereIn('group_id',array_keys($store_groups_htable));
        $store_groups=$StoreGroupModel->select('group_id,group_type,group_name,image_hash')->get()->getResult();

        $list=[
            'store_list'=>$store_list,
            'store_groups'=>$store_groups,
            'product_groups'=>$product_groups,
            'expired_at'=>$expired_at,
        ];
        session()->set('storenearcache',["$cachehash"=>$list]);
        return $list;
    }

    public function primaryNearGet(){
        $location_id=$this->request->getVar('location_id');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->primaryNearGet(['location_id'=>$location_id]);
        $result=$this->appStoreFilter([$result])[0]??null;
        if( $result=='not_found' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet(){
        $store_id=(int) $this->request->getPost('store_id');
        $mode=$this->request->getVar('mode');
        $distance_include=$this->request->getVar('distance_include');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemGet($store_id,$mode??'all',$distance_include);
        if( $result==='forbidden' ){
            madd('store','get','error',$store_id,$result);
            return $this->failForbidden($result);
        }
        if( $result==='notfound' ){
            madd('store','get','error',$store_id,$result);
            return $this->failNotFound($result);
        }
        madd('store','get','ok',$store_id,$result->store_name);
        $ReactionModel=model('ReactionModel');
        $result->reactionSummary=$ReactionModel->summaryGet("store:$store_id");
        return $this->respond($result);
    }

    public function itemDeliveryMethodsGet(){
        $store_id=(int) $this->request->getPost('store_id');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemDeliveryMethodsGet($store_id);
        if( !is_object($result) ){
            return $this->fail($result);
        }
        return $this->respond($result);
    }

    public function itemIsReady(){
        $store_id=$this->request->getVar('store_id');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemIsReady($store_id);
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    
    public function itemCreate(){
        $name=$this->request->getVar('name');
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemCreate($name);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='limit_exeeded' ){
            return $this->failResourceExists($result);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respond($result);
    }
    
    public function itemUpdate(){
        $data= $this->request->getJSON();
        $StoreModel=model('StoreModel');
        if( $data->store_delivery_methods??null ){
            $data->store_delivery_methods=strip_tags($data->store_delivery_methods,['<b>','<strong>','<br>','<i>','<u>','<p>','<ul>','<ol>','<li>','<h1>','<h2>','<h3>']);
        }
        $result=$StoreModel->itemUpdate($data);
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respondUpdated($result);
    }
    
    public function itemUpdateGroup(){
        $store_id=$this->request->getVar('store_id');
        $group_id=$this->request->getVar('group_id');
        $is_joined=$this->request->getVar('is_joined');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemUpdateGroup($store_id,$group_id,$is_joined);
        if( $result==='ok' ){
            if( !sudo() ){
                $StoreModel->itemDisable($store_id,1,false);
            }
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    public function itemDelete(){
        $store_id=$this->request->getVar('store_id');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemDelete($store_id);        
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }
    
    public function itemUnDelete(){
        $store_id=$this->request->getVar('store_id');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemUnDelete($store_id);        
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);   
    }
    
    public function itemDisable(){
        $store_id=$this->request->getVar('store_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->itemDisable($store_id,$is_disabled);
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        return $this->fail($result);
    }
    
    
    public function fieldApprove(){
        $store_id=$this->request->getVar('store_id');
        $field_name=$this->request->getVar('field_name');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->fieldApprove( $store_id, $field_name );
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $StoreModel->errors() ){
            return $this->failValidationErrors(json_encode($StoreModel->errors()));
        }
        return $this->respondUpdated($result);
    }

    /////////////////////////////////////////////////////
    //OWNER HANDLING SECTION
    /////////////////////////////////////////////////////
    public function ownerListGet(){
        $store_id=$this->request->getVar('store_id');

        $StoreModel=model('StoreModel');
        $result=$StoreModel->ownerListGet( $store_id );
        if( $result==='unauthorized' ){
            return $this->failUnauthorized($result);
        }
        if( $result==='nostore' ){
            return $this->fail($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->respond($result);
    }
    public function ownerSave(){
        $store_id=$this->request->getVar('store_id');
        $action=$this->request->getVar('action');
        $owner_id=$this->request->getVar('owner_id');
        $owner_phone=$this->request->getVar('owner_phone');

        $StoreModel=model('StoreModel');
        $result=$StoreModel->ownerSave( $action, $store_id, $owner_id, $owner_phone );
        if( $result==='ok' || $result==='idle'){
            return $this->respondUpdated($result);
        }
        if( $result==='forbidden' ){
            return $this->failForbidden($result);
        }
        if( $result==='unauthorized' ){
            return $this->failUnauthorized($result);
        }
        if( $result==='notfound' ){
            return $this->failNotFound($result);
        }
        return $this->fail($result);
    }
    /////////////////////////////////////////////////////
    //IMAGE HANDLING SECTION
    /////////////////////////////////////////////////////
    public function fileUpload(){
        $image_holder=$this->request->getPost('image_holder');
        $image_holder_id=$this->request->getPost('image_holder_id');
        if ( !(int) $image_holder_id ) {
            return $this->fail('no_holder_id');
        }
        $items = $this->request->getFiles();
        if(!$items){
            return $this->failResourceGone('no_files_uploaded');
        }
        $result=false;
        foreach($items['files'] as $file){
            $type = $file->getClientMimeType();
            if(!str_contains($type, 'image')){
                continue;
            }
            if ($file->isValid() && ! $file->hasMoved()) {
                $result=$this->fileSaveImage($image_holder_id,$file,$image_holder);
                if( $result!==true ){
                    return $this->fail($result);
                }
            }
        }
        if($result===true){
            return $this->respondCreated('ok');
        }
        return $this->fail('no_valid_images');
    }
    
    private function fileSaveImage( $image_holder_id, $file, $image_holder='store' ){
        if( !in_array($image_holder,['store','store_avatar','store_dmethods']) ){
            $image_holder='store';
        }
        $image_data=[
            'image_holder'=>$image_holder,
            'image_holder_id'=>$image_holder_id
        ];
        $StoreModel=model('StoreModel');
        $image_hash=$StoreModel->imageCreate($image_data);
        if( !$image_hash ){
            return $this->failForbidden('forbidden');
        }
        if( $image_hash === 'limit_exeeded' ){
            return $this->fail('limit_exeeded');
        }
        $file->move(WRITEPATH.'images/', $image_hash.'.webp');
        
        try{
            return \Config\Services::image()
            ->withFile(WRITEPATH.'images/'.$image_hash.'.webp')
            ->resize(1600, 1600, true, 'height')
            ->convert(IMAGETYPE_WEBP)
            ->save();
        }catch(\Exception $e){
            return $e->getMessage();
        }
    }
    
    public function imageDisable(){
        $image_id=$this->request->getVar('image_id');
        $is_disabled=$this->request->getVar('is_disabled');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->imageDisable( $image_id, $is_disabled );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    
    public function imageDelete(){
        $image_id=$this->request->getVar('image_id');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->imageDelete( $image_id );
        if( $result==='ok' ){
            return $this->respondDeleted($result);
        }
        return $this->fail($result);
    }
    
    public function imageOrder(){
        $image_id=$this->request->getVar('image_id');
        $dir=$this->request->getVar('dir');
        
        $StoreModel=model('StoreModel');
        $result=$StoreModel->imageOrder( $image_id, $dir );
        if( $result==='ok' ){
            return $this->respondUpdated($result);
        }
        return $this->fail($result);
    }
    public function locationCreate(){
        $location_holder_id=$this->request->getVar('location_holder_id');
        $location_group_id=$this->request->getVar('location_group_id');
        $location_group_type=$this->request->getVar('location_group_type');
        $location_longitude=$this->request->getVar('location_longitude');
        $location_latitude=$this->request->getVar('location_latitude');
        $location_address=$this->request->getVar('location_address');

        $data=[
            'location_holder'=>'store',
            'location_holder_id'=>$location_holder_id,
            'location_group_id'=>$location_group_id,
            'location_group_type'=>$location_group_type,
            'location_longitude'=>$location_longitude,
            'location_latitude'=>$location_latitude,
            'location_address'=>$location_address,
            'is_disabled'=>0,
            //'owner_id'=>$location_holder_id   get userIds of store
        ];
        $StoreModel=model('StoreModel');
        $LocationModel=model('LocationModel');
        if( !$StoreModel->permit($location_holder_id,'w') ){
            return $this->failForbidden('forbidden');
        }
        $store=$StoreModel->itemGet($location_holder_id);
        $data['owner_id']=$store->owner_id;
        $data['owner_ally_ids']=$store->owner_ally_ids;
        $result= $LocationModel->itemCreate($data,1);
        if( $LocationModel->errors() ){
            return $this->failValidationErrors(json_encode($LocationModel->errors()));
        }
        return $this->respondCreated($result);
    }
    
    public function locationDelete(){
        //$location_holder_id=$this->request->getVar('location_holder_id');
        $location_id=$this->request->getPost('location_id');
        $LocationModel=model('LocationModel');

        $result=$LocationModel->itemDelete($location_id);
        if( $result=='ok' ){
            return $this->respondDeleted('ok');
        }
        return $this->fail($result);
    }

    public function qrGet(){
        $store_id=$this->request->getGet('store_id');
        $type=$this->request->getGet('type');

        $data=null;
        if( $type=='store' ){
            $data=getenv('app.frontendUrl')."catalog/store-{$store_id}";
        } else 
        if( $type=='menu' ){
            $data=getenv('app.frontendUrl')."catalog/store-{$store_id}/menu";
        }

        $qr=new \App\Libraries\QRCode($data,['w'=>500,'h'=>500]);
        $qr->output_image();
        exit;
    }
}
