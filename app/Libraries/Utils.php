<?php
namespace App\Libraries;
class Utils{
    public function fileDownloadImage($source,$image_hash){
        $destination=WRITEPATH.'images/'.$image_hash.'.webp';

        copy($source,$destination);
        $mime_type=mime_content_type($destination);
        if(!str_contains($mime_type, 'image')){
            unlink($destination);
            return;
        }
        return \Config\Services::image()
        ->withFile($destination)
        ->resize(1024, 1024, true, 'height')
        ->convert(IMAGETYPE_WEBP)
        ->save();
    }
}   