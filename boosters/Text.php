<?php

class Text
{
    /**
     * Rename from integer to greek alphabet string
     *
     * @param int $sort_order
     * @return string $name
     **/
    static public function translateNumberToGreek($sort_order)
    {
        $letters = array
        (
            0,
            'alpha',    'beta',     'gamma',    'delta',
            'epsilon',  'zeta',     'eta',      'theta',
            'iota',     'kappa',    'lambda',   'mu',
            'nu',       'xi',       'omicron',  'pi',
            'rho',      'sigma',    'tau',      'upsilon',
            'phi',      'chi',      'psi',      'omega',
        );

        return isset ($letters[$sort_order]) ? $letters[$sort_order] : $sort_order;
    }

    /**
     * Generates a random password
     *
     * @param int $length
     * @param bool $specialSymbols
     * @return string
     */
    static public function generatePassword($length = 8, $specialSymbols = true)
    {
        $pass = '';
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' . ($specialSymbols ? '.,!#%=?+-' : '');
        for ($i = 0; $i < $length; $i++)
        {
            $n = rand(0, strlen($alphabet) - 1);
            $pass .= $alphabet[$n];
        }
        return $pass;
    }

    /**
     * Makes all the URLs in the text clickable
     *
     * @param $string
     * @return mixed
     */
    static public function clickabilizeUrls($string)
    {
        $rexProtocol = '(https?://)';
        $rexDomain   = '((?:[-a-zA-Z0-9]{1,63}\.)+[-a-zA-Z0-9]{2,63}|(?:[0-9]{1,3}\.){3}[0-9]{1,3})';
        $rexPort     = '(:[0-9]{1,5})?';
        $rexPath     = '(/[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]*?)?';
        $rexQuery    = '(\?[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?';
        $rexFragment = '(#[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?';

        return preg_replace_callback("&\\b$rexProtocol$rexDomain$rexPort$rexPath$rexQuery$rexFragment(?=[?.!,;:\"]?(\s|$))&", function($match)
        {
            $completeUrl = $match[1] ? $match[0] : "http://{$match[0]}";
            return '<a rel="nofollow" target="_blank" href="' . $completeUrl . '">' . $match[1] . $match[2] . $match[3] . $match[4] . '</a>';
        }, htmlspecialchars($string));
    }

    /**
     * Lorem Ipsum demo text generator
     *
     * @param bool $amount
     * @param bool $single
     * @return string
     */
    static public function lipsum($amount = false, $single = false)
    {
        if ($amount === false)
        {
            $amount = mt_rand(1, 100);
        }

        //hipster ipsum
        $awesomeText = "Normcore Etsy artisan, gastropub vegan Austin pork belly. Stumptown mixtape Blue Bottle, semiotics organic raw denim Tumblr. Retro squid keffiyeh Austin wayfarers irony. Tousled fanny pack cred occupy, Wes Anderson Pitchfork viral Pinterest selvage 90's. Flannel iPhone cliche post-ironic, ethical gluten-free organic try-hard wayfarers. Vegan keffiyeh gentrify Pitchfork letterpress. Gluten-free kogi church-key, stumptown cliche chia McSweeney's polaroid Schlitz ennui fanny pack messenger bag trust fund High Life Williamsburg.Pickled drinking vinegar slow-carb 3 wolf moon VHS. Cliche polaroid mixtape, DIY bicycle rights Truffaut meh mumblecore vegan direct trade lo-fi artisan fashion axe American Apparel photo booth. McSweeney's paleo sartorial asymmetrical Echo Park. Beard XOXO blog, narwhal church-key authentic brunch pug. Leggings church-key Banksy, authentic sustainable twee kitsch raw denim fanny packBushwick forage put a bird on it Pinterest. Leggings 3 wolf moon plaid polaroid. Skateboard Bushwick McSweeney's, try-hard occupy kitsch iPhone craft beer direct trade Tumblr.";

        $array = explode(' ', $awesomeText);
        shuffle($array);

        $newstring = implode($array, $single ? '' : ' ');

        return substr($newstring, 0, $amount);
    }

    /**
     * Text truncation
     *
     * @param bool $string
     * @param int $maxLength
     * @param string $encoding
     * @return bool|string
     */
    static public function trunc($string, $maxLength = 25, $encoding = 'utf-8')
    {
        $len = mb_strlen($string, $encoding);
        if ($len > $maxLength)
        {
            // store the parts and just cut them by 3
            $p1 = mb_substr($string, 0, $len / 3, $encoding);
            $p2 = mb_substr($string, $len - $len / 3, $len, $encoding);

            // is the new parts longer then max?
            if (mb_strlen($p1, $encoding) + mb_strlen($p2, $encoding) + 3 > $maxLength)
            {
                // cut them down further!
                return mb_substr($p1, 0, $maxLength / 2 - 1, $encoding) . '...' . mb_substr($p2, -($maxLength / 2 + 2), null, $encoding);
            }
            else
            {
                return $p1 . '...' . $p2;
            }
        }
        else
        {
            return $string;
        }
    }

    /**
     * Limits a string, ending with ellipsis, if necessary
     *
     * @param $string
     * @param int $maxLength
     * @param string $encoding
     * @return string
     */
    static public function ellipsis($string, $maxLength = 50, $encoding = 'utf-8')
    {
        return mb_strlen($string, $encoding) > $maxLength ? mb_substr($string, 0, $maxLength, $encoding) . '...' : $string;
    }

    /**
     * Generate GUID for slugs
     *
     * @return string
     */
    static public function GUID_v4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Slugify a string.
     *
     * @param $string
     * @return mixed|string
     * @throws \Exception
     */
    static public function slugify($string)
    {
        // lower case + cleanup + translit Latin
        $string = strtr(strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $string)), array("'" => ''));

        $string = preg_replace('([^a-zA-Z0-9_-]+)', '-', $string);
        $string = preg_replace('(-{2,})', '-', $string);
        $string = trim($string, '-');

        if (!strlen($string))
        {
            $string = self::generatePassword(8, false);
        }

        return $string;
    }
}