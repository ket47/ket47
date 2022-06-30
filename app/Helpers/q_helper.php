<?php

function q( $self, $die=true ){
    echo $self->getLastQuery();
    if($die)
        die();
}