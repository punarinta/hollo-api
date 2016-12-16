<?php

namespace App\Model;

/**
 * https://developer.yahoo.com/oauth2/guide/
 * https://developer.yahoo.com/forums
 *
 * Class YahooOAuth2
 * @package App\Model
 */
class YahooOAuth2
{
    const TOKEN_ENDPOINT = 'https://api.login.yahoo.com/oauth2/get_token';
    const AUTHORIZATION_ENDPOINT = 'https://api.login.yahoo.com/oauth2/request_auth';

    /**
     * @param $redirectUri
     * @param string $language
     * @return string
     */
    public function getAuthorizationURL($redirectUri, $language = 'en-us')
    {
        $cfg = \Sys::cfg('oauth.yahoo');

        return self::AUTHORIZATION_ENDPOINT . '?client_id=' . $cfg['clientId'] . '&redirect_uri=' . $redirectUri . '&language=' . $language . '&response_type=code';
    }

    /**
     * @param $redirectUri
     * @param $code
     * @return mixed
     */
    public function getAccessToken($redirectUri, $code)
    {
        $cfg = \Sys::cfg('oauth.yahoo');

        $data = array
        (
            'redirect_uri'  => $redirectUri,
            'code'          => $code, 
            'grant_type'    => 'authorization_code'
        );

        $response = self::fetch(self::TOKEN_ENDPOINT, $data, $cfg['clientId'] . ':' . $cfg['secret']);

        $jsonResponse = json_decode($response);
        
        return $jsonResponse->access_token;
    }

    /**
     * @param $url
     * @param string $data
     * @param string $auth
     * @param string $headers
     * @return mixed
     * @throws \Exception
     */
    public function fetch($url, $data = '', $auth = '', $headers = '')
    {
        $curl = curl_init($url);
        if ($data)
        {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        else
        {
            curl_setopt($curl, CURLOPT_POST, false);
        }
        if ($auth)
        {
            curl_setopt($curl, CURLOPT_USERPWD, $auth);
        }
        if ($headers)
        {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if (empty ($response))
        {
            throw new \Exception(curl_error($curl));
        }
        else
        {
            $info = curl_getinfo($curl);
            curl_close($curl);
            if ($info['http_code'] != 200 && $info['http_code'] != 201)
            {
                throw new \Exception($info['http_code'] . ' : ' . $response);
            }
        }
        
        return $response;
    }
}
