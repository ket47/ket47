<?php
namespace App\Models;
use CodeIgniter\Model;

class LocationModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'location_list';
    protected $primaryKey = 'location_id';
    protected $allowedFields = [
        'location_holder',
        'location_holder_id',
        'location_order',
        'location_address',
        'location_latitude',
        'location_longitude',
        'location_point',
        'is_main',
        'deleted_at'
        ];
    protected $validationRules    = [
        'location_holder'     => 'required',
        'location_holder_id'  => 'required',
        'location_latitude'   => 'required',
        'location_longitude'  => 'required',
    ];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $location_id ){
        return $this->where('location_id',$location_id)->get()->getRow();
    }
    
    public function itemCreate( $data, $limit=5 ){
        $inserted_count=$this
                ->select("COUNT(*) inserted_count")
                ->where('location_holder_id',$data['location_holder_id'])
                ->where('location_holder',$data['location_holder'])
                ->where('deleted_at IS NULL')
                ->get()
                ->getRow('inserted_count');
        if( $inserted_count>=$limit ){
            return 'limit_exeeded';
        }
        $this->set($data);
        $this->set('location_point',"POINT({$data['location_latitude']},{$data['location_longitude']})",false);
        $this->insert();
        $location_id=$this->insertID();
        
        $this->allowedFields[]='is_disabled';
        $this->allowedFields[]='owner_id';
        $data['location_order']=$inserted_count+1;
        if( $location_id ){
            $this->update($location_id,$data);
            $this->itemUpdateMain( $location_id );
            $LocationGroupMemberModel=model('LocationGroupMemberModel');
            $LocationGroupMemberModel->joinGroup($location_id,$data['location_type_id']);
            return 'ok';
        }
        return 'idle';
    }
    
    /*public function itemAdd( $data ){
        $this->where('location_holder',$data['location_holder']);
        $this->where('location_holder_id',$data['location_holder_id']);
        $this->where('is_main',1);
        $this->update(['is_main'=>0]);
        $data['is_main']=1;
        return $this->insert($data,true);
    }*/
    
    public function itemUpdate( $data ){
        $this->permitWhere('w');
        return $this->update($data['location_id'],$data);
    }
    
    public function itemUpdateMain( $location_id ){
        $loc=$this->where('location_id',$location_id)->get()->getRow();
        if(!$loc){
            return 'ok';
        }
        $this->where('location_holder',$loc->location_holder);
        $this->where('location_holder_id',$loc->location_holder_id);
        $this->set(['is_main'=>0]);
        $this->update();
        
        $this->where('location_holder',$loc->location_holder);
        $this->where('location_holder_id',$loc->location_holder_id);
        $this->where('is_disabled',0);
        $this->where('deleted_at IS NULL');
        $this->orderBy('location_order');
        $this->limit(1);
        $this->set(['is_main'=>1]);
        $this->update();
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemUpdateOrder( $location_id, $dir ){
        $sql="
            SELECT 
                il.location_id, 
                IF(il.location_id=$location_id,IF('$dir'='up',il.location_order-1.5,il.location_order+1.5),il.location_order) calculated_order,
                @order:=@order+1 location_order
            FROM
                location_list il
                JOIN (SELECT @order:=0) i
                JOIN location_list i2 USING(location_holder_id,location_holder)
            WHERE
                i2.location_id=$location_id
            ORDER BY calculated_order;
            ";
        $location_list=$this->query($sql)->getResult();
        $ok=$this->updateBatch($location_list,'location_id');
        $this->itemUpdateMain( $location_id );
        return $ok;
    }
    
    public function itemDelete( $location_id ){
        $this->permitWhere('w');
        $this->delete($location_id);
        $ok=$this->db->affectedRows()?'ok':'idle';
        $this->itemUpdateMain( $location_id );
        $this->itemPurge( $location_id );
        return $ok;
    }
    
    public function itemDisable( $location_id, $is_disabled ){
        $this->allowedFields[]='is_disabled';
        $ok=$this->update(['location_id'=>$location_id],['is_disabled'=>$is_disabled]);
        $this->itemUpdateMain( $location_id );
        return $ok;
    }
    
    public function itemPurge( $location_id ){
        $loc=$this->itemGet($location_id);
        if( !$loc ){
            return true;
        }
        if( !$loc->deleted_at ){
            return false;
        }
        return $this->delete([$location_id],true);
    }
    /////////////////////////////////////////////////////
    //LIST HANDLING SECTION
    /////////////////////////////////////////////////////
    protected $selectList="
            location_id,
            location_holder,
            location_holder_id,
            location_latitude,
            location_longitude,
            location_address,
            group_name,
            group_type,
            type_icon.image_hash image_hash";
    public function listGet( $filter ){
        $filter['order']='location_order';
        $filter['limit']=5;
        $this->filterMake($filter);
        $this->permitWhere('r');
        $this->select($this->selectList);
        $this->where('location_holder',$filter['location_holder']);
        $this->where('location_holder_id',$filter['location_holder_id']);
        $this->join('location_group_member_list','member_id=location_id','left');
        $this->join('location_group_list','group_id','left');
        $this->join('image_list type_icon',"type_icon.image_holder='location_group_list' AND type_icon.image_holder_id=group_id AND type_icon.is_main=1",'left');
        $this->select($this->selectList);
        return $this->get()->getResult();
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete( $location_holder, $location_holder_id ){
        $this->where('location_holder',$location_holder);
        $this->whereIn('location_holder_id',$location_holder_id);
        $this->delete();
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listUnDelete( $location_holder, $location_holder_id ){
        $olderStamp= new \CodeIgniter\I18n\Time("-".APP_TRASHED_DAYS." days");
        $this->where('location_holder',$location_holder);
        $this->where('location_holder_id',$location_holder_id);
        $this->where('deleted_at>',$olderStamp);
        $this->update(null,['deleted_at'=>NULL]);
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listDeleteDirectly( $location_holder, $location_holder_id ){
        $this->where('location_holder',$location_holder);
        $this->where('location_holder_id',$location_holder_id);
        $this->update(null,['deleted_at'=>'2000-01-01 00:00:00']);
        return $this->db->affectedRows()?'ok':'error';
    }
    
    public function listPurge( $olderThan=45 ){
        $olderStamp= new \CodeIgniter\I18n\Time("-$olderThan days");
        $this->where('created_at<',$olderStamp);
        $this->delete(null,true);
        return 'ok';
    }
    
    public function distanceGet($start_location_id, $finish_location_id){
        $this->query("SET @start_point:=(SELECT location_point FROM location_list WHERE location_id=$start_location_id)");
        $this->select("ST_Distance_Sphere(@start_point,location_point) distance");
        $this->where('location_id',$finish_location_id);
        return $this->get()->getRow('distance');
    }
    
    public function distanceListGet( int $center_location_id, float $point_distance, string $point_holder ){
        $this->query("SET @center_point:=(SELECT location_point FROM location_list WHERE location_id=$center_location_id)");
        $this->select("
            location_id,
            location_holder_id,
            location_address,
            updated_at,
            ST_Distance_Sphere(@center_point,location_point) distance");
        $this->where('location_holder',$point_holder);
        $this->having('distance<=',$point_distance);
        $this->orderBy('distance');
        return $this->get()->getResult();
    }
}
