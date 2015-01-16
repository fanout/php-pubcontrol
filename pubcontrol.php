<?php

/*  pubcontrol.php
    ~~~~~~~~~
    This module implements the PubControl class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

require 'pubcontrolclient.php';
require 'pcccbhandler.php';

class PubControl
{
    private $clients = null;

    public function __construct($config=null)
    {
        $this->clients = array();
        if (!is_null($config))
            $this->apply_config($config);
    }

    public function remove_all_clients()
    {
        $this->clients = array();
    }

    public function add_client($client)
    {
        $this->clients[] = $client;
    }

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

    public function finish()
    {
        foreach ($this->clients as $client)
            $client->finish();
    }

    public function publish($channel, $item)
    {
        foreach ($this->clients as $client)
            $client->publish($channel, $item);
    }

    public function publish_async($channel, $item, $callback=null)
    {
        $cb = null;
        if (!is_null($callback))
        {
            $pcccbhandler = new PubControlClientCallbackHandler(
                    count($this->clients), $callback);
            $cb = array($pcccbhandler, 'handler');
        }
        foreach ($this->clients as $client)
            $client->publish_async($channel, $item, $cb);
    }
}
?>
