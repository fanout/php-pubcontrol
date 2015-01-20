<?php

/*  pcccbhandler.php
    ~~~~~~~~~
    This module implements the PubControlClientCallbackHandler class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

class PubControlClientCallbackHandler extends Stackable
{
    public $completed = null;
    private $num_calls = null;
    private $callback = null;
    private $success = null;
    private $first_error_message = null;

    public function run()
    {
    }

    public function __construct($num_calls, $callback)
    {
        $this->update($num_calls, $callback);
    }

    public function update($num_calls, $callback)
    {
        $this->completed = false;
        $this->num_calls = $num_calls;
        $this->callback = $callback;
        $this->success = true;
    }

    public function handler($success, $message)
    {
        if (!$success && $this->success)
        {
            $this->success = false;
            $this->first_error_message = $message;
        }
        $this->num_calls -= 1;
        if ($this->num_calls <= 0)
        {
            $this->completed = true;
            $cb = $this->callback;
            call_user_func($cb, $this->success, $this->first_error_message);
        }
    }
}
?>
