<?php

/*  pubcontrolclient.php
    ~~~~~~~~~
    This module implements the PubControlClient class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

namespace PubControl;

if (class_exists('Thread'))  
    include dirname(__FILE__) . '/threadsafeclient.php';

include dirname(__FILE__) . '/pccutilities.php';

// The PubControlClient class allows consumers to publish either synchronously 
// or asynchronously to an endpoint of their choice. The consumer wraps a Format
// class instance in an Item class instance and passes that to the publish
// methods. The async publish method has an optional callback parameter that
// is called after the publishing is complete to notify the consumer of the
// result.
class PubControlClient
{
    public $uri = null;
    public $req_queue = null;
    public $tsclient = null;
    public $auth_basic_user = null;
    public $auth_basic_pass = null;
    public $auth_jwt_claim = null;
    public $auth_jwt_key = null;
    public $pcc_utilities = null;

    // Initialize this class with a URL representing the publishing endpoint.
    // If async publishing is supported (i.e., pthread is installed) then
    // instantiate an instance of the ThreadSafeClient.
    public function __construct($uri)
    {
        $this->uri = $uri;
        $this->pcc_utilities = new PccUtilities();    
        if ($this->is_async_supported())
        {
            $this->req_queue = new ThreadSafeArray();
            $this->tsclient = new ThreadSafeClient($this->uri,
                    $this->req_queue);
        }
    }

    // Destroy this instance by cleaning up all thread related objects.
    public function __destruct()
    {
        if (!is_null($this->tsclient))
        {
            \Mutex::destroy($this->tsclient->mutex);
            if (!is_null($this->tsclient->thread_mutex))
                \Mutex::destroy($this->tsclient->thread_mutex);
            if (!is_null($this->tsclient->thread_cond))
                \Cond::destroy($this->tsclient->thread_cond);
        }
    }

    // Call this method and pass a username and password to use basic
    // authentication with the configured endpoint.
    function set_auth_basic($username, $password)
    {
        $this->auth_basic_user = $username;
        $this->auth_basic_pass = $password;
        if ($this->tsclient != null)
            $this->tsclient->set_auth_basic($username, $password);
    }

    // Call this method and pass a claim and key to use JWT authentication
    // with the configured endpoint.
    public function set_auth_jwt($claim, $key)
    {
        $this->auth_jwt_claim = $claim;
        $this->auth_jwt_key = $key;
        if ($this->tsclient != null)
            $this->tsclient->set_auth_jwt($claim, $key);
    }

    // The synchronous publish method for publishing the specified item to the
    // specified channel on the configured endpoint.
    public function publish($channel, $item)
    {
        $export = $item->export();
        $export['channel'] = $channel;
        $uri = null;
        $auth = null;
        $uri = $this->uri;
        $auth = $this->pcc_utilities->gen_auth_header($this->auth_jwt_claim,
                $this->auth_jwt_key, $this->auth_basic_user,
                $this->auth_basic_pass);
        $this->pcc_utilities->pubcall($uri, $auth, array($export));
    }

    // The asynchronous publish method for publishing the specified item to the
    // specified channel on the configured endpoint. The callback method is
    // optional and will be passed the publishing results after publishing is
    // complete.
    public function publish_async($channel, $item, $callback=null)
    {
        if (!$this->is_async_supported())
            throw new \RuntimeException('Asynchronous publishing not supported. '
                    . 'Recompile PHP with --enable-maintainer-zts to ' 
                    . 'turn pthreads on.');
        $this->tsclient->publish_async($channel, $item, $callback);
    }

    // The finish method is a blocking method that ensures that all asynchronous
    // publishing is complete prior to returning and allowing the consumer to 
    // proceed.
    public function finish()
    {
        if ($this->tsclient != null)
            $this->tsclient->finish();
    }

    // Determines whether async publishing is supported by checking if the
    // Thread class exists. To turn on async publishing support make sure
    // to compile PHP with pthreads turned on.
    public function is_async_supported()
    {
        if (class_exists('Thread'))
            return true;
        return false;
    }
}
?>
