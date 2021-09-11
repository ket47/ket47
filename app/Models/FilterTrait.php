<?php
namespace App\Models;
trait FilterTrait{
    protected  function filterMake( $filter=null ){
        if( !$filter ){
            return null;
        }
        if( $filter[$this->primaryKey]??=0 ){
            $this->whereIn($this->primaryKey,$filter[$this->primaryKey]);
        }
        if( $filter['show_disabled']??=0 ){
            $this->where('is_disabled',0);
        }
        if( $filter['name_query']??=0 && $filter['name_query_fields']??=0 ){
            $fields= explode(',', $filter['name_query_fields']);
            $clues=explode(' ',$filter['name_query']);
            foreach( $fields as $field ){
                foreach($clues as $clue){
                    $this->orLike($field,$clue);
                }
            }
        }
        if( $filter['limit']??=0 ){
            $this->limit($filter['limit']);
        }
        if( $filter['order']??=0 ){
            $this->orderBy($filter['order'],'ASC');
        }
    }
}