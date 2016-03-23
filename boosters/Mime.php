<?php

/**
 * Class Mime
 *
 * Small helping class for distinguishing various file types
 *
 * @package CioBase\Helper
 */
class Mime
{
    const FLAG_IMAGE    = 0x001;
    const FLAG_VIDEO    = 0x010;
    const FLAG_AUDIO    = 0x100;

    const FLAG_ZENCODER = 0x110;

    /**
     * @param $mime
     * @return bool
     */
    static function isImage($mime)
    {
        switch ($mime)
        {
            case 'image/jpeg':
            case 'image/png':
            case 'image/gif':
                return true;
                break;

            default:
                $type = explode('/', $mime);
                return (count($type)>1 && $type[0] == 'image');
        }
    }

    /**
     * @param $mime
     * @return bool
     */
    static function isVideo($mime)
    {
        switch ($mime)
        {
            case 'video/quicktime':
            case 'video/x-quicktime':
            case 'image/mov':
            case 'video/avi':
            case 'video/mp4':
            case 'video/x-flv':
                return true;
                break;

            default:
                $type = explode('/', $mime);
                return (count($type)>1 && $type[0] == 'video');
        }
    }

    /**
     * @param $mime
     * @return bool
     */
    static function isAudio($mime)
    {
        switch ($mime)
        {
            case 'audio/aiff':
            case 'audio/x-midi':
            case 'audio/x-wav':
            case 'audio/ogg':
            case 'audio/mp3':
                return true;
                break;

            default:
                $type = explode('/', $mime);
                return (count($type)>1 && $type[0] == 'audio');
        }
    }

    /**
     * @param $mime
     * @param $flag
     * @return int
     */
    static function isType($mime, $flag)
    {
        $result = 0;

        if ($flag & self::FLAG_IMAGE) $result = $result | self::isImage($mime);
        if ($flag & self::FLAG_VIDEO) $result = $result | self::isVideo($mime);
        if ($flag & self::FLAG_AUDIO) $result = $result | self::isAudio($mime);

        return $result;
    }

    /**
     * Detects MIME type by extension
     *
     * @param $extension
     * @return int|null|string
     */
    static function getTypeByExt($extension)
    {
        foreach (\Sys::cfg('mime') as $k => $v)
        {
            if ($extension == $v) return $k;
        }

        return null;
    }
}