<?php

namespace App\Model;

/**
 * Class EmailParser
 * @package App\Model
 */
class EmailParser
{
    protected $emailRawContent;
    protected $rawFields;
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
     * @return string
     * @throws \Exception
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

        if (!preg_match('!boundary=(.*)$!i', $topContentType[0], $topBoundary))
        {
            // this is a simple mail

            $charset = 'UTF-8';
            $contentTypeData = explode(';', $topContentType[0]);
            $contentEncoding = $this->getHeader('Content-Transfer-Encoding');
            if (isset ($contentTypeData[1]))
            {
                $charset = trim(str_replace('charset=', '', $contentTypeData[1]));
            }

            $content = $this->rawBodyLines;

            if ($contentEncoding[0] == 'quoted-printable')
            {
                $content = quoted_printable_decode($content);
                $content = strtr($content, ["=\n" => '']);
            }
            if ($contentEncoding[0] == 'base64')
            {
                $content = base64_decode(strtr($content, ['-' => '+', '_' => '/']));
            }

            // TODO: do something with charset

            $bodies[] = array
            (
                'type'      => $contentTypeData[0],
                'content'   => $content,
            );
        }
        else
        {
            $topBoundary = $topBoundary[1];

            foreach (explode('--' . $topBoundary, $this->rawBodyLines) as $topPart)
            {
                if (!trim($topPart))
                {
                    continue;
                }

                $topPartData = $this->parsePart($topPart);

                if ($topPartData['headers']['content-type'][0] == 'multipart/alternative')
                {
                    foreach (explode('--' . $topPartData['headers']['content-type']['boundary'], $topPartData['content']) as $part)
                    {
                        if (!$partData = $this->parsePart($part))
                        {
                            continue;
                        }

                        $bodies[] = array
                        (
                            'type'      => $partData['headers']['content-type'][0],
                            'content'   => $partData['content'],
                        );
                    }
                }
            }
        }

        return $bodies;
    }

    /**
     * @param $text
     * @return array|null
     */
    public function parsePart($text)
    {
        $text = trim($text);
        if (!strlen($text) || $text == '--')
        {
            return null;
        }

        $i = 0;
        $payload = [];
        $headers = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line)
        {
            $lineData = explode(':', $line);
            $key = strtolower($lineData[0]);
            $pairs = [];

            if (count($lineData) > 1)
            {
                foreach (explode(';', $lineData[1]) as $pair)
                {
                    $pair = explode('=', $pair);
                    if (count($pair) > 1)
                    {
                        $pairs[trim($pair[0])] = trim($pair[1]);
                    }
                    else
                    {
                        $pairs[] = trim($pair[0]);
                    }
                }

                $headers[$key] = $pairs;
            }

            if ($i && self::isNewLine($line))
            {
                $payload = array_slice($lines, $i);
                break;
            }
            ++$i;
        }

        return array
        (
            'headers'   => $headers,
            'content'   => trim(implode("\n", $payload)),
        );
    }

    /**
     * @return array
     */
    public function getAttachments()
    {
        $attachments = [];

        if (!$topContentType = $this->getHeader('Content-Type'))
        {
            return [];
        }

        if (preg_match('!boundary=(.*)$!i', $topContentType[0], $topBoundary))
        {
            $topBoundary = $topBoundary[1];

            foreach (explode('--' . $topBoundary, $this->rawBodyLines) as $topPart)
            {
                if (!trim($topPart))
                {
                    continue;
                }

                $topPartData = $this->parsePart($topPart);

                if ($topPartData['headers']['content-type'][0] != 'multipart/alternative' && $topPartData)
                {
                    $content = $topPartData['content'];

                    if ($topPartData['headers']['content-transfer-encoding'][0] == 'quoted-printable')
                    {
                        $content = quoted_printable_decode($content);
                        $content = strtr($content, ["=\n" => '']);
                    }
                    if ($topPartData['headers']['content-transfer-encoding'][0] == 'base64')
                    {
                        $content = base64_decode(strtr($content, ['-' => '+', '_' => '/']));
                    }

                    $attachments[] = array
                    (
                        'size'      => strlen($content),
                        'type'      => $topPartData['headers']['content-type'][0],
                        'name'      => strtr($topPartData['headers']['content-type']['name'], ['"' => '', "'" => '']),
                        'content'   => $content,
                    );
                }
            }
        }

        return $attachments;
    }

    /**
     * @param $headerName
     * @return string
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
    protected function isLineStartingWithPrintableChar($line)
    {
        return preg_match('/^[A-Za-z]/', $line);
    }
}
