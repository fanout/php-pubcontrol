<?php

class TestFormatSubclass1 extends PubControl\Format
{
    public function name() { return 'name1'; }

    public function export() { return 'export1'; }
}

class TestFormatSubclass2 extends PubControl\Format
{
    public function name() { return 'name2'; }

    public function export() { return 'export2'; }
}

class ItemTests extends PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $item = new PubControl\Item('format');
        $item = new PubControl\Item('format', 'id', 'prev-id');
    }

    public function testExport()
    {
        $item = new PubControl\Item(new TestFormatSubclass1());
        $this->assertEquals($item->export(), array('name1' => 'export1'));
        $item = new PubControl\Item(new TestFormatSubclass1(), 'id', 'prev-id');
        $this->assertEquals($item->export(), array('name1' => 'export1',
                'id' => 'id', 'prev-id' => 'prev-id'));
        $item = new PubControl\Item(array(new TestFormatSubclass1(),
                new TestFormatSubclass2()));
        $this->assertEquals($item->export(), array('name1' => 'export1',
                'name2' => 'export2'));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testExportSameFormatException()
    {
        $item = new PubControl\Item(array(new TestFormatSubclass1(),
                new TestFormatSubclass1()));
        $item->export();
    }
}

?>
