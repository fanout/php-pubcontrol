<?php

/*  pccutilities.php
    ~~~~~~~~~
    This module implements the PccUtilities class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

namespace PubControl;

// The PccUtilities class is an internal class that provides helper methods
// used by both the PubControlClient and ThreadSafeClient classes.
class PccUtilities
{
    // An internal method for preparing the HTTP POST request for publishing
    // data to the endpoint. This method accepts the URI endpoint, authorization
    // header, and a list of items to publish.
    public function pubcall($uri, $auth_header, $items)
    {
        $uri .= '/publish/';
        $content = array();
        $content['items'] = $items;
        $headers = array('Content-Type: application/json');
        if (!is_null($auth_header))
            $headers[] = 'Authorization: ' . $auth_header;
        $results = $this->make_http_request($uri, $headers, $content);
        $this->verify_http_status_code($results[0], $results[1]);
    }

    // An internal method for making an HTTP request to publish a
    // message with the specified URI, headers, and content.
    public function make_http_request($uri, $headers, $content)
    {
        $post = curl_init($uri);
        curl_setopt_array($post, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($content)
        ));
        $response = curl_exec($post);
        if (curl_error($post) != '')
            throw new \RuntimeException('Failed to publish: ' . 
                    curl_error($post));
        return array($response, 
                intval(curl_getinfo($post, CURLINFO_HTTP_CODE)));
    }

    // An internal method used for verifying an HTTP status code where
    // an exception code is thrown if the code is not successful.
    public function verify_http_status_code($response, $http_code)
    {
        if ($http_code < 200 || $http_code >= 300)
            throw new \RuntimeException('Failed to publish: ' . $response);
    }

    // An internal method used to generate an authorization header. The
    // authorization header is generated based on whether basic or JWT
    // authorization information was provided via the publicly accessible
    // 'set_*_auth' methods defined above.
    public function gen_auth_header($auth_jwt_claim, $auth_jwt_key,
            $auth_basic_user, $auth_basic_pass)
    {
        if (!is_null($auth_basic_user))
            return 'Basic ' . base64_encode(
                    "{$auth_basic_user}:{$auth_basic_pass}");
        elseif (!is_null($auth_jwt_claim))
        {
            $claim = $auth_jwt_claim;
            if (!array_key_exists('exp', $claim))
                $claim['exp'] = time() + 3600;
            return 'Bearer ' . \Firebase\JWT\JWT::encode($claim, $auth_jwt_key);
        }
        else 
            return null;
    }
}
?>
