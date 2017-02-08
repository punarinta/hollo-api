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
     * @param string $mime
     * @return bool
     * @throws \Exception
     */
    public static function createAttachmentPreview($imapObject, $messageData, $chatId, $messageId, $fileId = 0, $mime = '')
    {
        if (!$imapObject)
        {
            return false;
        }

        // https://s3.eu-central-1.amazonaws.com/cached-t/$chatId/$messageId/$fileId

        $types =
        [
            // allowed types
            'image/png'         => 'png',
            'image/gif'         => 'gif',
            'image/jpg'         => 'jpg',
            'image/jpeg'        => 'jpg',
            'application/pdf'   => 'pdf',
            'image/svg+xml'     => 'svg',
            'text/html'         => 'html',
            'image/tiff'        => 'tiff',
            'image/bmp'         => 'bmp',
        ];

        if (!isset ($types[$mime]))
        {
            return false;
        }

        $tempDumpPath = tempnam('data/temp', 'DUMP-');
        $tempThumbPath = tempnam('data/temp', 'THUMB-');

        try
        {
            // get file data
            $binaryData = $imapObject->getFileData($messageData, $fileId);

            // dump it
            file_put_contents($tempDumpPath . '.' . $types[$mime], $binaryData);

            if (!in_array($mime, ['image/png', 'image/gif', 'image/jpeg']))
            {
                $tempFileName = tempnam('data/temp', 'TEMP-');
                $im = new \imagick($tempDumpPath . '.' . $types[$mime] . '[0]');
                $im->setImageFormat('png');
                $im->trimImage(0);
                file_put_contents($tempFileName, $im);
                File::createGdThumbnail($tempFileName, $tempThumbPath);
                unlink($tempFileName);
            }
            else
            {
                File::createGdThumbnail($tempDumpPath . '.' . $types[$mime], $tempThumbPath);
            }

            File::toAmazon($tempThumbPath, "$chatId/$messageId/$fileId");
        }
        catch (\Exception $e)
        {
            throw new \Exception('Thumbnail creation error: ' . $e->getMessage());
        }
        finally
        {
            unlink($tempDumpPath);
            unlink($tempDumpPath . '.' . $types[$mime]);
            unlink($tempThumbPath);
        }

        return true;
    }

    /**
     * Generates a thumbnail for an image (PNG, GIF, JPEG)
     *
     * @param $sourcePath
     * @param $thumbnailPath
     * @return bool
     */
    public static function createGdThumbnail($sourcePath, $thumbnailPath)
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
        //  IMPORTANT: S3 class upgraded with https://raw.githubusercontent.com/tpyo/amazon-s3-php-class/eca1de29149b67336a302c51da6a36740207a093/S3.php

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
