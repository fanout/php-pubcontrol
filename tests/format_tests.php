<?php

class FormatTests extends PHPUnit_Framework_TestCase
{
    public function testName()
    {
        $format = new PubControl\Format();
        $format->name();
    }

    public function testExport()
    {
        $format = new PubControl\Format();
        $format->export();
    }
}

?>
