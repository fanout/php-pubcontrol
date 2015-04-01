<?php

class FormatTests extends PHPUnit_Framework_TestCase
{
    public function testName()
    {
        $format = new Format();
        $format->name();
    }

    public function testExport()
    {
        $format = new Format();
        $format->export();
    }
}

?>
