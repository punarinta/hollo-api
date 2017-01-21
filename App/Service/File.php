<?php

namespace App\Service;

include_once 'vendor/tpyo/amazon-s3-php-class/S3.php';

class File
{
    /**
     * Creates an attachment preview and uploads it to Amazon S3
     *
     * @param object $imapObject
     * @param $messageData
     * @param $chatId
     * @param $messageId
     * @param int $fileId
     */
    public static function createAttachmentPreview($imapObject, $messageData, $chatId, $messageId, $fileId = 0)
    {
        // https://s3.eu-central-1.amazonaws.com/cached-t/$chatId/$messageId/$fileId

        $tempDumpPath = tempnam('data/temp', 'DUMP-');
        $tempThumbPath = tempnam('data/temp', 'THUMB-');

        try
        {
            // get file data
            $binaryData = $imapObject->getFileData($messageData, $fileId);

            // dump it
            file_put_contents($tempDumpPath, $binaryData);

            File::createThumbnail($tempDumpPath, $tempThumbPath);
            File::toAmazon($tempThumbPath, "$chatId/$messageId/$fileId");
        }
        catch (\Exception $e)
        {
            // do nothing
        }
        finally
        {
            unlink($tempDumpPath);
            unlink($tempThumbPath);
        }
    }

    /**
     * Generates a thumbnail for an image (PNG, GIF, JPEG)
     *
     * @param $sourcePath
     * @param $thumbnailPath
     * @return bool
     */
    public static function createThumbnail($sourcePath, $thumbnailPath)
    {
        list ($source_image_width, $source_image_height, $source_image_type) = getimagesize($sourcePath);

        switch ($source_image_type)
        {
            case IMAGETYPE_GIF:
                $source_gd_image = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_JPEG:
                $source_gd_image = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source_gd_image = imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }

        $thumbnail_gd_image = imagecreatetruecolor(128, 128);

        // fill the background with white
        $whiteBackground = imagecolorallocate($thumbnail_gd_image, 255, 255, 255);
        imagefill($thumbnail_gd_image, 0, 0, $whiteBackground);

        imagecopyresampled($thumbnail_gd_image, $source_gd_image, 0, 0, 0, 0, 128, 128, $source_image_width, $source_image_height);
        imagejpeg($thumbnail_gd_image, $thumbnailPath, 93);
        imagedestroy($source_gd_image);
        imagedestroy($thumbnail_gd_image);

        return true;
    }

    /**
     * Uploads a file to Amazon S3
     *
     * @param $localPath
     * @param $remotePath
     */
    public static function toAmazon($localPath, $remotePath)
    {
        //  IMPORTANT: S3 class upgraded with https://github.com/tpyo/amazon-s3-php-class/issues/96

        // https://s3.eu-central-1.amazonaws.com/cached-t/$remotePath

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
