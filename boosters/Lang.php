<?php

class Lang
{
    /**
     * Sets the current locale.
     *
     * @param $locale
     */
    static function setLocale($locale)
    {
        $locale .= '.utf8';
        $GLOBALS['-LC'] = $locale;
        putenv("LC_ALL=$locale");
        setlocale(LC_ALL, $locale);

        // TODO: activate after translation is added

    /*    bindtextdomain('Mailless', './App/Translation');
        textdomain('Mailless');*/
    }

    /**
     * Translates a singular form
     *
     * @param $key
     * @return mixed
     */
    static function translate($key)
    {
        return gettext($key);
    }

    /**
     * Translates with a support of plurals
     *
     * @param $a
     * @param $b
     * @param int $x
     * @return mixed
     */
    static function translatePlural($a, $b, $x = 1)
    {
        return ngettext($a, $b, $x);
    }
}