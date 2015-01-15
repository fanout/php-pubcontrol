<?php

/*  pcccbhandler.php
    ~~~~~~~~~
    This module implements the PubControlClientCallbackHandler class.
    class should be used publicly.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

class PubControlClientCallbackHandler
{
    private $num_calls = null;
    private $callback = null;
    private $success = null;
    private $first_error_message = null;

    public function __construct($num_calls, $callback)
    {
        $this->num_calls = $numcalls;
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
            $this->callback($this->success, $this->first_error_message);
    }
}
?>
