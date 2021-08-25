<?php

function permit( $permission ){
    $session=session();
    $permissions=$session->getValue('user_permissions');
    if( $permissions && in_array("|$permission|", $permissions) ){
        return true;
    }
    return false;
}
