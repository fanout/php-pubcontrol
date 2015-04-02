<?php

/*  pcccbhandler.php
    ~~~~~~~~~
    This module implements the PubControlClientCallbackHandler class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

// The PubControlClientCallbackHandler class is used internally for allowing
// an async publish call made from the PubControl class to execute a callback
// method only a single time. A PubControl instance can potentially contain
// many PubControlClient instances in which case this class tracks the number
// of successful publishes relative to the total number of PubControlClient
// instances. A failure to publish in any of the PubControlClient instances
// will result in a failed result passed to the callback method and the error
// from the first encountered failure.
class PubControlClientCallbackHandler extends Stackable
{
    public $completed = null;
    private $callback = null;
    private $num_calls = null;
    private $success = null;
    private $first_error_message = null;

    // Required stackable interface method.
    public function run()
    {
    }

    // The initialize method accepts: a num_calls parameter which is an integer
    // representing the number of PubControlClient instances, and a callback
    // method to be executed after all publishing is complete.
    public function __construct($num_calls, $callback)
    {
        $this->update($num_calls, $callback);
    }

    // Reset the completed and success flags in this instance and update
    // it with the specified num_calls and callback values.
    public function update($num_calls, $callback)
    {
        $this->completed = false;
        $this->num_calls = $num_calls;
        $this->callback = $callback;
        $this->success = true;
    }

    // The handler method which is executed by PubControlClient when publishing
    // is complete. This method tracks the number of publishes performed and 
    // when all publishes are complete it will call the callback method
    // originally specified by the consumer. If publishing failures are
    // encountered only the first error is saved and reported to the callback
    // method.
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
