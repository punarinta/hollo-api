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
        list ($widthSrc, $heightSrc, $source_image_type) = getimagesize($sourcePath);

        switch ($source_image_type)
        {
            case IMAGETYPE_GIF:
                $imSrc = imagecreatefromgif($sourcePath);
                break;

            case IMAGETYPE_JPEG:
                $imSrc = imagecreatefromjpeg($sourcePath);
                break;

            case IMAGETYPE_PNG:
                $imSrc = imagecreatefrompng($sourcePath);
                break;

            default:
                return false;
        }

        $srcX = 0;
        $srcY = 0;

        if ($widthSrc > $heightSrc)
        {
            $srcX = ($widthSrc - $heightSrc) / 2;
            $srcY = 0;
        }
        if ($widthSrc < $heightSrc)
        {
            $srcX = 0;
            $srcY = ($heightSrc - $widthSrc) / 2;
        }

        $imThumb = imagecreatetruecolor(128, 128);

        // fill the background with white
        $white = imagecolorallocate($imThumb, 255, 255, 255);
        imagefill($imThumb, 0, 0, $white);

        imagecopyresampled($imThumb, $imSrc, 0, 0, $srcX, $srcY, 128, 128, $widthSrc - 2 * $srcX, $heightSrc - 2 * $srcY);
        imagejpeg($imThumb, $thumbnailPath, 93);
        imagedestroy($imSrc);
        imagedestroy($imThumb);

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
