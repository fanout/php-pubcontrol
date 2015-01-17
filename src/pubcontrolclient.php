<?php

/*  pubcontrolclient.php
    ~~~~~~~~~
    This module implements the PubControlClient class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

require 'format.php';
require 'item.php';

if (!class_exists('JWT'))
    require 'vendor/autoload.php';

if (class_exists('Thread'))
    include 'threadsafeclient.php';

class PubControlClient
{
    private $req_queue = null;
    private $tsclient = null;
    private $auth_basic_user = null;
    private $auth_basic_pass = null;
    private $auth_jwt_claim = null;
    private $auth_jwt_key = null;

    public function __construct($uri)
    {
        $this->uri = $uri;        
        if ($this->is_async_supported())
        {
            $this->req_queue = new ThreadSafeArray();
            $this->tsclient = new ThreadSafeClient($this->uri,
                    $this->req_queue);
        }
    }

    public function __destruct()
    {
        if (!is_null($this->tsclient))
        {
            Mutex::destroy($this->tsclient->mutex);
            if (!is_null($this->tsclient->thread_mutex))
                Mutex::destroy($this->tsclient->thread_mutex);
            if (!is_null($this->tsclient->thread_cond))
                Cond::destroy($this->tsclient->thread_cond);
        }
    }

    function set_auth_basic($username, $password)
    {
        $this->auth_basic_user = $username;
        $this->auth_basic_pass = $password;
        if ($this->tsclient != null)
            $this->tsclient->set_auth_basic($username, $password);
    }

    public function set_auth_jwt($claim, $key)
    {
        $this->auth_jwt_claim = $claim;
        $this->auth_jwt_key = $key;
        if ($this->tsclient != null)
            $this->tsclient->set_auth_jwt($claim, $key);
    }

    public function publish($channel, $item)
    {
        $export = $item->export();
        $export['channel'] = $channel;
        $uri = null;
        $auth = null;
        $uri = $this->uri;
        $auth = self::gen_auth_header($this->auth_jwt_claim,
                $this->auth_jwt_key, $this->auth_basic_user,
                $this->auth_basic_pass);
        self::pubcall($uri, $auth, array($export));
    }

    public function publish_async($channel, $item, $callback=null)
    {
        if (!$this->is_async_supported())
            throw new RuntimeException('Asynchronous publishing not supported. '
                    . 'Recompile PHP with --enable-maintainer-zts to ' 
                    . 'turn pthreads on.');
        $this->tsclient->publish_async($channel, $item, $callback);
    }

    public function finish()
    {
        if ($this->tsclient != null)
            $this->tsclient->finish();
    }

    public function is_async_supported()
    {
        if (class_exists('Thread'))
            return true;
        return false;
    }

    public static function pubcall($uri, $auth_header, $items)
    {
        $uri .= '/publish/';
        $content = array();
        $content['items'] = $items;
        $headers = array('Content-Type: application/json');
        if (!is_null($auth_header))
            $headers[] = 'Authorization: ' . $auth_header;
        $post = curl_init($uri);
        curl_setopt_array($post, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($content)
        ));
        $response = curl_exec($post);
        if (curl_error($post) != '')
            throw new RuntimeException('Failed to publish: ' . 
                    curl_error($post));
        $http_code = intval(curl_getinfo($post, CURLINFO_HTTP_CODE));
        if ($http_code < 200 || $http_code >= 300)
            throw new RuntimeException('Failed to publish: ' . $response);
    }

    public static function gen_auth_header($auth_jwt_claim, $auth_jwt_key,
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
            return 'Bearer ' . JWT::encode($claim, $auth_jwt_key); 
        }
        else 
            return null;
    }
}
?>
