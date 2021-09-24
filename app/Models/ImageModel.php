<?php
namespace App\Models;
use CodeIgniter\Model;

class ImageModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'image_list';
    protected $primaryKey = 'image_id';
    protected $allowedFields = [
        'image_holder',
        'image_holder_id',
        'image_hash'
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    
    public function itemGet( $image_id ){
        return $this->where('image_id',$image_id)->get()->getRow();
    }
    
    public function itemCreate( $data ){
        $this->allowedFields[]='is_disabled';
        $this->allowedFields[]='owner_id';
        $data['is_disabled']=1;
        $data['owner_id']=session()->get('user_id');
        $data['image_hash']=md5(microtime().rand(1,1000));
        if( $this->insert($data) ){
            return $data['image_hash'];
        }
        return null;
    }
    
    public function itemUpdate( $data ){
        return $this->update($data['image_id'],$data);
    }
    
    public function itemDelete( $image_id ){
        $image=$this->itemGet($image_id);
        
        $found_sources=glob(WRITEPATH.'images/'.$image->image_hash.'*');
        $found_optimised=glob(WRITEPATH.'images/optimised/'.$image->image_hash.'*');
        $found=array_merge($found_optimised,$found_sources);
        foreach($found as $filename){
            unlink($filename);
        }
        return $this->delete([$image_id],true);
    }
    
    public function itemDisable( $image_id, $is_disabled ){
        $this->allowedFields[]='is_disabled';
        return $this->update(['image_id'=>$image_id],['is_disabled'=>$is_disabled]);
    }
    
    
    
    
    
    
    
    public function listGet( $filter ){
        $this->filterMake($filter);
        return $this->get()->getResult();
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete(){
        return false;
    }
    
}
