<?php

/*  pubcontrolclient.php
    ~~~~~~~~~
    This module implements the PubControlClient, ThreadSafeClient,
    and ThreadSafeArray classes. Note that only the PubControlClient
    class should be used publicly.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

require 'format.php';
require 'item.php';
require 'vendor/autoload.php';

class PubControlClient
{
    private $req_queue = null;
    private $client = null;

    public function __construct($uri)
    {
        $this->uri = $uri;
        $this->req_queue = new ThreadSafeArray();
        $this->client = new ThreadSafeClient($this->uri, $this->req_queue);
    }

    public function __destruct()
    {
        if (!is_null($this->client))
            Mutex::destroy($this->client->mutex);
        if (!is_null($this->client) && !is_null($this->client->thread_mutex))
            Mutex::destroy($this->client->thread_mutex);
        if (!is_null($this->client) && !is_null($this->client->thread_cond))
            Cond::destroy($this->client->thread_cond);
    }

    function set_auth_basic($username, $password)
    {
        $this->client->set_auth_basic($username, $password);
    }

    public function set_auth_jwt($claim, $key)
    {   
        $this->client->set_auth_jwt($claim, $key);
    }

    public function publish($channel, $item)
    {
        $this->client->publish($channel, $item);
    }

    public function publish_async($channel, $item, $callback=null)
    {
        $this->client->publish_async($channel, $item, $callback);
    }

    public function finish()
    {
        $this->client->finish();
    }
}

// NOTE: The ThreadSafeClient class cannot be used directly.
// Use the PubControlClient class instead.
class ThreadSafeClient extends Thread
{
    public $uri = null;
    public $mutex = null;
    public $is_thread_running = false;
    public $thread_cond = null;
    public $thread_mutex = null;
    public $req_queue = null;
    public $auth_basic_user = null;
    public $auth_basic_pass = null;
    public $auth_jwt_claim = null;
    public $auth_jwt_key = null;

    public function __construct($uri, $req_queue)
    {
        $this->req_queue = $req_queue;
        $this->uri = $uri;
        $this->mutex = Mutex::create();
    }
    
    function set_auth_basic($username, $password)
    {
        Mutex::lock($this->mutex);
        $this->auth_basic_user = $username;
        $this->auth_basic_pass = $password;
        Mutex::unlock($this->mutex);
    }

    public function set_auth_jwt($claim, $key)
    {
        Mutex::lock($this->mutex);
        $this->auth_jwt_claim = $claim;
        $this->auth_jwt_key = $key;
        Mutex::unlock($this->mutex);
    }

    public function publish($channel, $item)
    {
        $export = $item->export();
        $export['channel'] = $channel;
        $uri = null;
        $auth = null;
        Mutex::lock($this->mutex);
        $uri = $this->uri;
        $auth = $this->gen_auth_header();
        Mutex::unlock($this->mutex);
        self::pubcall($uri, $auth, array($export));
    }

    public function publish_async($channel, $item, $callback=null)
    {
        $export = $item->export();
        $export['channel'] = $channel;
        $uri = null;
        $auth = null;
        Mutex::lock($this->mutex);
        $uri = $this->uri;
        $auth = $this->gen_auth_header();
        $this->ensure_thread();
        Mutex::unlock($this->mutex);
        $this->queue_req(array('pub', $uri, $auth, $export, $callback));
    }

    public function finish()
    {
        Mutex::lock($this->mutex);
        if ($this->is_thread_running)
        {
            $this->queue_req(array('stop'));
            $this->join();
            $this->is_thread_running = false;
        }
        Mutex::unlock($this->mutex);
   }

    public function run()
    {
        $quit = false;
        while (!$quit)
        {
            Mutex::lock($this->thread_mutex);
            if (count($this->req_queue) == 0)
            {
                Cond::wait($this->thread_cond, $this->thread_mutex);
                if (count($this->req_queue) == 0)
                {
                    Mutex::unlock($this->thread_mutex);
                    continue;
                }
            }
            $reqs = array();
            while (count($this->req_queue) > 0 and count($reqs) < 10)
            {
                $m = $this->req_queue->shift();
                if ($m[0] == 'stop')
                {
                    $quit = true;
                    break;
                }
                $reqs[] = array($m[1], $m[2], $m[3], $m[4]);
            }
            Mutex::unlock($this->thread_mutex);
            if (count($reqs) > 0)
                self::pubbatch($reqs);
        }
    }

    public function gen_auth_header()
    {
        if (!is_null($this->auth_basic_user))
            return 'Basic ' . base64_encode(
                    "{$this->auth_basic_user}:{$this->auth_basic_pass}");
        elseif (!is_null($this->auth_jwt_claim))
        {
            $claim = $this->auth_jwt_claim;
            if (!array_key_exists('exp', $claim))
                $claim['exp'] = time() + 3600;
            return 'Bearer ' . JWT::encode($claim, $this->auth_jwt_key); 
        }
        else 
            return null;
    }

    public function ensure_thread()
    {
        if (!$this->is_thread_running)
        {
            $this->is_thread_running = true;
            $this->thread_cond = Cond::create();
            $this->thread_mutex = Mutex::create();
            $this->start();
        }
    }
    
    public function queue_req($req)
    {
        Mutex::lock($this->thread_mutex);
        $this->req_queue[] = $req;
        Cond::signal($this->thread_cond);    
        Mutex::unlock($this->thread_mutex);
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

    public static function pubbatch($reqs)
    {
        if (count($reqs) == 0)
            throw new RuntimeException('reqs length == 0');
        $uri = $reqs[0][0];
        $auth_header = $reqs[0][1];
        $items = array();
        $callbacks = array();
        foreach ($reqs as $req)
        {
            $items[] = $req[2];
            $callbacks[] = $req[3];
        }
        $result = null;
        try
        {
            self::pubcall($uri, $auth_header, $items);
            $result = array(true, '');
        }
        catch (RuntimeException $exception)
        {
            $result = array(false, $exception->getMessage());
        }
        foreach ($callbacks as $callback)
            if (!is_null($callback))
            {
                $callback($result[0], $result[1]);
            }
    }
}

class ThreadSafeArray extends Stackable
{
    public function run()
    {
    }
}
?>
