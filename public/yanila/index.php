<?php
include '../../../yanilama/UpdateInstaller.php';
$you=isset($_GET['you'])?$_GET['you']:0;
$UpdateInstaller = new UpdateInstaller( $you );