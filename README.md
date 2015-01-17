# TAD-PHP

A simple PHP class to interacts with ZK Time & Attendance Devices.

##About

TAD: A class that implements an interface to interacts with ZK Time & Attendance devices.

Documentation found about ZK SOAP api is very limited or poor, however TAD class implements most SOAP functions supported by ZK devices. Specifically TAD class exposes the following 35 methods:

```
get_date, get_att_log, get_user_info, get_all_user_info, get_user_template, get_combination, get_option, get_free_sizes, get_platform, get_fingerprint_algorithm, get_serial_number, get_oem_vendor, get_mac_address, get_device_name, get_manufacture_time, get_antipassback_mode, get_workcode, get_ext_format_mode, get_encrypted_mode, get_pin2_width, get_ssr_mode, get_firmware_version, set_date, set_user_info, set_user_template, delete_user, delete_template, delete_data, delete_user_password, delete_admin, enable, disable, refresh_db, restart, and poweroff.
```
All methods above are implemented by 2 classes: **Providers\TADSoap** and **Providers\TADZKLib**.

There are some SOAP functions supported by ZK devices that it's suppossed,  according to the official docs (which incidentally it's very limited and so poor!!!) must show an expected behaviour, but when they are invoked don't work like is expected, so they become useless (e.g. Restart SOAP call). For these situations, TAD class implement them by **Providers\TADZKLib** class ([PHP_ZKLib] - http://dnaextrim.github.io/php_zklib/). This class takes a different approach to "talk to the device": it uses UDP protocol at device standard port 4370.

PHP_ZKLib class it's been fully integrated, after a refactoring process taking out all duplicated code (DRY).

For practical purposes, you don't have to be worried about when to use TAD class or PHP_ZKLib class because you only have to get a TAD instance (as shown below) and call any of its methods available. The class decides about when runs the method invoked using TAD class or PHP_ZKLib class.

##Requirements
* Any flavour of PHP 5.4+
* PHPUnit to execute the test suite (optional).

## Supported devices

* All ZK Time & Attendance devices with web server built-in (with ZEM600 or less).

##Getting started
###Setting up the environment
After download TAD-PHP, you have 2 ways to get your enviroment configured to use the classes:

####Composer

[Composer](https://getcomposer.org) is the PHP's package manager and is the recommended way to get packages for your projects. It's also able to build automatically ***autoloaders*** if you wrote down your code using PSR-0 and/or PSR-4 standards, avoiding you headaches about everything related to loading classes.

**TADPHP** is built follows PSR-4 standard and comes with a specific file named **composer.json** that allows **Composer** to generate a file named **autoload.php** (beside others files of course). This files generated is the only one you need to include in your project to get all classes required by TADPHP loaded in memory:

1. Install Composer:
	```
    curl -s https://getcomposer.org/installer | php
	
	```

2. Get inside TADPHP root folder and generate the **autoload.php** file:
	```
    php composer.phar dump-autoload
    ```
    The command above will generate a folder called **vendor**. Inside of it, you'll see the **autoload.php**
    
3. Require/Include **autoload.php** file in the **index.php** of your project or whatever file you need to use **TAD-PHP** classes:
	```php
    <?php
    require 'vendor/autoload.php';
    ...
    
    ```
    
####Loading TAD-PHP classes by hand
Even if Composer it's the preferred method to generate the files needed to get all classes loaded, maybe you want to do the task by hand:

1. Copy and paste TAD-PHP folder in your project root.

2. Rename TAD-PHP folder to use a shorter name (for example 'tad').

3. Require/Include all classes required by TAD-PHP using the relative TAD-PHP path

	```php
    <?php
	require 'tad/lib/TADFactory.php';
    require 'tad/lib/TAD.php';
	require 'tad/lib/TADResponse.php';
	require 'tad/lib/Providers/TADSoap.php';
	require 'tad/lib/Providers/TADZKLib.php';
	require 'tad/lib/Exceptions/ConnectionError.php';
	require 'tad/lib/Exceptions/FilterArgumentError.php';
	require 'tad/lib/Exceptions/UnrecognizedArgument.php';
	require 'tad/lib/Exceptions/UnrecognizedCommand.php';
    
    ```
    
####Handling namespaces
All TAD-PHP classes are under the namespace named **TADPHP**. So, to use any class you need to use the **Fully qualified class name**. For example, to get a new instance of **TADFactory class** you need to use:

```php
<?php
...
$tad_factory = new TADPHP\TADFactory();
...
```

However, as your project grows up using fully qualified class names becomes annoying, so it's better to use PHP **USE** sentence:

```php
<?php
...
use TADPHP\TADFactory;
use TADPHP\TAD;
...

$comands = TAD::commands_available();
$tad = (new TADFactory(['ip'=>'192.168.100.156', 'com_key'=>0]))->get_instance();
...
```

###Class instantiation
First, instantiate a TADFactory object, then use it to create a TAD object.
```php
<?php
...
$tad_factory = new TADFactory(['ip'=>'192.168.0.1']);
$tad = $tad_factory->get_instance();
...
```
Or you can get a TAD object in one single step (valid only in PHP 5.4+):
```php
<?php
  $tad = (new TADFactory(['ip'=>'192.168.0.1']))->get_instance();
```
You can customize TAD object traits passing an options array:
```php
<?php
  $options = [
    'ip' => '192.168.0.1',   // '169.254.0.1' by default (totally useless!!!).
    'internal_id' => 100,    // 1 by default.
    'com_key' => 123,        // 0 by default.
    'description' => 'TAD1', // 'N/A' by default.
    'soap_port' => 8080,     // 80 by default,
    'udp_port' => 20000      // 4370 by default.
    'encoding' => 'utf-8'    // iso8859-1 by default.
  ];
  
  $tad_factory = new TADFactory($options);
  $tad = $tad_factory->get_instance();  
```
##TAD API
SOAP API is implemented by **TADSoap class**. All methods that use UDP Protocol are implemented by **PHP_ZKLib class**. Even though you have 2 classes, you do not have to be worried about which method is been calling using SOAP api or through PHP_ZKLib. You've got a single interface.

Some methods need that you set up some parameters prior you can call them. TAD class uses associative arrays as way to pass params to the methods. Using associative arrays is a "more verbose way" that helps you to remember which params you have to pass.

Valid params supported by TAD class are:

```
com_key, pin, time, template, name, password, group, privilege, card, pin2, tz1, tz2, tz3, finger_id, option_name, date, size, valid, value
```

As you can see, params names are so intuitive and easy to remember.

######Note: All examples shown below asusmes $tad variable holds a TAD object.

###Getting a list of commands available
```php
// Get a full list of commands supported by TADPHP\TAD class.
$commands_list = TAD::commands_available();

// Get a list of commands implemented via TADPHP\TADSoap.
$soap_commands =  = TAD::soap_commands_available();

// Get a list of commands implemented via TAD\PHP\TADSoap.
$zklib_commands = TAD::zklib_commands_available();
```

###Getting and Setting Date and Time

```php
// Getting current time and date
$dt = $tad->get_date();

// Setting device's date to '2014-01-01' (time will be set to now!)
$response = $tad->set_date(['date'=>'2014-01-01']);

// Setting device's time to '12:30:15' (date will be set to today!)
$response = $tad->set_date(['time'=>'12:30:15']);

// Setting device's date & time
$response = $tad->set_date(['date'=>'2014-01-01', 'time'=>'12:30:15']);

// Setting device's date & time to now.
$response = $tad->set_date();
```
###Getting attendance logs
You can retrieve attendance logs for all user or just for one:
```php
// Getting attendance logs from all users.
$logs = $tad->get_att_log();

// Getting attendance logs from one user.
$logs = $tad->get_att_log(['pin'=>123]);
```
###Getting information about users.
You can get all information about a single user or all users. The information you can get include:

* PIN: Internal user's ID (this is an id generated by the device).
* Name: User's name.
* Password: Password used to check in/out.
* Card: Card number (relevant if you device supports RFID technology).
* Privilege: User's role (1: regular user, 2: enroller, 6: admin and 14: superadmin)
* Group: User's group privilege.
* PIN2: Personal identity number (this is an id you can set according your needs).
* TZ1: User's time zone 1.
* TZ2: User's time zone 2.
* TZ3: User's time zone 3:

```php
// Getting info from a specific user.
$user_info = $tad->get_user_info(['pin'=>123]);

// Getting info from all users.
$all_user_info = $tad->get_all_user_info();
```
###Creating / Updating users
TAD class allows you to register new users in the device or even you can update (change) information about an user already registered. However to achieve this, TAD class needs to delete the user (of course this applies when you are updating user's information) and then creates the user. Maybe this is not the best way to do that, but if TAD just calls the method to create a user, it will be created as many times as you call it.

If you look into PHP_ZKLib code, you'll see a method to create / update users. However, when you call that method, it generates a PIN code (not PIN2 code) in a way that if that code already exists in the device, it refuses to create the user. This is a method that should be modified to make it working properly but the way how PIN code is created is unknown.

In the meantime, TAD class uses delete and create SOAP calls. Of course, to make things easy for you, you have to call just 1 method.
```php
// We are creating a superadmin user named 'Foo Bar' with a PIN = 123 and password = 4321.
$r = $tad->set_user_info([
    'pin' => 123,
    'name'=> 'Foo Bar',
    'privilege'=> 14,
    'password' => 4321
]);
```
######Note: The way TAD class creates / updates users has one big concern you have to be aware. The user's fingerprints stored (templates) are lost!!!, so it is necessary to save them prior you call set_user_info() method.

###Uploading user's fingerprints
The device uses an algorithm to encode fingerprints called "BioBridge" and it has 2 flavors: VX 9.0 and the new one VX 10.0. According the documentation, VX 10.0 generates shorter encoded fingerprints and it's faster when the device has to make searchings for a fingerprint match process. However, TAD class exposes a method to upload fingerprints but it works only when device is configured to use the old BioBridge VX 9.0 algorithm. When device uses VX 10.0 algorithm, the machine freezes!!!. When asked to ZK Software forum, the answer got was: "It has to work with any biobridge version. Check your code!".  Any help about this, would be appreciated.

```php
/** Setting a user template (fingerprint).
 * 
 * You can upload until 10 templates per user. You have to use 'finger_id' param to indicate
 * which fingerprint you are uploading to.
 * 
 * Till now, this method only works with VX 9.0 BioBridge algorithm :-(. Any help
 * arround this issue will be appreciated. 
 */
 
// The folowing string represents a fingerprint encoded using BioBridge algorithm VX 9.0
$template1_vx9 = "ocosgoulTUEdNKVRwRQ0I27BDTEkdMEONK9KQQunMVSBK6VPLEENk9MwgQ+DP3PBC1FTXEEG4ihpQQQ3vFQBO4K+WwERYilHAQ8ztktBEBbKQ0ELDtJrwQ7dqCiBCz+/IgEGKrBjQQhEO0zBFQNDQYEKFbhrQQdLF1wBDxclfUELMNFXwQRvvmHBCslKUAEZfU1OQRzmIU5BXRW0eoEKPMltgQnQGUyBJQSfRIEUSzIdAQ45l3gBByHUTMEJ5yVhQQmi0UZBFHvYPUEGeKxTAQ6rFGNBCIYURoEOZS9VwR+1M4RoE5m0DRUTF8DHd6HdqxHAxWmj393M28DDX2FkanKi/t7LGsDCWqGarmt1BaL/25nAwVaiipu/cgcQGKG6mcDBU6KYmr5wChQcobmJIsDBUKKJmZ1uExyi+ZaYwMFMgU2CQCSinYdnJsDBR4Ghl3Q4owa3dnfAwUamdlZlR5p2Zi7AwUSndERlfOpWZlfAwUOiQzVkLDhDopRUVTLAwT2iQ0ZjIzVMolNFRcDBN6I0ZlQebVaiEjRVwMEyolVVUxVxXKEBRUTAwS+iZVYyD3JhoQJFTMDBLKJlVUIKcWShBVVTwMIkoWVkFQhyaaEVZ1rAwh6hVlUPAW+iNGd3wMIToWdlBnWiRWZ3aMDDCqRmZjRpZmrAxASjd2Vnh2/gAA==";

$template1_data = [
  'pin' => 123,
  'finger_id' => 0, // First fingerprint has 0 as index.
  'size' => 514,    // Be careful, this is not string length of $template1_vx9 var.
  'valid' => 1,
  'template' => $template1_vx9
];

$tad->set_user_template( $template1_data );
```

###Deleting user's fingerprints
When you have to delete user's fingerprints, you delete all of them. You cannot delete fingerprint one by one.

```php
// Delete all fingerprints (template) for user with PIN = 123.
$tad->delete_template(['pin'=>123]);
```

###Deleting user's passwords
```php
// Delete password for user with PIN = 123.
$tad->delete_user_password(['pin'=>123]);
```

###Deleting users
```php
/// Delete user with PIN = 123.
$tad->delete_user(['pin'=>123]);
```

###Deleting admin users
From the device point of view, users that have a privilege level not equal to 0, are considered admin users, so this method enables you wipe them out with one single step.
```php
$tad->delete_admin();
```

###Clearing out data from device
You can clear information from device according a code you specify.

Code meaning:

1: clear all device data, 2: clear all users templates and 3: clear all attendance logs.
```php
// Delete all attendance logs stored.
$tad->delete_data(['value'=>3]);
```

###Getting some statistics from the device.
You can get some valuable statistics about your device including:

 * Space available for templates. 
 * Space available for attendance logs.
 * Total storage capacity for attendance logs.
 * Total storage capacity for user templates.
 * Total users stored
 * Total user passwords stored.
 * Total attendance logs stored.
 * Total templates stored. 

```php
// Get some device statistics.
$fs = $tad->get_free_sizes();
```
###Disabling / Enabling the device
Sometimes you need to lock device's screen and keypad to prevent users can use it. You can disable the device and when you want to get it back working, just enable it!
```php
// Disabling device.
$tad->disable();

...

// Enabling device.
$tad->enable();
```

###Restarting / Shutting down the device.
```php
// Restart the device!!!
$tad->restart();

...

// Shut down the device!!!
$tad->poweroff();
```

##TADResponse API
Every command executed via TAD class will return an object that is an instance of **TADResponse** class. This object contains all information about the device's response received. This way you can get full flexibility to manipulate the responses that you got from the device: you can transform it in XML, JSON or even you can get an array. Also you can set some criterias to make a filtering process on the response.

**TADResponse** class offers 14 methods:
```
get_response, set_response, get_encoding, set_encoding, get_header, set_header, get_response_body, to_xml, to_json, to_array, count, is_empty_response, filter_xml and filter_by

```
### Getting and Setting responses
When you call any TAD method, all responses (including those empty ones) are returned as an **TADResponse object**, so you can invoke any of methods mentioned above:
```php
$r= $tad->get_date();

// Get response in XML format.
$xml_date = $r->get_response();

// Or you can specify the format.
$json_date = $r->get_response(['format'=>'json']);

// Or you want to get just response's body (without XML header).
$raw_response = $r->get_response_body(); // This always will be in XML format!!!
```
Sometimes you would like to build a TADResponse object by hand:
```php
$response = '<Response><Name>Foo</Name><Address>Bar</Address></Response>';
$encoding = 'iso8859-1';

$tr = new TADResponse($response, $encoding);
...
// Maybe later you want to change the response you set before
$new_response = '<CrazyResponse><Joke>foo bar taz</Joke></CrazyResponse>';
$tr->set_response($new_response);
...
```
###Getting and Setting response's encoding
```php
$r = $tad->get_date();

// Get current response's encoding.
$encoding = $r->get_encoding();

// Perhaps you want to change response's encoding.
$r->set_encoding('utf-8');
```

###Getting and Setting response's header
Instead of getting a full XML response, you can get just the header's response and you can change it even:
```php
$r = $tad->get_date();

$header = $r->get_header();
// Method above returns '<?xml version="1.0" encoding="iso8859-1" standalone="no"?>'

$new_header = '<?xml version="1.1" encoding="utf-8" standalone="yes"?>';

// Lets set a new response's header.
$r->set_header($new_header);
```

###Transform device's responses in XML, JSON or Array format.
As you seen above, you can get device's responses in different formats using **get_response() method** and specifying the format you want. However, you can use the following methods too:
```php
// Get attendance logs for all users.
$att_logs = $tad->get_att_logs(); // $att_logs is an TADResponse object.

// Get response in XML format.
$xml_att_logs = $att_logs->to_xml();

// Get response in JSON format.
$json_att_logs = $att_logs->to_json();

// Get an array from response.
$array_att_logs = $att_logs->to_array().

// Lets get an XML response in one single step.
$xml = $tad->get_att_logs()->to_xml();
``` 

###Counting how many items has the response
When you are interested just in how many items has the response, just count them:
```php
$att_logs = $tad->get_att_logs();

// Get just the number of logs you retrived.
$logs_number = $att_logs->count();
```

###Dealing with empty responses
Sometimes some queries to the device returns an empty answer. Because the original response from the device is in XML format, to know if you got any relevant data, you should have to parse the responses. That's not very handy:
```php
$r = $tad->get_att_logs(['pin'=>123]); // This employee does not have any logs!!!

if ($r->is_empty_response()) {
    echo 'The employee does not have logs recorded';
}
...
```

###Filtering response according your needs!!!
As you saw above, all device's responses are handled by **TADResponse class**. You get the raw XML but you always get the whole set. What if you you'd like to do some kind of processing on reponses? Now, you can process the whole XML response by applying filters. This way, you can get just XML responses that really needs.

```php
// Get attendance logs for all users;
$att_logs = $tad->get_att_logs();

// Now, you want filter the resulset to get att logs between '2014-01-10' and '2014-03-20'.
$filtered_att_logs = $att_logs->filter_by_date(
    ['start' => '2014-01-10','end' => '2014-03-20']
);

// Maybe you need to be more specific: get logs of users with privilege of Enroller
// that generated a 'check-out' event after '2014-08-01'.
$filtered_att_logs = $att_logs->filter_by_privilege_and_status_and_date(
    2, // 2 = Enroller role.
    1, // 1 = Check-out.
    ['start'] => '2014-08-01'
);

// You can do specific string searching too!!!
$users = $tad->get_all_user_info();

// Searches for all employees with the string 'Foo' as part of its name.
$foo_employees = $users->filter_by_name(['like'=>'Foo']);
```

Notes:

* The original response is lost! because it is replaced with the filtered response.
* If you do a **filter_by** using a non exists tag, you'll always get an **empty response**.
* When you want to specify specific ranges you have to use an associative array with keys **'start'** (indicates where range begins), and **'end'** (where range ends).
* **greater than** ranges are indicated by passing only **'start'** key.
* **less than** ranges are indicated by passing only **'end'** key.
* To perform searches (filtering) to match just partial strings, you have to use the key **'like'** as you saw in the example above.
* To perform a full match search (filtering) you have to pass the string directly, without use an array.
* To filter by 1 exact value you have to pass just the value (not an array!). However, if by any reason you decide to use an array, both keys have to have the same value.
* If you want to build a very specific filter, you have to use **filter_xml()** method. Using it, you are able to built a customized regex to define how the XML have to be processed.

##Todo
**TADPHP** is not perfect!!!. As mentioned at the beggining, it's been developed after hours, and hours, and hours of googling and it's been tested using just Fingertec Q2i Time & attendance device (that it's I have in my work), so it's possible that you can find errors when you use it with others devices or even you can find better ways to do the things. For that reason, there are some things to do:

* Make TAD-PHP works perfectly on devices with ZEM greater than 600 (with ZEM800 almost everything works as expected, but there are still some bugs).
* Make set_user_template() method works with BioBridge VX 10.0 algorithm.
* Find out how to customize the PIN code generation in the PHP_ZKLib zk_set_user_info() method.
* Test TAD method get_option(). This method allows you getting detailed information about the device, but it's necessary to set its argument to a valid option name. However, these names are not available, and according to documentation ZK Software can give you all options names but you have to pay for them.
* Enhance PHP_ZKLib to allows more sophisticated functions like uploading user's photo for example.

##Author
[Jorge Cobis](<mailto:jcobis@gmail.com>) - <http://twitter.com/cobisja>.

By the way, I'm from Bolivarian Republic of Venezuela :-D

##Contributing
Feel free to contribute!!!. Welcome aboard!!!

##Misc
###Version history
***0.4.2** (Saturday, 17th January 2015)

* Improved directions to get TAD-PHP running.
* Some minor documentation changes.

**0.4.1** (Friday, 16th January 2015)

* Enhanced **TADZKLib class** by adding 14 new methods to get operating information about the device.
* Some minor bug fixes.
* Some fixes in documentation.

**0.4.0** (Sunday, 11th January 2015)

* Wiped out **TADHelpers class**. All its behavior it's been implemented into **TADResponse class**.
* All classes has been refactored according the new behavior associated to **TADResponse class**.
* All test suite has been reviewed and upgraded.
* Changed global **Provider namespace** to get a consistent namespacing schema.
* Some minor bugs fixes.
* Improved **TADZKLib class** documentation.
* Some fixes in documentation.

**0.3.2** (Saturday, 3rd January 2015)

* Implemented a ***dynamic xml filter*** that allows you to build single or multiple filtering criterias based on XML tags of response.
* Refactoring of tests.
* Some bug fixes in documentation.

**0.3.1** (Monday, 29th December 2014)

* Some improvements in README.md.
* Refactored XML response generated by Providers\TADZKLib class.
* Some refactoring in tests to ajust them to the refactored XML response generated by Providers\TADZKLib class.
* Tests DRYed.
* Some bug fixtures.

**0.3.0** (Saturday, 27th December 2014)

* Encoding option added to options set used when you instantiate the TADPHP\TADFactory class. With this options you can customized encoding for both SOAP requests and responses.
* Add a new Test class (RealTADTest) the allows you to run tests against a real Time & Attendance device.
* General refactoring according the new encoding options added.
* Some bug fixes.
* General code formatting to ajust it to PSR-1 and PSR-2 (it is not completed yet!).
* Some improvements in README.md

**0.2.0** (Wednesday, 24th December 2014)

* Some refactoring to make TADPHP\TADSAP, Providers\TADSOAP and Providers\TADZKLib classes simpler.
* TADPHP\TADHelpers refactored to contains just methods related with TADPHP\TAD class responses.
* Some bug fixes in test suite.
* Some improvements in README.md


**0.1.0** (Monday, 22nd December 2014)

* Initial public release.


##License
Copyright (c) 2014 Jorge Cobis (<jcobis@gmail.com>)

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
