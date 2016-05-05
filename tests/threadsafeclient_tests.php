<?php

class ThreadSafeClientForTesting1 extends PubControl\ThreadSafeClient
{
    public $was_ensure_thread_called = false;
    public $was_queue_req_called = false;
    public $req = null;

    public function __construct()
    {
        parent::__construct('uri', new PubControl\ThreadSafeArray());
    }

    public function ensure_thread()
    {
        $this->was_ensure_thread_called = true;
    }

    public function queue_req($req)
    {
        $this->req = $req;
        $this->was_queue_req_called = true;
    }
}

class ThreadSafeClientForTesting2 extends PubControl\ThreadSafeClient
{
    public $was_pubbatch_called = false;
    public $reqs = null;

    public function __construct($req_queue)
    {
        parent::__construct('uri', $req_queue);
    }

    public function pubbatch($reqs)
    {
        $this->reqs = $reqs;
        if (count($reqs) > 10)
            throw new RuntimeException('too many reqs');
        $this->was_pubbatch_called = true;
    }
}

class PccUtilitiesStackableTestClass extends Stackable
{
    public $was_pubcall_called = false;
    public $uri = false;
    public $auth = false;
    public $content = false;

    public function pubcall($uri, $auth, $content)
    {
        $this->uri = $uri;
        $this->auth = $auth;
        $this->content = $content;
        $this->was_pubcall_called = true;
    }

    public function run()
    {
    }
}

class PccUtilitiesFailureTestClass extends Stackable
{
    public function pubcall($uri, $auth, $content)
    {
        throw new RuntimeException('message');
    }

    public function run()
    {
    }
}

class TestThreadSafeClient extends PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $this->assertEquals($pc->uri, 'uri');
        $this->assertEquals($pc->req_queue, $req_queue);
        $this->assertFalse(is_null($pc->mutex));
        $this->assertFalse(is_null($pc->pcc_utilities));
    }

    public function testSetAuthBasic()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $pc->set_auth_basic('user', 'pass');
        $this->assertEquals($pc->auth_basic_user, 'user');
        $this->assertEquals($pc->auth_basic_pass, 'pass');
    }

    public function testSetAuthJwt()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $pc->set_auth_basic('claim', 'key');
        $this->assertEquals($pc->auth_basic_user, 'claim');
        $this->assertEquals($pc->auth_basic_pass, 'key');
    }

    public function testPublishAsync()
    {
        $pc = new ThreadSafeClientForTesting1();
        $pc->set_auth_basic('user', 'pass');
        $pc->publish_async('chan', new ItemTestClass(), 'callback');
        $this->assertEquals($pc->req[0], 'pub');
        $this->assertEquals($pc->req[1], 'uri');
        $this->assertEquals($pc->req[2], 'Basic ' .
                base64_encode('user' . ':' . 'pass'));
        $this->assertEquals($pc->req[3], array(
                'channel' => 'chan',
                'name' => 'export'));
        $this->assertEquals($pc->req[4], 'callback');
        $this->assertTrue($pc->was_ensure_thread_called);
        $this->assertTrue($pc->was_queue_req_called);
    }

    public function testFinish1()
    {
        $pc = new ThreadSafeClientForTesting1();
        $pc->is_thread_running = true;
        $pc->finish();
        $this->assertEquals($pc->req[0], 'stop');
        $this->assertTrue($pc->was_queue_req_called);
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $pc->ensure_thread();
        $this->assertTrue($pc->isRunning());
        $pc->finish();
        $this->assertFalse($pc->isRunning());
    }

    public function testRun()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new ThreadSafeClientForTesting2($req_queue);
        $pc->ensure_thread();
        foreach (range(0, 500) as $number)
        {
            $pc->queue_req(array('pub', 'uri', 'auth',
                    'export', 'callback'));
        }
        $pc->queue_req(array('stop'));
        $pc->join();
        $this->assertEquals($pc->reqs[0][0], 'uri');
        $this->assertEquals($pc->reqs[0][1], 'auth');
        $this->assertEquals($pc->reqs[0][2], 'export');
        $this->assertEquals($pc->reqs[0][3], 'callback');
        $this->assertTrue($pc->was_pubbatch_called);
    }

    public function testEnsureThread()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $pc->ensure_thread();
        $this->assertEquals($pc->is_thread_running, true);
        $this->assertFalse(is_null($pc->thread_cond));
        $this->assertFalse(is_null($pc->thread_mutex));
        $this->assertTrue($pc->isRunning());
        $pc->finish();
    }

    public function testQueueReq()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $pc->ensure_thread();
        $pc->finish();
        $pc->queue_req('req');
        $this->assertEquals($req_queue->shift(), 'req');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testPubbatchException()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $pc->pubbatch(array());
    }

    public function testPubbatch1()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $utilities = new PccUtilitiesStackableTestClass();
        $pc->pcc_utilities = $utilities;
        $callback1 = new CallbackTestClass();
        $callback2 = new CallbackTestClass();
        $pc->pubbatch(array(array('uri2', 'auth', 'export',
                array($callback1, "callback")),
                array('uri2', 'auth', 'export',
                array($callback2, "callback"))));
        $this->assertTrue($callback1->was_callback_called);
        $this->assertEquals($callback1->result, true);
        $this->assertEquals($callback1->message, null);
        $this->assertTrue($callback2->was_callback_called);
        $this->assertEquals($callback2->result, true);
        $this->assertEquals($callback2->message, null);
        $this->assertEquals($pc->pcc_utilities->uri, 'uri2');
        $this->assertEquals($pc->pcc_utilities->auth, 'auth');
        $this->assertEquals($pc->pcc_utilities->content, ['export', 'export']);
        $this->assertTrue($pc->pcc_utilities->was_pubcall_called);
    }

    public function testPubbatch2()
    {
        $req_queue = new PubControl\ThreadSafeArray();
        $pc = new PubControl\ThreadSafeClient('uri', $req_queue);
        $utilities = new PccUtilitiesFailureTestClass();
        $pc->pcc_utilities = $utilities;
        $callback = new CallbackTestClass();
        $pc->pubbatch(array(array('uri2', 'auth', 'export',
                array($callback, "callback"))));
        $this->assertTrue($callback->was_callback_called);
        $this->assertEquals($callback->result, false);
        $this->assertEquals($callback->message, 'message');
    }
}

?>
