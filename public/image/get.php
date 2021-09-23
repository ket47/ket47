<?php

function image_resize($path, $width, $height) {
    $src = imagecreatefromwebp($path);
    
    $srcw = imagesx($src);
    $srch = imagesy($src);
    $ratio = ( $width / $srcw < $height / $srch ) ? $width / $srcw : $height / $srch;
    $thumb = imagecreatetruecolor($srcw * $ratio, $srch * $ratio);
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $srcw * $ratio, $srch * $ratio, $srcw, $srch);
    return $thumb;
}
$uri=$_SERVER['REQUEST_URI'];
$uri_parts=explode('/',$uri);
$uri_filename=array_pop($uri_parts);
list($hash,$width,$height,$extension)=explode('.',$uri_filename);

$images_path='../../writable/images/';

$content_type='';
$file_optimised=$images_path.'optimised/'.$uri_filename;
if ( !file_exists($file_optimised) ) {
    $file_source=$images_path.$hash.'.webp';
    $thumb= image_resize($file_source, $width, $height);
    
    switch( $extension ){
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumb,$file_optimised);
            $content_type='image/jpeg';
            break;
        case 'png':
            imagepng($thumb,$file_optimised);
            $content_type='image/png';
            break;
        case 'webp':
            imagewebp($thumb,$file_optimised);
            $content_type='image/webp';
            break;
        case 'default':
            http_response_code(404);
            die;
    }
}
header('Cache-Control: public, max-age=604800, immutable');
header('Content-Length: ' . filesize($file_optimised));
header('Content-Type: ' . $content_type);
readfile($file_optimised);
exit;