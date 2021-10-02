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
        
        $this->filterStatus($filter);
        
        if( $filter[$this->primaryKey] ){
            $this->whereIn($this->primaryKey,$filter[$this->primaryKey]);
        }
        
        if( !empty($filter['name_query']) && !empty($filter['name_query_fields']) ){
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
    
    
    private function filterStatus($filter){
        if( $filter['is_active'] && $filter['is_disabled'] && $filter['is_deleted'] ){
            return true;//optimisation if all entries should be shown
        }
        $status_where=[];
        if( $filter['is_disabled'] ){//admin filters
            $this->permitWhere('r','disabled');
            $status_where[]='is_disabled=1';
        }
        if( $filter['is_deleted'] ){//admin filters
            $this->permitWhere('r','disabled');
            $status_where[]='deleted_at IS NOT NULL';
        }
        if( $filter['is_active'] ){
            $status_where[]='(is_disabled=0 AND deleted_at IS NULL)';
        }
        if( $status_where ){
            $this->where( '('.implode(' OR ',$status_where).')' );
        } else {
            $this->where( '1=2' );
        }
    }
}