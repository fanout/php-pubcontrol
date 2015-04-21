<?php

/*  format.php
    ~~~~~~~~~
    This module implements the Format class.
    :authors: Konstantin Bokarius.
    :copyright: (c) 2015 by Fanout, Inc.
    :license: MIT, see LICENSE for more details. */

namespace PubControl;

// The Format class is provided as a base class for all publishing
// formats that are included in the Item class. Examples of format
// implementations include JsonObjectFormat and HttpStreamFormat.
class Format
{
    // The name of the format which should return a string. Examples
    // include 'json-object' and 'http-response'
    public function name() { }

    // The export method which should return a format-specific hash
    // containing the required format-specific data.
    public function export() { }
}

?>
