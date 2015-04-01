<?php

/*  pubcontrol.php
    ~~~~~~~~~
    This module implements the PubControl class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

if (class_exists('Thread'))
    require dirname(__FILE__) . '/pcccbhandler.php';

// The PubControl class allows a consumer to manage a set of publishing
// endpoints and to publish to all of those endpoints via a single publish
// or publish_async method call. A PubControl instance can be configured
// either using a hash or array of hashes containing configuration information
// or by manually adding PubControlClient instances.
class PubControl
{
    protected $clients = null;
    protected $pcccbhandlers = null;

    // Initialize with or without a configuration. A configuration can be applied
    // after initialization via the apply_config method.
    public function __construct($config=null)
    {
        $this->clients = array();
        $this->pcccbhandlers = array();
        if (!is_null($config))
            $this->apply_config($config);
    }

    // Remove all of the configured PubControlClient instances.
    public function remove_all_clients()
    {
        $this->clients = array();
    }

    // Add the specified PubControlClient instance.
    public function add_client($client)
    {
        $this->clients[] = $client;
    }

    // Apply the specified configuration to this PubControl instance. The
    // configuration object can either be a hash or an array of hashes where
    // each hash corresponds to a single PubControlClient instance. Each hash
    // will be parsed and a PubControlClient will be created either using just
    // a URI or a URI and JWT authentication information.
    public function apply_config($config)
    {
        if (!is_array(reset($config)))
            $config = array($config);
        foreach ($config as $entry)
        {
            $pub = new PubControlClient($entry['uri']);
            if (array_key_exists('iss', $entry))
                $pub->set_auth_jwt(array('iss' => $entry['iss']), 
                        $entry['key']);
            $this->clients[] = $pub;
        }
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

    // The finish method is a blocking method that ensures that all asynchronous
    // publishing is complete for all of the configured PubControlClient
    // instances prior to returning and allowing the consumer to proceed.
    public function finish()
    {
        if (!$this->is_async_supported())
            return;
        foreach ($this->clients as $client)
            $client->finish();
    }

    // The synchronous publish method for publishing the specified item to the
    // specified channel for all of the configured PubControlClient instances.
    public function publish($channel, $item)
    {
        foreach ($this->clients as $client)
            $client->publish($channel, $item);
    }

    // The asynchronous publish method for publishing the specified item to the
    // specified channel on the configured endpoint. The callback method is
    // optional and will be passed the publishing results after publishing is
    // complete. Note that a failure to publish in any of the configured
    // PubControlClient instances will result in a failure result being passed
    // to the callback method along with the first encountered error message.
    // If async publishing is not supported then an exception will be thrown.
    public function publish_async($channel, $item, $callback=null)
    {
        if (!$this->is_async_supported())
            throw new RuntimeException('Asynchronous publishing not supported. '
                    . 'Recompile PHP with --enable-maintainer-zts to ' 
                    . 'turn pthreads on.');
        $cb = null;
        if (!is_null($callback))
        {
            $pcccbhandler = null;
            foreach ($this->pcccbhandlers as $key => $value)
                if ($value->completed)
                {
                    $pcccbhandler = $value;
                    $pcccbhandler->update(count($this->clients), $callback);
                    break;
                }
            if (is_null($pcccbhandler))  
            {
                $pcccbhandler = new PubControlClientCallbackHandler(
                        count($this->clients), $callback);
                $this->pcccbhandlers[] = $pcccbhandler;
            }
            $cb = array($pcccbhandler, 'handler');
        }
        foreach ($this->clients as $client)
            $client->publish_async($channel, $item, $cb);
    }
}
?>
