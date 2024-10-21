<?php
namespace App\Models;
use CodeIgniter\Model;
use Throwable;

class LocationModel extends Model{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'location_list';
    protected $primaryKey = 'location_id';
    protected $allowedFields = [
        'location_prev_id',
        'location_holder',
        'location_holder_id',
        'location_order',
        'location_address',
        'location_comment',
        'location_phone',
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
    protected $useSoftDeletes = true;//if user deletes adress it still available in joins
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';    
    /////////////////////////////////////////////////////
    //ITEM HANDLING SECTION
    /////////////////////////////////////////////////////
    public function itemGet( $location_id, $mode='basic' ){
        $this->select("
            location_id,
            location_holder,
            location_holder_id,
            location_prev_id,
            location_order,
            location_address,
            location_comment,
            location_phone,
            location_latitude,
            location_longitude,
            location_list.is_main
        ");
        if( $mode=='all' ){
            $this->select('image_hash');
            $this->join('location_group_member_list','member_id=location_id','left');
            $this->join('location_group_list','group_id','left');
            $this->join('image_list type_icon',"type_icon.image_holder='location_group_list' AND type_icon.image_holder_id=group_id AND type_icon.is_main=1",'left');
        }
        return $this->where('location_id',$location_id)->get()->getRow();
    }

    private function itemRestrictedFilterout( $address ){
        return trim(str_ireplace([
            'Симферопольский район,',
            'Симферопольский район',
            'Симферополь,',
            'Республика Крым,',
            'Республика Крым',
        ],'',$address));
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
        if( $data['location_address']??null ){
            $data['location_address']=$this->itemRestrictedFilterout($data['location_address']);
        }
        $this->set($data);
        $this->set('location_point',"POINT({$data['location_longitude']},{$data['location_latitude']})",false);
        $this->insert();
        $location_id=$this->getInsertID();
        
        if( $location_id ){
            $this->allowedFields[]='is_disabled';
            $this->allowedFields[]='owner_id';
            $this->allowedFields[]='owner_ally_ids';
            $this->itemMainReset( $data['location_holder'], $data['location_holder_id'] );
            $data['location_order']=$inserted_count+1;
            $data['is_main']=1;
            $this->update($location_id,$data);
            $LocationGroupMemberModel=model('LocationGroupMemberModel');
            if($data['location_group_id']){
                $LocationGroupMemberModel->joinGroup($location_id,$data['location_group_id']);
            } else {
                $LocationGroupMemberModel->joinGroupByType($location_id,$data['location_group_type']);
            }
            return 'ok';
        }
        return 'idle';
    }

    public function itemAdd($data){
        $distanceThreshold=25;//20m
        $distanceToMainPoint=$this->itemMainDistanceFromGet( $data['location_holder'], $data['location_holder_id'],$data['location_longitude'],$data['location_latitude'] );
        if( $distanceToMainPoint && $distanceToMainPoint->span_length<$distanceThreshold ){
            return $this->itemMainRefresh($distanceToMainPoint->location_id);
        }
        $this->itemMainReset( $data['location_holder'], $data['location_holder_id'] );
        $data['location_prev_id']=$distanceToMainPoint->location_id??0;//linking current point to previous to build route
        $data['owner_id']=session()->get('user_id');
        $data['is_disabled']=0;
        $data['is_main']=1;
        $this->allowedFields[]='is_disabled';
        $this->allowedFields[]='owner_id';
        $this->set($data);
        $this->set('location_point',"POINT({$data['location_longitude']},{$data['location_latitude']})",false);
        $this->insert();
        $location_id=$this->getInsertID();
        return $location_id?'ok':'idle';
    }
    
    public function itemUpdate( $data ){
        if(!$data->location_id){
            return 'noid';
        }
        $this->permitWhere('w');
        $this->update($data->location_id,$data);
        return $this->db->affectedRows()?'ok':'idle';
    }

    public function itemMainDistanceFromGet( $location_holder, $location_holder_id, $location_longitude, $location_latitude ){
        $this->where('location_holder',$location_holder);
        $this->where('location_holder_id',$location_holder_id);
        $this->where('location_list.is_main',1);
        $this->select('location_id');
        $this->select("ST_Distance_Sphere(location_point,POINT({$location_longitude}, {$location_latitude})) span_length");
        return $this->get()->getRow();
    }

    public function itemMainGet($location_holder, $location_holder_id){
        $this->where('location_holder',$location_holder);
        $this->where('location_holder_id',$location_holder_id);
        $this->where('location_list.is_main',1);
        $this->select('location_id,location_latitude,location_longitude,location_address,location_comment,image_hash,group_name,group_type');
        //join group & image
        $this->join('location_group_member_list','member_id=location_id','left');
        $this->join('location_group_list','group_id','left');
        $this->join('image_list type_icon',"type_icon.image_holder='location_group_list' AND type_icon.image_holder_id=group_id AND type_icon.is_main=1",'left');

        return $this->get()->getRow();
    }

    public function itemMainSet($location_id){
        $loc=$this->where('location_id',$location_id)->get()->getRow();
        if(!$loc){
            return 'ok';
        }
        $this->itemMainReset( $loc->location_holder, $loc->location_holder_id );
        
        $this->where('location_id',$location_id);
        $this->set(['is_main'=>1]);
        $this->update();
        return $this->db->affectedRows()?'ok':'idle';        
    }
    
    private function itemMainReset( $location_holder, $location_holder_id ){
        $this->where('location_holder',$location_holder);
        $this->where('location_holder_id',$location_holder_id);
        $this->where('is_main',1);
        $this->set(['is_main'=>0]);
        $this->update();
        return $this->db->affectedRows()?'ok':'idle';
    }

    private function itemMainUpdate( $location_id ){
        $loc=$this->where('location_id',$location_id)->get()->getRow();
        if(!$loc){
            return 'ok';
        }
        $this->itemMainReset( $loc->location_holder, $loc->location_holder_id );
        
        $this->where('location_holder',$loc->location_holder);
        $this->where('location_holder_id',$loc->location_holder_id);
        $this->where('is_disabled',0);
        $this->where('deleted_at IS NULL');
        $this->orderBy('is_main','DESC');
        $this->orderBy('location_address');
        $this->limit(1);
        $this->set(['is_main'=>1]);
        $this->update();
        return $this->db->affectedRows()?'ok':'idle';
    }

    /**
     * refreshes main point, sets updated_at to current time stamp
     */
    private function itemMainRefresh($location_id){
        $this->allowedFields[]='updated_at';
        $this->permitWhere('w');
        $this->set('updated_at','NOW()',false);
        $this->where('location_id',$location_id);
        $this->update();
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function itemDelete( int $location_id ){
        if(!$location_id){
            return 'noid';
        }
        $this->permitWhere('w');
        $this->where('location_id',$location_id)->delete();
        $ok=$this->db->affectedRows()?'ok':'idle';
        $this->itemMainUpdate( $location_id );
        return $ok;
    }
    
    public function itemDisable( $location_id, $is_disabled ){
        $this->allowedFields[]='is_disabled';
        $ok=$this->update(['location_id'=>$location_id],['is_disabled'=>$is_disabled]);
        $this->itemMainUpdate( $location_id );
        return $ok;
    }
    
    public function itemPurge( $location_id ){
        // $loc=$this->itemGet($location_id);
        // if( !$loc ){
        //     return true;
        // }
        // if( !$loc->deleted_at ){
        //     return false;
        // }
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
            location_comment,
            location_phone,
            location_list.is_main,
            group_name,
            group_type,
            type_icon.image_hash image_hash";
    public function listGet( $filter ){
        $filter['order']=null;
        $filter['limit']=15;
        $this->filterMake($filter);
        if( in_array($filter['location_holder'],['courier','user']) ){
            $this->permitWhere('r');
        }
        $this->select($this->selectList);
        $this->where('location_holder',$filter['location_holder']);
        $this->where('location_holder_id',$filter['location_holder_id']);
        $this->join('location_group_member_list','member_id=location_id','left');
        $this->join('location_group_list','group_id','left');
        $this->join('image_list type_icon',"type_icon.image_holder='location_group_list' AND type_icon.image_holder_id=group_id AND type_icon.is_main=1",'left');
        $this->orderBy("location_list.is_main DESC,location_list.created_at DESC");
        $this->select($this->selectList);
        return $this->get()->getResult();
    }

    public function listCountGet($filter){
        $this->permitWhere('r');
        $this->where('is_disabled',0);
        $this->where('deleted_at IS NULL');
        $this->where('location_holder',$filter['location_holder']);
        $this->where('location_holder_id',$filter['location_holder_id']);
        $this->select("COUNT(*) location_count");
        return $this->get()->getRow('location_count');
    }

    /**
     * calculates route length by chaining location points
     */
    public function routeLengthGet( string $location_holder, int $location_holder_id, string $start_at, string $finish_at ){
        $this->permitWhere('r');
        $this->where("location_list.created_at>='$start_at'");
        $this->where("location_list.created_at<='$finish_at'");
        $this->where('location_list.location_holder',$location_holder);
        $this->where('location_list.location_holder_id',$location_holder_id);
        $this->select("SUM(ST_Distance_Sphere(location_list.location_point,loc_prev.location_point)) route_length");
        $this->join('location_list loc_prev','loc_prev.location_id=location_list.location_prev_id');
        return $this->get()->getRow('route_length');
    }

    public function distanceToUserInclude(){
        $user_id=session()->get('user_id');
        if($user_id>0){
            $this->query("SET @start_point:=(SELECT location_point FROM location_list WHERE is_main=1 AND location_holder='user' AND location_holder_id=$user_id)");
            $this->select("ST_Distance_Sphere(@start_point,location_point) distance");
            return true;
        }
        return false;
    }
    
    public function listCreate(){
        return false;
    }
    
    public function listUpdate(){
        return false;
    }
    
    public function listDelete( $location_holder, $location_holder_id ){
        $this->permitWhere('w');
        $this->where('location_holder',$location_holder);
        $this->where('location_holder_id',$location_holder_id);
        $this->delete();    
        return $this->db->affectedRows()?'ok':'idle';
    }
    
    public function listUnDelete( $location_holder, $location_holder_id ){
        $olderStamp= new \CodeIgniter\I18n\Time("-1 days");
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
    
    // public function listPurge( $olderThan=45 ){
    //     $olderStamp= new \CodeIgniter\I18n\Time((-1*$olderThan)." hours");
    //     $this->where('created_at<',$olderStamp);
    //     $this->delete(null,true);
    //     return 'ok';
    // }

    public function distanceHolderGet($start_holder,$start_holder_id,$finish_holder,$finish_holder_id){
        $this->query("SET @start_point:=(SELECT location_point FROM location_list WHERE `location_holder`='$start_holder' AND `location_holder_id`='$start_holder_id' AND deleted_at IS NULL LIMIT 1)");
        $this->select("ST_Distance_Sphere(@start_point,location_point) distance");
        $this->where('location_holder',$finish_holder);
        $this->where('location_holder_id',$finish_holder_id);
        $this->where('is_main',1);
        $this->where('is_disabled',0);
        $this->where('deleted_at IS NULL');
        return $this->get()->getRow('distance');
    }
    
    public function distanceGet($start_location_id, $finish_location_id){
        $this->query("SET @start_point:=(SELECT location_point FROM location_list WHERE location_id=$start_location_id)");
        $this->select("ST_Distance_Sphere(@start_point,location_point) distance");
        $this->where('location_id',$finish_location_id);
        return $this->get()->getRow('distance');
    }
    
    public function itemTemporaryCreate( $location_latitude, $location_longitude ){
        if( is_numeric($location_latitude) && abs($location_latitude)<=90 && is_numeric($location_longitude) && abs($location_longitude)<=180 ){
            $this->query("SET @center_point:=POINT('$location_longitude','$location_latitude')");
            return -100;
        }
        return 0;
    }

    public function distanceListGet( int $center_location_id, int $point_distance_limit, string $point_holder=null ){
        if($center_location_id>0){//If location_id<0 use temporary point from itemTemporaryCreate
            $this->query("SET @center_point:=(SELECT location_point FROM location_list WHERE location_id=$center_location_id)");
        }
        try{
            $this->select("
                location_id,
                location_holder_id,
                location_address,
                location_list.updated_at,
                ST_Distance_Sphere(@center_point,location_point) distance");
            if( $point_holder ){
                $this->where('location_holder',$point_holder);
            }
            if( $point_holder=='store' ){
                /**
                 * @todo rewrite this function 
                 */
                $this->select('store_delivery_radius,store_delivery_allow');
                $this->having('distance<=',"GREATEST(IFNULL(store_delivery_radius*store_delivery_allow,0),$point_distance_limit)",false);
            } else {
                $this->having('distance<=',$point_distance_limit);
            }
            $this->orderBy('distance');
            return $this->get()->getResult();
        }catch(Throwable $e){
            return [];
        }
    }
}
