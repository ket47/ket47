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
        'image_hash',
        'image_order'
        ];

    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    
    public function itemGet( $image_id ){
        return $this->where('image_id',$image_id)->get()->getRow();
    }
    
    public function itemCreate( $data, $limit=5 ){
        $inserted_count=$this
                ->select("COUNT(*) inserted_count")
                ->where('image_holder_id',$data['image_holder_id'])
                ->where('image_holder',$data['image_holder'])
                ->get()
                ->getRow('inserted_count');
        if( $inserted_count>=$limit ){
            return 'limit_exeeded';
        }
        $this->allowedFields[]='is_disabled';
        $this->allowedFields[]='owner_id';
        $data['image_order']=$inserted_count+1;
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
    
    public function itemUpdateOrder( $image_id, $dir ){
        $sql="
            SELECT 
                il.image_id, 
                IF(il.image_id=$image_id,IF('$dir'='up',il.image_order-1.5,il.image_order+1.5),il.image_order) calculated_order,
                @order:=@order+1 image_order
            FROM
                image_list il
                JOIN (SELECT @order:=0) i
                JOIN image_list i2 USING(image_holder_id,image_holder)
            WHERE
                i2.image_id=$image_id
            ORDER BY calculated_order;
            ";
        $image_list=$this->query($sql)->getResult();
        return $this->updateBatch($image_list,'image_id');
    }
    
    public function itemDelete( $image_id ){
        return $this->delete($image_id);
    }
    
    public function itemDisable( $image_id, $is_disabled ){
        $this->allowedFields[]='is_disabled';
        return $this->update(['image_id'=>$image_id],['is_disabled'=>$is_disabled]);
    }
    
    public function itemPurge( $image_id ){
        $image=$this->itemGet($image_id);
        
        $found_sources=glob(WRITEPATH.'images/'.$image->image_hash.'*');
        $found_optimised=glob(WRITEPATH.'images/optimised/'.$image->image_hash.'*');
        $found=array_merge($found_optimised,$found_sources);
        foreach($found as $filename){
            unlink($filename);
        }
        return $this->delete([$image_id],true);
    }
    
    
    
    
    
    
    public function listGet( $filter ){
        $filter['order']='image_order';
        $filter['limit']=5;
        $this->filterMake($filter);
        $this->where('image_holder',$filter['image_holder']);
        $this->where('image_holder_id',$filter['image_holder_id']);
        return $this->get()->getResult();
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete( $image_holder, $image_holder_id ){
        $this->where('image_holder',$image_holder)
                ->where('image_holder_id',$image_holder_id)
                ->delete();
        return false;
    }
    
    public function listPurge( $olderThan=7, $image_holder=null, $image_holder_id=null ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan days");
        $this->where('deleted_at<',$olderStamp);
        if( $image_holder ){
            $this->where('image_holder',$image_holder);
        }
        if( $image_holder_id ){
            $this->where('image_holder_id',$image_holder_id);
        }
        $list_to_purge=$this->select('image_id')->get()->getResult();
        foreach( $list_to_purge as $item_to_purge ){
            $this->itemPurge($item_to_purge->image_id);
        }
        return 'list_purge_ok';
    }
    
}
