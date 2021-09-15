<?php
namespace App\Models;
trait FilterTrait{
    protected  function filterMake( $filter=null ){
        if( !$filter ){
            return null;
        }
        $filter[$this->primaryKey]??=0;
        $filter['is_active']??=1;
        $filter['is_disabled']??=0;
        $filter['is_deleted']??=0;
        $filter['limit']??=30;
        $filter['order']??=0;
        
        if( $filter[$this->primaryKey] ){
            $this->whereIn($this->primaryKey,$filter[$this->primaryKey]);
        }
        if( $filter['is_disabled'] ){//admin filters
            $this->permitWhere('r','disabled');
            
        }
        
        
        
        
        
        if( $filter['is_active'] ){
            $this->where('is_disabled',0);
            $this->where('deleted_at IS NULL');
        }
        if( sudo() && $filter['is_disabled'] ){
            $this->where('is_disabled',1);
        }
        if( sudo() && $filter['is_deleted'] ){
            $this->where('deleted_at IS NOT NULL');
        }
        if( isset($filter['name_query']) && isset($filter['name_query_fields']) ){
            $fields= explode(',', $filter['name_query_fields']);
            $clues=explode(' ',$filter['name_query']);
            foreach( $fields as $field ){
                foreach($clues as $clue){
                    $this->orLike($field,$clue);
                }
            }
        }
        if( $filter['limit'] ){
            $this->limit($filter['limit']);
        }
        if( $filter['order'] ){
            $this->orderBy($filter['order'],'ASC');
        }
    }
}