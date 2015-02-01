php-pubcontrol
============

Author: Konstantin Bokarius <kon@fanout.io>

EPCP library for PHP.

License
-------

php-pubcontrol is offered under the MIT license. See the LICENSE file.

Requirements
------------

* openssl
* curl
* pthreads (required for asynchronous publishing)
* firebase/php-jwt >=1.0.0 (retreived automatically via Composer)

Installation
------------

Using Composer: 'composer require fanout/pubcontrol' 

Manual: ensure that php-jwt has been included and require the following files in php-pubcontrol:

```PHP
require 'php-pubcontrol/src/format.php';
require 'php-pubcontrol/src/item.php';
require 'php-pubcontrol/src/pubcontrolclient.php';
require 'php-pubcontrol/src/pubcontrol.php';
```

Asynchronous Publishing
-----------------------

In order to make asynchronous publish calls pthreads must be installed. If pthreads is not installed then only synchronous publish calls can be made. To install pthreads recompile PHP with the following flag: '--enable-maintainer-zts'

Also note that since a callback passed to the publish_async methods is going to be executed in a separate thread, that callback and the class it belongs to are subject to the rules and limitations imposed by the pthreads extension.

See more information about pthreads here: http://php.net/manual/en/book.pthreads.php

Usage
------------

```PHP
<?php

class HttpResponseFormat extends Format
{
    private $body = null;

    public function __construct($body)
    {
        $this->body = $body;
    }

	function name()
    { 
        return 'http-response';
    }

	function export()
    {
        return array('body' => $this->body);
    }
}

function callback($result, $message)
{
    if ($result)
        Print "Publish successful\r\n";
    else
        Print "Publish failed with message: {$message}\r\n";
}

// PubControl can be initialized with or without an endpoint configuration.
// Each endpoint can include optional JWT authentication info.
// Multiple endpoints can be included in a single configuration.

// Initialize PubControl with a single endpoint:
$pub = new PubControl(array('uri' => 'https://api.fanout.io/realm/<myrealm>',
        'iss' => '<myrealm>', 'key' => base64_decode('<realmkey>')));

// Add new endpoints by applying an endpoint configuration:
$pub->apply_config(array(array('uri' => '<myendpoint_uri_1>'), 
        array('uri' => '<myendpoint_uri_2>')));

// Remove all configured endpoints:
$pub->remove_all_clients();

// Explicitly add an endpoint as a PubControlClient instance:
$pubclient = new PubControlClient('<myendpoint_uri>');
// Optionally set JWT auth: $pubclient->set_auth_jwt(<claim>, '<key>');
// Optionally set basic auth: $pubclient->set_auth_basic('<user>', '<password>');
$pub->add_client($pubclient);

// Publish across all configured endpoints synchronously:
$pub->publish('<channel>', new Item(new HttpResponseFormat("Test publish!")));
// Publish across all configured endpoints asynchronously (requires pthreads):
if ($pub->is_async_supported())
    $pub->publish_async('<channel>', new Item(new HttpResponseFormat(
            "Test async publish!")), );

// Wait for all async publish calls to complete:
$pub->finish();
?>
```
