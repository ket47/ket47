<?php
namespace App\Models;
class SearchModel extends SecureModel{
    
    use PermissionTrait;
    use FilterTrait;
    
    protected $table      = 'product_list';
    protected $primaryKey = 'product_id';
    protected $returnType = 'object';
    protected $allowedFields = [

        ];
    
    public function listGet( string $query=null ){
        return false;
    }

    /**
     * Function groups all search results by store
     */
    public function storeMatchesGet( array $filter ){
        //helper('bench');
        $near_stores=$this->storeNearGet( $filter['location_id']??null, $filter['location_latitude']??null, $filter['location_longitude']??null, $filter['query']??null );
        //bench('storeNearGet');
        if( empty($near_stores['store_list']) ){
            return 'store_notfound';
        }
        $filter['store_ids']=$near_stores['id_list'];
        $this->matchTableCreate($filter);

        $bulider=db_connect();
        $store_rank_list=$bulider
        ->table('tmp_search')
        ->select('store_id')
        ->groupBy('store_id')
        ->orderBy("SUM(score)+MAX(score)","DESC")
        ->get()->getResult();
        //bench('store_rank_list');

        function storeFind($store_list,$store_id):object{
            foreach($store_list as $store){
                if($store->store_id==$store_id){
                    return $store;
                }
            }
        }
        $grouped=[];
        $productmatch_list=[];
        foreach( $store_rank_list as $rank ){
            $store=storeFind($near_stores['store_list'],$rank->store_id);
            $store->matches=$bulider->table('tmp_search')->where('store_id',$store->store_id)->limit(12)->get()->getResult();
            $grouped[]=$store;
            $productmatch_list[]=$rank->store_id;
        }

        if( $filter['query']??null ){
            $query=$filter['query'];
            $cleaned_query=str_replace([',','.',';','!','?'],'|',$query);
            foreach($near_stores['store_list'] as $store){
                if( in_array($store->store_id,$productmatch_list) ){
                    continue;
                }
                if( !preg_match("|($cleaned_query)|iu", $store->store_name) ){
                    continue;
                }
                $store=storeFind($near_stores['store_list'],$store->store_id);
                array_unshift($grouped,$store);
            }
        }
        //bench('grouped');
        return $grouped;
    }

    /**
     * Gives available stores and caches it
     */
    private function storeNearGet( int $location_id=null, float $location_latitude=null, float $location_longitude=null ){
        $cachehash=md5("$location_id,$location_latitude,$location_longitude");
        $storesearchcache=session()->get('storesearchcache')??[];
        if( isset($storesearchcache[$cachehash]['expired_at']) && $storesearchcache[$cachehash]['expired_at']>time() ){
            return $storesearchcache[$cachehash];
        }
        $cache_live_time=15*60;//minutes
        $till_end_of_hour=(60-date('i'))*60-1;//till the hh:59:59 when store can close
        $expired_at=time()+min($cache_live_time,$till_end_of_hour);

        $LocationModel=model('LocationModel');
        $StoreModel=model('StoreModel');
        if( $location_id ){
            $LocationModel->select('location_latitude,location_longitude');
            $LocationModel->where('location_id',$location_id);
            $loc=$LocationModel->get()->getRow();
            extract((array)$loc);
        }
        if( $location_latitude && $location_longitude ){
            /**
             * Bounding box delta per meter. very rough calculation 
             */
            // $bb_latpm=0.000009000900090009001;
            // $bb_lonpm=0.00001270648330058939;
            $delivery_radius=(int) getenv('delivery.radius');
            $StoreModel->join('location_list',"location_holder='store' AND location_holder_id=store_id AND is_main=1");
            $StoreModel->select("ST_Distance_Sphere(POINT('$location_longitude','$location_latitude'),location_point) distance,store_delivery_radius,store_delivery_allow");
            $StoreModel->having("distance < GREATEST(IFNULL(store_delivery_radius*store_delivery_allow,0),$delivery_radius)");
        }
        $weekday=date('N')-1;
        $nextweekday=date('N')%7;
        $dayhour=date('H');

        $StoreModel->select('store_id,store_name,image_hash,store_time_preparation');
        $StoreModel->select("IS_STORE_OPEN(store_time_opens_{$weekday},store_time_closes_{$weekday},$dayhour) is_opened");
        $StoreModel->select("store_time_opens_{$weekday} store_time_opens,store_time_opens_{$nextweekday} store_next_time_opens,store_time_closes_{$weekday} store_time_closes,store_time_closes_{$nextweekday} store_next_time_closes");

        // if( $query ){
        //     $like=preg_replace('|\\W+|u', '%',$query);
        //     $StoreModel->select("store_name LIKE '%$like%' name_match");
        // }
        $StoreModel->join('image_list',"image_holder='store' AND image_holder_id=store_id AND image_list.is_main=1",'left');
        $StoreModel->where('store_list.is_working',1);
        $StoreModel->where('store_list.is_disabled',0);
        $StoreModel->where('store_list.deleted_at IS NULL');

        $store_list=$StoreModel->get()->getResult();
        foreach($store_list as $store){
            $id_list[]=$store->store_id;
        }

        $list=[
            'id_list'=>$id_list,
            'store_list'=>$store_list,
            'expired_at'=>$expired_at
        ];
        session()->set('storesearchcache',["$cachehash"=>$list]); 
        return $list;
    }

    /**
     * Creates tmp table with query matches and orders by score
     */
    private function matchTableCreate( array $filter ){
        $productdescr_weight=0.5;
        $groupdescr_weight=0.2;
        $perk_weight=0.2;

        if( $filter['store_id']??0 ){
            $this->where('store_id',$filter['store_id']);
        }
        if( $filter['limit']??0 ){
            $this->limit($filter['limit']);
        }
        if( $filter['offset']??0 ){
            $this->offset($filter['offset']);
        }
        $search_query=$this->db->escapeString($filter['query']??null);
        $query_score=1;
        if( $search_query ){
            $against='';
            $search_query=str_replace([',','.',';','!','?'],' ',$search_query);
            $words=explode(' ',$search_query);
            foreach($words as $word){
                if(mb_strlen($word)>3){
                    $word_root=mb_substr($word,0,4);
                    $against.=" (>$word <$word_root*)";
                } else 
                    $against.=" $word";
                
            }
            $query_score="( (MATCH(product_name) AGAINST ('$against' IN BOOLEAN MODE)) + (MATCH(product_description) AGAINST ('$against' IN BOOLEAN MODE))*$productdescr_weight + (MATCH(group_description) AGAINST ('$against' IN BOOLEAN MODE))*$groupdescr_weight )";
            $this->where($query_score,null,false);
        }
        $score="$query_score*(1+COUNT(perk_id)*$perk_weight)";

        $now=date("Y-m-d H:i:s");
        $this->select("
            product_id,
            product_name,
            product_quantity,
            product_quantity_reserved,
            product_quantity_min,
            product_code,
            product_list.is_disabled,
            is_counted
        ");
        $this->select("store_id,image_hash");
        $this->select("$score score",false);
        $this->select("ROUND(IF(IFNULL(product_promo_price,0)>0 AND `product_price`>`product_promo_price` AND product_promo_start<NOW() AND product_promo_finish>NOW(),product_promo_price,product_price)) product_final_price");
        $this->join('product_group_member_list pgml','member_id=product_id','left');
        $this->join('product_group_list pgl','pgl.group_id=pgml.group_id','left');
        $this->join('perk_list',"perk_holder='product' AND perk_holder_id=product_id AND expired_at>'$now'",'left');
        $this->join('image_list',"image_holder='product' AND image_holder_id=product_id AND image_list.is_main=1");
        $this->whereIn('store_id',$filter['store_ids']);
        $this->where("(`product_parent_id` IS NULL OR `product_parent_id`=`product_id`)");
        $this->where('product_list.is_disabled',0);
        $this->where('product_list.deleted_at IS NULL');
        $this->where('validity>50');
        $this->orderBy('score','DESC');
        $this->groupBy('member_id');

        $found_sql=$this->builder->getCompiledSelect(true);
        //bench('matchTableCreate SQL');
        //pl($found_sql);
        $this->query("DROP TEMPORARY TABLE IF EXISTS tmp_search");//TEMPORARY
        $this->query("CREATE TEMPORARY TABLE tmp_search AS ($found_sql)");
        //bench('matchTableCreate');
    }

    private function transliterate($input) {
        $map=[
            'cyr' => [
                ' ', 'ё',  'ж',   'ц',  'ч',   'щ',  'ш',  'ю',  'я',
                'а', 'б', 'в', 'г', 'д', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'ы', 'е', 'э', 'х',
            ],
            'lat' => [
                ' ', 'yo', 'j', 'ts', 'ch', 'shch', 'sh', 'yu', 'ya',
                'a', 'b', 'v', 'g', 'd', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'y', 'e', 'e', 'h',
            ]
        ];
        $from=$map['lat'];
        $to=$map['cyr'];
        if( in_array(mb_substr($input,0,1),$map['cyr']) ){
            $from=$map['cyr'];
            $to=$map['lat'];
        }
        $result='';
        do{
            $continue=false;
            foreach( $from as $i=>$char ){
                if( mb_strpos($input, $char)===0 ){
                    $result.=$to[$i];
                    $input=mb_substr($input,mb_strlen($char));
                    $continue=true;
                }
            }
        } while ( $continue );
        return $result;
    }

    public function suggestionListGet( array $filter ){
        $search_query=mb_strtolower( str_replace([',','.',';','!','?','\'','"'],' ',trim($filter['query']??null)) );
        if( empty($search_query) ){
            return 'invalid_query';
        }
        $near_stores=$this->storeNearGet( $filter['location_id']??null, $filter['location_latitude']??null, $filter['location_longitude']??null, $filter['query']??null );
        
        return $this->suggestionListPrepare( $search_query, $near_stores['id_list'] );
    }

    private function suggestionListPrepare( string $search_query, array $store_ids ){
        $limit=7;
        $like=$this->db->escapeLikeString($search_query);
        $or_like=$this->transliterate($like);

        $bulider=db_connect();
        $products = $bulider
        ->table('product_list')
        ->select('LOWER(product_name) suggestion')
        //->select("LOWER(REGEXP_REPLACE(product_name,'[^([:alpha:][:space:])]','')) suggestion")
        ->where('is_disabled',0)
        ->where('deleted_at IS NULL')
        ->like('product_name',$like,'after')
        ->orLike('product_name',$or_like,'after')
        ->whereIn('store_id',$store_ids)
        ->orderBy('LENGTH(product_name)')
        ->limit($limit);

        $stores =   $bulider
        ->table('store_list')
        ->select('LOWER(store_name) suggestion')
        //->select("LOWER(REGEXP_REPLACE(store_name,'[^([:alpha:][:space:])]','')) suggestion")
        ->like('store_name',$like,'after')
        ->orLike('store_name',$or_like,'after')
        ->whereIn('store_id',$store_ids)
        ->orderBy('LENGTH(store_name)')
        ->limit($limit);

        $suggestions=$bulider
        ->newQuery()
        ->fromSubquery($products->union($stores), 'q')
        ->orderBy('LENGTH(suggestion)')
        ->limit($limit)
        ->get()
        ->getResult();

        if( $suggestions ){
            foreach($suggestions as $row){
                $row->suggestion=preg_replace('/[^\w\s]/u','',$row->suggestion);
            }
        }
        return $suggestions;
    }
}