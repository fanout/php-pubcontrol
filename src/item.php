<?php

/*  item.php
    ~~~~~~~~~
    This module implements the Item class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

namespace PubControl;

// The Item class is a container used to contain one or more format
// implementation instances where each implementation instance is of a
// different type of format. An Item instance may not contain multiple
// implementations of the same type of format. An Item instance is then
// serialized into a hash that is used for publishing to clients.
class Item
{
    private $formats = null;
    private $id = null;
    private $prev_id = null;

    // The initialize method can accept either a single Format implementation
    // instance or an array of Format implementation instances. Optionally
    // specify an ID and/or previous ID to be sent as part of the message
    // published to the client.
    public function __construct($formats, $id=null, $prev_id=null)
    {
        $this->id = $id;
        $this->prev_id = $prev_id;
        if (!is_array($formats))
            $formats = array($formats);
        $this->formats = $formats;
    }

    // The export method serializes all of the formats, ID, and previous ID
    // into a hash that is used for publishing to clients. If more than one
    // instance of the same type of Format implementation was specified then
    // an error will be raised.
    public function export()
    {
        $format_types = array();
        foreach ($this->formats as $format)
        {
            $format_class_name = get_class($format);
            if (in_array($format_class_name, $format_types))
                throw new \RuntimeException('Multiple ' .
                        $format_class_name . ' format classes specified');
            array_push($format_types, $format_class_name);
        }
        $out = array();
        if (!is_null($this->id))
            $out['id'] = $this->id;
        if (!is_null($this->prev_id))
            $out['prev-id'] = $this->prev_id;
        foreach ($this->formats as $format)
            $out[$format->name()] = $format->export();
        return $out;
    }
}

?>
