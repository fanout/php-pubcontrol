<?php

class PubControlTestClass extends PubControl\PubControl
{
    public function getClients()
    {
        return $this->clients;
    }

    public function getPcccbHandlers()
    {
        return $this->pcccbhandlers;
    }
}

class PubControlClientTestClass
{
    public $was_finish_called = false;
    public $was_publish_called = false;
    public $publish_channel = false;
    public $publish_item = false;

    public function finish()
    {
        $this->was_finish_called = true;   
    }

    public function publish($channel, $item)
    {
        $this->was_publish_called = true;
        $this->publish_channel = $channel;
        $this->publish_item = $item;
    }
}

class PubControlClientAsyncTestClass
{
    public $publish_channel = null;
    public $publish_item = null;
    public $publish_cb = null;

    public function publish_async($channel, $item, $callback=null)
    {
        $this->publish_channel = $channel;
        $this->publish_item = $item;
        $this->publish_cb = $callback;
    }
}

class PubControlTestClassNoAsync extends PubControl\PubControl
{
    public function is_async_supported()
    {
        return false;
    }
}

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

class TestPubControl extends PHPUnit_Framework_TestCase
{
    public function testInitialize()
    {
        $pc = new PubControlTestClass();
        $this->assertEquals(count($pc->getClients()), 0);
        $this->assertEquals(count($pc->getPcccbHandlers()), 0);
        $pc = new PubControlTestClass(array(array(
                'uri' => 'uri',
                'iss' => 'iss',
                'key' => 'key=='),
                array('uri' => 'uri2',
                'iss' => 'iss2',
                'key' => 'key==2')));
        $this->assertEquals(count($pc->getClients()), 2);
        $this->assertEquals(count($pc->getPcccbHandlers()), 0);
        $this->assertEquals($pc->getClients()[0]->uri, 'uri');
        $this->assertEquals($pc->getClients()[0]->auth_jwt_claim,
                array('iss' => 'iss'));
        $this->assertEquals($pc->getClients()[0]->auth_jwt_key, 'key==');
        $this->assertEquals($pc->getClients()[1]->uri, 'uri2');
        $this->assertEquals($pc->getClients()[1]->auth_jwt_claim,
                array('iss' => 'iss2'));
        $this->assertEquals($pc->getClients()[1]->auth_jwt_key, 'key==2');
    }

    public function testRemoveAllClients()
    {
        $pc = new PubControlTestClass();
        $pc->add_client('item');
        $this->assertEquals(count($pc->getClients()), 1);
        $pc->remove_all_clients();
        $this->assertEquals(count($pc->getClients()), 0);
    }

    public function testAddClient()
    {
        $pc = new PubControlTestClass();
        $pc->add_client('item');
        $this->assertEquals(count($pc->getClients()), 1);
        $pc->add_client('item');
        $this->assertEquals(count($pc->getClients()), 2);
    }

    public function testApplyConfig()
    {
        $pc = new PubControlTestClass();
        $pc->apply_config(array(array(
                'uri' => 'uri',
                'iss' => 'iss',
                'key' => 'key=='),
                array('uri' => 'uri2',
                'iss' => 'iss2',
                'key' => 'key==2')));
        $this->assertEquals(count($pc->getClients()), 2);
        $this->assertEquals($pc->getClients()[0]->uri, 'uri');
        $this->assertEquals($pc->getClients()[0]->auth_jwt_claim,
                array('iss' => 'iss'));
        $this->assertEquals($pc->getClients()[0]->auth_jwt_key, 'key==');
        $this->assertEquals($pc->getClients()[1]->uri, 'uri2');
        $this->assertEquals($pc->getClients()[1]->auth_jwt_claim,
                array('iss' => 'iss2'));
        $this->assertEquals($pc->getClients()[1]->auth_jwt_key, 'key==2');
        $pc->apply_config(array(
                'uri' => 'uri3',
                'iss' => 'iss3',
                'key' => 'key==3'));
        $this->assertEquals(count($pc->getClients()), 3);
        $this->assertEquals($pc->getClients()[2]->uri, 'uri3');
        $this->assertEquals($pc->getClients()[2]->auth_jwt_claim,
                array('iss' => 'iss3'));
        $this->assertEquals($pc->getClients()[2]->auth_jwt_key, 'key==3');
    }

    public function testFinish()
    {
        $pc = new PubControl\PubControl();
        $pcc1 = new PubControlClientTestClass();
        $pcc2 = new PubControlClientTestClass();
        $pc->add_client($pcc1);
        $pc->add_client($pcc2);
        $pc->finish();
        $this->assertTrue($pcc1->was_finish_called);
        $this->assertTrue($pcc2->was_finish_called);
        $pc = new PubControlTestClassNoAsync();
        $pcc1 = new PubControlClientTestClass();
        $pc->add_client($pcc1);
        $pc->finish();
        $this->assertFalse($pcc1->was_finish_called);
    }

    public function testPublish()
    {
        $pc = new PubControl\PubControl();
        $pcc1 = new PubControlClientTestClass();
        $pcc2 = new PubControlClientTestClass();
        $pc->add_client($pcc1);
        $pc->add_client($pcc2);
        $pc->publish('chan', 'item');
        $this->assertTrue($pcc1->was_publish_called);
        $this->assertEquals($pcc1->publish_channel, 'chan');
        $this->assertEquals($pcc1->publish_item, 'item');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testPublishAsyncException()
    {
        $pc = new PubControlTestClassNoAsync();
        $pc->publish_async('chan', 'item', 'callback');
    }

    public function testPublishAsync()
    {
        $pc = new PubControl\PubControl();
        $callback = new CallbackTestClass();
        $pcc1 = new PubControlClientAsyncTestClass('uri');
        $pcc2 = new PubControlClientAsyncTestClass('uri');
        $pcc3 = new PubControlClientAsyncTestClass('uri');
        $pc->add_client($pcc1);
        $pc->add_client($pcc2);
        $pc->add_client($pcc3);
        $pc->publish_async('chan', 'item', array($callback, "callback"));
        $this->assertEquals($pcc1->publish_channel, 'chan');
        $this->assertEquals($pcc1->publish_item, 'item');
        $this->assertEquals($pcc2->publish_channel, 'chan');
        $this->assertEquals($pcc2->publish_item, 'item');
        $this->assertEquals($pcc3->publish_channel, 'chan');
        $this->assertEquals($pcc3->publish_item, 'item');
        call_user_func($pcc1->publish_cb, false, 'message');
        call_user_func($pcc2->publish_cb, false, 'message');
        $this->assertTrue(is_null($callback->result));
        call_user_func($pcc3->publish_cb, false, 'message');
        $this->assertFalse($callback->result);
        $this->assertEquals($callback->message, 'message');
    }
}

?>
