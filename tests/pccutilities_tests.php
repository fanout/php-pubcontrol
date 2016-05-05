<?php

class TestClassForPubcallTesting1 extends PubControl\PccUtilities
{
    private $test = null;
    public $wasRequestMade = false;

    public function __construct($test)
    {
        $this->test = $test;
    }

    public function make_http_request($uri, $headers, $content)
    {
        $this->test->assertEquals($uri, 'uri/publish/');
        $this->test->assertEquals($headers, array(
            'Content-Type: application/json',
            'Authorization: auth_header'));
        $this->test->assertEquals($content, array('items' => 'items'));
        $this->wasRequestMade = true;
        return array('response', 200);
    }
}

class TestClassForPubcallTesting2 extends PubControl\PccUtilities
{
    private $test = null;
    public $wasRequestMade = false;

    public function __construct($test)
    {
        $this->test = $test;
    }

    public function makeHttpRequest($uri, $headers, $content)
    {
        $this->test->assertEquals($uri, 'uri/publish/');
        $this->test->assertEquals($headers, array(
            'Content-Type: application/json'));
        $this->test->assertEquals($content, array('items' => 'items'));
        $this->wasRequestMade = true;
        return array('response', 300);
    }
}

class PccUtilitiesTests extends PHPUnit_Framework_TestCase
{
    public function testPubcall()
    {
        $pccu = new TestClassForPubcallTesting1($this);
        $pccu->pubcall('uri', 'auth_header', 'items');
        $this->assertTrue($pccu->wasRequestMade);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testPubcallFailure()
    {
        $pccu = new TestClassForPubcallTesting2($this);
        $pccu->pubcall('uri', null, 'items');
        $this->assertTrue($pccu->wasRequestMade);
    }

    public function testVerifyHttpStatusCode()
    {
        $pccu = new PubControl\PccUtilities();
        foreach (range(200, 299) as $number)
        {
            $pccu->verify_http_status_code('response', $number);
        }
    }

    /**
     * @expectedException RuntimeException
     */
    public function testVerifyHttpStatusCodeFailure1()
    {
        $pccu = new PubControl\PccUtilities();
        $pccu->verify_http_status_code('response', 199);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testVerifyHttpStatusCodeFailure2()
    {
        $pccu = new PubControl\PccUtilities();
        $pccu->verify_http_status_code('response', 300);
    }

    public function testGenAuthHeaderBasic()
    {
        $pccu = new PubControl\PccUtilities();
        $header = $pccu->gen_auth_header(null, null, 'user', 'pass');
        $this->assertEquals($header, 'Basic ' . base64_encode('user' . ':' .
                'pass'));
    }

    public function testGenAuthHeaderJwt()
    {
        $pccu = new PubControl\PccUtilities();
        $header = $pccu->gen_auth_header(array('claim' => 'hello', 'exp' =>
            1000), 'key==', null, null);

        // Header is {"typ":"JWT","alg":"HS256"}{"claim":"hello","exp":1000}
        $this->assertEquals($header, 'Bearer eyJ0eXAiOiJKV1QiLCJhb' .
                'GciOiJIUzI1NiJ9.eyJjbGFpbSI6ImhlbGxvIiwiZXhwIjoxMDAwfQ.-' .
                'de7_nwFHcDuvyAX2ptOKpTdDKJNw3WmOPK2oQ8vpS4');
        $header = $pccu->gen_auth_header(array('claim' => 'hello'),
                'key==', null, null);
        $claim = Firebase\JWT\JWT::decode(substr($header, 7), 'key==', ['HS256']);
        $this->assertTrue(array_key_exists('exp', $claim));
    }
}

?>
