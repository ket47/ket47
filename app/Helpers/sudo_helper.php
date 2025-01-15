<?php

function sudo(){
    $user_data=session()->get('user_data')??null;
    if( isset($user_data->member_of_groups->group_types) && str_contains($user_data->member_of_groups->group_types,'admin') ){
        return true;
    }
    return false;
}

function courdo(){
    $user_data=session()->get('user_data')??null;
    if( !isset($user_data->member_of_groups->group_types) || !str_contains($user_data->member_of_groups->group_types,'courier') ){
        return false;
    }
    $courier=model('CourierModel')->itemGet(null,'basic');
    if( !$courier || $courier->is_disabled==1 || $courier->deleted_at ){
        return false;
    }
    return true;
}
function stodo(){
    $store=model('StoreModel')->itemOwnedGet(session()->get('user_id'));
    if( empty($store)){
        return false;
    }
    return true;
}

function ownersAll(object $item){
    $owner_all=explode(',',"0,".($item->owner_ally_ids??0).",".($item->owner_id??0) );
    $owner_all_filtered=array_unique($owner_all,SORT_NUMERIC);
    array_shift($owner_all_filtered);
    //return $owner_all_filtered;
    $owner_all_list=implode(',',$owner_all_filtered);
    return $owner_all_list;
}