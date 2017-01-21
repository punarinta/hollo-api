<?php

namespace App\Service;

//  S3 class upgraded with https://github.com/tpyo/amazon-s3-php-class/issues/96

include_once 'vendor/tpyo/amazon-s3-php-class/S3.php';

class File
{
    public static function createThumbnail($source_image_path, $thumbnail_image_path)
    {
        list ($source_image_width, $source_image_height, $source_image_type) = getimagesize($source_image_path);

        switch ($source_image_type)
        {
            case IMAGETYPE_GIF:
                $source_gd_image = imagecreatefromgif($source_image_path);
                break;
            case IMAGETYPE_JPEG:
                $source_gd_image = imagecreatefromjpeg($source_image_path);
                break;
            case IMAGETYPE_PNG:
                $source_gd_image = imagecreatefrompng($source_image_path);
                break;
            default:
                return false;
        }

        $thumbnail_gd_image = imagecreatetruecolor(128, 128);
        imagecopyresampled($thumbnail_gd_image, $source_gd_image, 0, 0, 0, 0, 128, 128, $source_image_width, $source_image_height);
        imagejpeg($thumbnail_gd_image, $thumbnail_image_path, 93);
        imagedestroy($source_gd_image);
        imagedestroy($thumbnail_gd_image);

        return true;
    }

    public static function toAmazon($localPath, $remotePath)
    {
        $cfg = \Sys::cfg('amazon');

        $s3 = new \S3($cfg['api_key'], $cfg['secret'], false, 's3.eu-central-1.amazonaws.com');
        $s3->setSignatureVersion('v4');

        try
        {
            if (!$s3->putObjectFile($localPath, $cfg['bucket'], $remotePath, \S3::ACL_PUBLIC_READ))
            {
                throw new \Exception('Was unable to copy file');
            }
        }
        catch (\Exception $e)
        {
            // may be used in future
        }
    }
}
