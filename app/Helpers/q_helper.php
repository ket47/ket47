<?php

function q( $self, $die=true ){
    echo $self->getLastQuery();
    if($die)
        die();
}
function ql($self){
    log_message('error',$self->getLastQuery());
}