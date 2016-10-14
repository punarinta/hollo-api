<?php

namespace App\Model;

/**
 * Class Plancake
 * @package App\Model
 */
class Plancake
{
    const PLAINTEXT = 1;
    const HTML = 2;

    /**
     * @var string
     */
    private $emailRawContent;

    /**
     * @var array
     */
    protected $rawFields;

    /**
     * @var array of string (each element is a line)
     */
    protected $rawBodyLines;

    /**
     * @param string $emailRawContent
     */
    public function  __construct($emailRawContent)
    {
        $this->emailRawContent = $emailRawContent;
        $this->extractHeadersAndRawBody();
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->rawFields;
    }

    private function extractHeadersAndRawBody()
    {
        $i = 0;
        $currentHeader = '';
        $lines = preg_split("/(\r?\n|\r)/", $this->emailRawContent);

        foreach ($lines as $line)
        {
            if (self::isNewLine($line))
            {
                // end of headers
                $this->rawBodyLines = array_slice($lines, $i);

                $this->rawBodyLines = implode("\n", $this->rawBodyLines);
            //    $temp = quoted_printable_decode($temp);
            //    $this->rawBodyLines = strtr($temp, ["=\n" => '']);
                break;
            }

            if ($this->isLineStartingWithPrintableChar($line)) // start of new header
            {
                preg_match('/([^:]+): ?(.*)$/', $line, $matches);
                $newHeader = strtolower($matches[1]);
                $value = $matches[2];

                if (!isset ($this->rawFields[$newHeader]))
                {
                    $this->rawFields[$newHeader] = [];
                }

                $this->rawFields[$newHeader][] = $value;
                $currentHeader = $newHeader;
            }
            else
            {
                // more lines related to the current header
                if ($currentHeader)
                {
                    // use the last one, in case there are several headers with the same name
                    $this->rawFields[$currentHeader][count($this->rawFields[$currentHeader]) - 1] .= substr($line, 1);
                }
            }
            ++$i;
        }
    }

    /**
     * @return string (in UTF-8 format)
     * @throws \Exception if a subject header is not found
     */
    public function getSubject()
    {
        if (!isset ($this->rawFields['subject'][0]))
        {
            throw new \Exception("Couldn't find the subject of the email");
        }

        $ret = '';

        foreach (imap_mime_header_decode($this->rawFields['subject'][0]) as $h)
        {
            // subject can span into several lines
            $charset = ($h->charset == 'default') ? 'US-ASCII' : $h->charset;
            $ret .= iconv($charset, "UTF-8//TRANSLIT", $h->text);
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getBodies()
    {
        $bodies = [];

        if (!$topContentType = $this->getHeader('Content-Type'))
        {
            return [];
        }

        preg_match('!boundary=(.*)$!i', $topContentType[0], $topBoundary);
        $topBoundary = $topBoundary[1];

        foreach (explode('--' . $topBoundary, $this->rawBodyLines) as $topPart)
        {
            if (!trim($topPart))
            {
                continue;
            }

            if (preg_match('/^Content-Type: ?(.*)$/mi', $topPart, $matches))
            {
                $contentType = explode(';', $matches[1]);

                if (trim($contentType[0]) == 'multipart/alternative')
                {
                    // $boundary = trim(strtr($contentType[1], ['boundary=' => '']));

                    print_r($this->rawBodyLines);
                    break;

                /*    foreach (explode('--' . $boundary, $topPart) as $part)
                    {
                        print_r($part);
                    }*/
                }
            }
        }

        return $bodies;
    }

    /**
     * @return array
     */
    public function getAttachments()
    {
        return [];
    }

    /**
     * @param string $headerName - the header we want to retrieve
     * @return string - the value of the header
     */
    public function getHeader($headerName)
    {
        $headerName = strtolower($headerName);

        if (isset ($this->rawFields[$headerName]))
        {
            return $this->rawFields[$headerName];
        }

        return '';
    }

    /**
     * @param string $line
     * @return boolean
     */
    public static function isNewLine($line)
    {
        $line = str_replace("\r", '', $line);
        $line = str_replace("\n", '', $line);

        return (strlen($line) === 0);
    }

    /**
     * @param string $line
     * @return boolean
     */
    private function isLineStartingWithPrintableChar($line)
    {
        return preg_match('/^[A-Za-z]/', $line);
    }
}
