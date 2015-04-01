<?php

class CallbackTestClass extends Stackable
{
    public $was_callback_called = false;
    public $result = null;
    public $message = null;

    public function callback($result, $message)
    {
        $this->result = $result;
        $this->message = $message;
        $this->was_callback_called = true;
    }

    public function run()
    {
    }
}

class PubControlClientCallbackHandlerTests extends PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $pccbhandler = new PubControlClientCallbackHandler(1, 'callback');
    }

    public function testUpdate()
    {
        $pccbhandler = new PubControlClientCallbackHandler(1, 'callback');
        $pccbhandler->update(2, 'callback');
    }

    public function testHandler()
    {
        $callback_test_class = new CallbackTestClass();
        $pccbhandler = new PubControlClientCallbackHandler(1,
                array($callback_test_class, "callback"));
        $pccbhandler->handler(true, null);
        $this->assertEquals($callback_test_class->result, true);
        $this->assertEquals($callback_test_class->message, null);
        $this->assertTrue($callback_test_class->was_callback_called);   
        $this->assertTrue($pccbhandler->completed);

        $callback_test_class = new CallbackTestClass();
        $pccbhandler = new PubControlClientCallbackHandler(2,
                array($callback_test_class, "callback"));
        $pccbhandler->handler(true, null);
        $this->assertFalse($callback_test_class->was_callback_called);
        $this->assertFalse($pccbhandler->completed);
        $this->assertEquals($callback_test_class->result, null);
        $this->assertEquals($callback_test_class->message, null);
        $pccbhandler->handler(true, null);
        $this->assertEquals($callback_test_class->result, true);
        $this->assertEquals($callback_test_class->message, null);
        $this->assertTrue($callback_test_class->was_callback_called);   
        $this->assertTrue($pccbhandler->completed);

        $callback_test_class = new CallbackTestClass();
        $pccbhandler = new PubControlClientCallbackHandler(3,
                array($callback_test_class, "callback"));
        $pccbhandler->handler(false, 'message');
        $this->assertFalse($callback_test_class->was_callback_called);
        $this->assertFalse($pccbhandler->completed);
        $this->assertEquals($callback_test_class->result, null);
        $this->assertEquals($callback_test_class->message, null);
        $pccbhandler->handler(false, 'message2');
        $this->assertFalse($callback_test_class->was_callback_called);
        $this->assertFalse($pccbhandler->completed);
        $this->assertEquals($callback_test_class->result, null);
        $this->assertEquals($callback_test_class->message, null);
        $pccbhandler->handler(true, null);
        $this->assertEquals($callback_test_class->result, false);
        $this->assertEquals($callback_test_class->message, 'message');
        $this->assertTrue($callback_test_class->was_callback_called);   
        $this->assertTrue($pccbhandler->completed);
    }
}

?>
