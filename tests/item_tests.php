<?php

class TestFormatSubclass1 extends Format
{
    public function name() { return 'name1'; }

    public function export() { return 'export1'; }
}

class TestFormatSubclass2 extends Format
{
    public function name() { return 'name2'; }

    public function export() { return 'export2'; }
}

class ItemTests extends PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $item = new Item('format');
        $item = new Item('format', 'id', 'prev-id');
    }

    public function testExport()
    {
        $item = new Item(new TestFormatSubclass1());
        $this->assertEquals($item->export(), array('name1' => 'export1'));
        $item = new Item(new TestFormatSubclass1(), 'id', 'prev-id');
        $this->assertEquals($item->export(), array('name1' => 'export1',
                'id' => 'id', 'prev-id' => 'prev-id'));
        $item = new Item(array(new TestFormatSubclass1(),
                new TestFormatSubclass2()));
        $this->assertEquals($item->export(), array('name1' => 'export1',
                'name2' => 'export2'));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testExportSameFormatException()
    {
        $item = new Item(array(new TestFormatSubclass1(),
                new TestFormatSubclass1()));
        $item->export();
    }
}

?>
