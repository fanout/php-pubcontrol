<?php

/*  threadsafeclient.php
    ~~~~~~~~~
    This module implements the ThreadSafeClient and ThreadSafeArray
    classes. Note that ThreadSafeClient should not be used directly.
    Use the PubControlClient class instead.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

/* NOTE: The ThreadSafeClient class cannot be used directly.
   Use the PubControlClient class instead. */

// The ThreadSafeClient internal class is used to facilitate async publishing
// via the PHP pthreads feature. It essentially provides the same functionality
// that PubControlClient does along with async-capabilities.
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
    public $pcc_utilities = null;

    // Initialize with a URI and request queue instance.
    public function __construct($uri, $req_queue)
    {
        $this->req_queue = $req_queue;
        $this->uri = $uri;
        $this->mutex = Mutex::create();
        $this->pcc_utilities = new PccUtilities(); 
    }
    
    // Call this method and pass a username and password to use basic
    // authentication with the configured endpoint.  
    function set_auth_basic($username, $password)
    {
        Mutex::lock($this->mutex);
        $this->auth_basic_user = $username;
        $this->auth_basic_pass = $password;
        Mutex::unlock($this->mutex);
    }

    // Call this method and pass a claim and key to use JWT authentication
    // with the configured endpoint.
    public function set_auth_jwt($claim, $key)
    {
        Mutex::lock($this->mutex);
        $this->auth_jwt_claim = $claim;
        $this->auth_jwt_key = $key;
        Mutex::unlock($this->mutex);
    }

    // The asynchronous publish method for publishing the specified item to the
    // specified channel on the configured endpoint. The callback method is
    // optional and will be passed the publishing results after publishing is
    // complete.
    public function publish_async($channel, $item, $callback=null)
    {
        $export = $item->export();
        $export['channel'] = $channel;
        $uri = null;
        $auth = null;
        Mutex::lock($this->mutex);
        $uri = $this->uri;
        $auth = $this->pcc_utilities->gen_auth_header($this->auth_jwt_claim,
                $this->auth_jwt_key, $this->auth_basic_user,
                $this->auth_basic_pass);
        $this->ensure_thread();
        Mutex::unlock($this->mutex);
        $this->queue_req(array('pub', $uri, $auth, $export, $callback));
    }

    // The finish method is a blocking method that ensures that all asynchronous
    // publishing is complete prior to returning and allowing the consumer to 
    // proceed.    
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

    // An internal method that is meant to run as a separate thread and process
    // asynchronous publishing requests. The method runs continously and
    // publishes requests in batches containing a maximum of 10 requests. The
    // method completes and the thread is terminated only when a 'stop' command
    // is provided in the request queue.
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
                $this->pubbatch($reqs);
        }
    }

    // An internal method that ensures that asynchronous publish calls are
    // properly processed. This method initializes the required class fields,
    // starts the pubworker worker thread, and is meant to execute only when
    // the consumer makes an asynchronous publish call.
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

    // An internal method for adding an asynchronous publish request to the 
    // publishing queue. This method will also activate the pubworker worker
    // thread to make sure that it process any and all requests added to
    // the queue.    
    public function queue_req($req)
    {
        Mutex::lock($this->thread_mutex);
        $this->req_queue[] = $req;
        Cond::signal($this->thread_cond);    
        Mutex::unlock($this->thread_mutex);
    }

    // An internal method for publishing a batch of requests. The requests are
    // parsed for the URI, authorization header, and each request is published
    // to the endpoint. After all publishing is complete, each callback
    // corresponding to each request is called (if a callback was originally
    // provided for that request) and passed a result indicating whether that
    // request was successfully published.
    public function pubbatch($reqs)
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
            $this->pcc_utilities->pubcall($uri, $auth_header, $items);
            $result = array(true, '');
        }
        catch (RuntimeException $exception)
        {
            $result = array(false, $exception->getMessage());
        }
        foreach ($callbacks as $callback)
            if (!is_null($callback))
            {
                call_user_func($callback, $result[0], $result[1]);
            }
    }
}

// A thread safe array used for the request queue.
class ThreadSafeArray extends Stackable
{

    // Required stackable interface method.
    public function run()
    {
    }
}
?>
