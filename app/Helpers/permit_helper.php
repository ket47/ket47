<?php

function permit( $permission ){
    $session=session();
    $permissions=$session->getValue('user_permissions');
    if( $permissions && in_array("|$permission|", $permissions) ){
        return true;
    }
    throw new Exception('Permission denied for '.$permission,401);
}
