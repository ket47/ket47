<?php

function sudo(){
    $user_data=session()->get('user_data')??null;
    if( isset($user_data->member_of_groups->user_group_types) && str_contains($user_data->member_of_groups->user_group_types,'admin') ){
        return true;
    }
    return false;
}