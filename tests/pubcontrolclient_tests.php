<?php

class NoAsyncPubControlClientClass extends PubControl\PubControlClient
{
    public function is_async_supported()
    {
        return false;
    }
}

class ItemTestClass
{
    public function export()
    {
        return array('name' => 'export');
    }
}

class ThreadSafeClientTestClass
{
    public $was_publish_called = false;
    public $was_finish_called = false;
    private $test = null;
    public $mutex = null;
    public $thread_cond = null;
    public $thread_mutex = null;

    public function __construct($test)
    {
        $this->test = $test;
    }

    public function publish_async($channel, $item, $callback = null)
    {
        $this->test->assertEquals($channel, 'chan');
        $this->test->assertEquals($item, 'item');
        $this->test->assertEquals($callback, 'callback');
        $this->was_publish_called = true;
    }

    public function finish()
    {
        $this->was_finish_called = true;
    }
}

class PccUtilitiesTestClass
{
    public $was_pubcall_called = false;
    private $test = null;

    public function __construct($test)
    {
        $this->test = $test;
    }

    public function pubcall($uri, $auth, $content)
    {
        $this->test->assertEquals($uri, 'uri');
        $this->test->assertEquals($auth, 'auth');
        $this->test->assertEquals($content, array(array(
            'channel' => 'channel',
            'name' => 'export')));
        $this->was_pubcall_called = true;
    }

    public function gen_auth_header($claim, $key, $user, $pass)
    {
        $this->test->assertEquals($claim, 'claim');
        $this->test->assertEquals($key, 'key');
        $this->test->assertEquals($user, 'user');
        $this->test->assertEquals($pass, 'pass');
        return 'auth';
    }
}

class TestPubControlClient extends PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $pc = new PubControl\PubControlClient('uri');
        $this->assertEquals($pc->uri, 'uri');
        $this->assertFalse(is_null($pc->pcc_utilities));
        $this->assertFalse(is_null($pc->tsclient));
        $this->assertFalse(is_null($pc->req_queue));
        $pc = new NoAsyncPubControlClientClass('uri');
        $this->assertTrue(is_null($pc->tsclient));
        $this->assertTrue(is_null($pc->req_queue));
    }

    public function testSetAuthBasic()
    {
        $pc = new PubControl\PubControlClient('uri');
        $pc->set_auth_basic('user', 'pass');
        $this->assertEquals($pc->auth_basic_user, 'user');
        $this->assertEquals($pc->auth_basic_pass, 'pass');
        $this->assertEquals($pc->tsclient->auth_basic_user, 'user');
        $this->assertEquals($pc->tsclient->auth_basic_pass, 'pass');
    }

    public function testSetAuthJwt()
    {
        $pc = new PubControl\PubControlClient('uri');
        $pc->set_auth_jwt('claim', 'key');
        $this->assertEquals($pc->auth_jwt_claim, 'claim');
        $this->assertEquals($pc->auth_jwt_key, 'key');
        $this->assertEquals($pc->tsclient->auth_jwt_claim, 'claim');
        $this->assertEquals($pc->tsclient->auth_jwt_key, 'key');
    }

    public function testPublish()
    {
        $pc = new PubControl\PubControlClient('uri');
        $pc->set_auth_basic('user', 'pass');
        $pc->set_auth_jwt('claim', 'key');
        $pc->pcc_utilities = new PccUtilitiesTestClass($this);
        $pc->publish('channel', new ItemTestClass());
        $this->assertTrue($pc->pcc_utilities->was_pubcall_called);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testPublishAsyncException()
    {
        $pc = new NoAsyncPubControlClientClass('uri');
        $pc->publish_async('chan', 'item', 'callback');
    }

    public function testPublishAsync()
    {
        $pc = new PubControl\PubControlClient('uri');
        $pc->tsclient = new ThreadSafeClientTestClass($this);
        $pc->publish_async('chan', 'item', 'callback');
        $this->assertTrue($pc->tsclient->was_publish_called);
    }

    public function testFinish()
    {
        $pc = new PubControl\PubControlClient('uri');
        $pc->tsclient = new ThreadSafeClientTestClass($this);
        $pc->finish();
        $this->assertTrue($pc->tsclient->was_finish_called);
    }

    public function testIsAsyncSupported()
    {
        $pc = new PubControl\PubControlClient('uri');
        $this->assertTrue($pc->is_async_supported());
    }
}

?>
