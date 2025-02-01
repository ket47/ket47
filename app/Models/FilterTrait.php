<?php
namespace App\Models;
trait FilterTrait{
    public function filterMake( $filter=null, $use_model_table=true ){
        if( !$filter ){
            return null;
        }
        $filter[$this->primaryKey]??=0;
        $filter['is_active']??=1;
        $filter['is_disabled']??=0;
        $filter['is_deleted']??=0;
        $filter['offset']??=0;
        $filter['limit']??=30;
        $filter['order']??=0;
        $filter['reverse']??=0;
        
        if( $use_model_table ){
            $table="{$this->table}.";
        } else {
            $table='';
        }
        $this->filterStatus($filter);
        if( $filter[$this->primaryKey] ){
            $this->where($this->primaryKey,$filter[$this->primaryKey]);
        }
        
        if( !empty($filter['name_query']) && !empty($filter['name_query_fields']) ){
            $cases=[];
            $fields= explode(',', $filter['name_query_fields']);
            $clues=  explode(' ', trim($filter['name_query']));
            foreach( $fields as $field ){
                if( !in_array($field,$this->selectableFields??$this->allowedFields) ){ //FILTER ONLY EXISTING FIELDS
                    continue;
                }
                $words=[];
                foreach($clues as $clue){
                    //$clue=trim($clue);
                    if( !$clue || $clue=='' || $clue==' ' ){
                        continue;
                    }
                    $not='';
                    if( substr($clue,0,1)=='!' ){
                        $clue=substr($clue,1);
                        $not=' NOT ';
                    }
                    $words[]="{$table}$field $not LIKE '%".$this->escapeLikeString(trim($clue))."%' ESCAPE '!'";
                }
                if( $words ){
                    $cases[]=implode(' AND ',$words);
                }
            }
            if( $cases ){
                $this->where('('.implode(' OR ', $cases).')');
            }
        }
        if( $filter['offset'] ){
            $this->offset((int)$filter['offset']);
        }
        if( $filter['limit'] ){
            $this->limit((int)$filter['limit']);
        }
        if( $filter['order'] ){
            $this->orderBy($filter['order'],'ASC');
        }
        if( $filter['reverse'] ){
            $this->orderBy($filter['reverse'],'DESC');
        }
    }
    
    
    private function filterStatus($filter){
        $user_id=session()->get('user_id');
        $status_where=[];
        if( $filter['is_disabled'] && $user_id>0 ){//admin filters
            $permitWhere=$this->permitWhereGet('r','disabled');
            $status_where[]="{$this->table}.is_disabled=1 AND $permitWhere";
        }
        if( $filter['is_deleted'] && $user_id>0 ){//admin filters
            $permitWhere=$this->permitWhereGet('r','disabled');
            //$olderStamp= new \CodeIgniter\I18n\Time("-1 days");
            $status_where[]="{$this->table}.deleted_at IS NOT NULL AND $permitWhere";
        } else {
            $this->where("{$this->table}.deleted_at IS NULL");
        }
        if( $filter['is_active'] ){
            $status_where[]="({$this->table}.is_disabled=0 AND {$this->table}.deleted_at IS NULL)";
        }
        if( $status_where ){
            $this->where( '('.implode(' OR ',$status_where).')' );
        } else {
            $this->where( '1=2' );
        }
    }
}