<?php

/*  item.php
    ~~~~~~~~~
    This module implements the Item class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

class Item
{
    private $formats = null;
    private $id = null;
    private $prev_id = null;

    public function __construct($formats, $id=null, $prev_id=null)
    {
        $this->id = $id;
        $this->prev_id = $prev_id;
        if (!is_array($formats))
            $formats = array($formats);
        $this->formats = $formats;
    }

    public function export()
    {
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
