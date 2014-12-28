<?php

/*
 * tad-php
 *
 * (The MIT License)
 *
 * Copyright (c) 2014 Jorge Cobis <jcobis@gmail.com / http://twitter.com/cobisja>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * RealTADTest: Tests about real interaction between TAD-PHP classes and
 * ZK Time & Attendance Devices.
 */
namespace Test;

use TADPHP\TAD;
use TADPHP\TADHelpers;
use TADPHP\TADFactory;

class RealTADTest extends \PHPUnit_Framework_TestCase
{
    /**
     * TAD real ip address.
     * @var string
     */
    private $tad_ip;

    public function setUp()
    {
        $this->tad_ip = '192.168.100.156';

        if (!TAD::is_device_online($this->tad_ip)) {
            $this->markTestSkipped("Real TAD tests disabled. Device in {$this->tad_ip} is not online!");
        }
    }

    public function testDeviceIsOnLine()
    {
        $tad_options = [ 'ip'=>$this->tad_ip, 'encoding'=>'iso8859-1' ];
        $tad = (new TADFactory($tad_options))->get_instance();

        $this->assertNotNull($tad);
        $this->assertInstanceOf('TADPHP\TAD', $tad);

        return $tad;
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testGetDate(TAD $tad)
    {
        $date = $tad->get_date();

        $xml_object = new \SimpleXMLElement($date);
        $this->assertNotNull($xml_object->Row->Date);
        $this->assertNotNull($xml_object->Row->Time);
        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string) $xml_object->Row->Date);
        $this->assertRegExp('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/', (string) $xml_object->Row->Time);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testSetDate(TAD $tad)
    {
        $date = '2000-01-01';
        $time = '12:15:30';
        $expected_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><SetDateResponse><Result>1</Result><Information>Succeed!</Information></SetDateResponse>';

        $response = $tad->set_date(['date'=>$date, 'time'=>$time]);
        $this->assertEquals($expected_response, $response);

        $dt = $tad->get_date();
        $xml_object = new \SimpleXMLElement($dt);
        $tad->set_date();

        $this->assertEquals($date, (string)$xml_object->Row->Date);
        $this->assertRegExp('/12:15:[0-9]{2}/', (string)$xml_object->Row->Time);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testGetFreeSizes(TAD $tad)
    {
        $expected_free_sizes_keys = [
            'att_logs_available', 'templates_available', 'att_logs_capacity',
            'templates_capacity', 'passwords_stored', 'admins_stored',
            'att_logs_stored', 'templates_stored', 'users_stored'
        ];

        $expected_free_sizes_items = count($expected_free_sizes_keys);

        $free_sizes = $tad->get_free_sizes();
        $xml_object = new \SimpleXMLElement($free_sizes);

        $this->assertEquals($expected_free_sizes_items, $xml_object->count());

        $free_sizes_array = TADHelpers::xml_to_array($free_sizes);
        $free_sizes_keys = array_keys($free_sizes_array);

        $this->assertTrue($expected_free_sizes_keys === $free_sizes_keys);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testSetUserInfoAndDeleteUser(TAD $tad)
    {
        $user_info = [
            'pin' => 123,
            'name' => 'Foo Bar',
            'password' => 8888,
            'privilege' => 0,
        ];

        $expected_set_user_info_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><SetUserInfoResponse><Row><Result>1</Result><Information>Successfully!</Information></Row></SetUserInfoResponse>';
        $expected_delete_user_info_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><DeleteUserResponse><Row><Result>1</Result><Information>Successfully!</Information></Row></DeleteUserResponse>';

        $fs = $tad->get_free_sizes();
        $xml_object = new \SimpleXMLElement($fs);
        $total_users_before = (integer) $xml_object->users_stored;

        $set_user_info_response = $tad->set_user_info($user_info);

        $fs = $tad->get_free_sizes();
        $xml_object = new \SimpleXMLElement($fs);
        $total_users_after = (integer) $xml_object->users_stored;

        $delete_user_response = $tad->delete_user([ 'pin' => $user_info['pin'] ]);

        $this->assertEquals($expected_set_user_info_response, $set_user_info_response);
        $this->assertEquals($expected_delete_user_info_response, $delete_user_response);
        $this->assertTrue($total_users_after === $total_users_before + 1);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testGetUserInfo(TAD $tad)
    {
        $user_info = [
            'pin' => 123,
            'name' => 'Foo Bar',
            'password' => 8888,
            'privilege' => 0,
        ];

        $tad->set_user_info($user_info);

        $response = $tad->get_user_info(['pin'=>123]);

        $user_info_response = TADHelpers::xml_to_array($response)['Row'];
        $tad->delete_user(['pin'=>123]);

        $this->assertEquals($user_info_response['PIN2'], $user_info['pin']);
        $this->assertEquals($user_info_response['Name'], $user_info['name']);
        $this->assertEquals($user_info_response['Password'], $user_info['password']);
        $this->assertEquals($user_info_response['Privilege'], $user_info['privilege']);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testGetAllUserInfo(TAD $tad)
    {
        $fs = $tad->get_free_sizes();
        $xml_object = new \SimpleXMLElement($fs);
        $total_users = (integer) $xml_object->users_stored;

        $all_user_info_response = $tad->get_all_user_info();

        $all_user_info_items = count(TADHelpers::xml_to_array($all_user_info_response)['Row']);

        $this->assertEquals($total_users, $all_user_info_items);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testDeleteUserPassword(TAD $tad)
    {
        $user_info = [
            'pin' => 123,
            'name' => 'Foo Bar',
            'password' => 8888,
            'privilege' => 0,
        ];

        $expected_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><ClearUserPasswordResponse><Row><Result>1</Result><Information>Successfully!</Information></Row></ClearUserPasswordResponse>';

        $tad->set_user_info($user_info);

        $before_password = TADHelpers::xml_to_array($tad->get_user_info(['pin'=>$user_info['pin']]))['Row']['Password'];
        $response = $tad->delete_user_password(['pin'=>$user_info['pin']]);

        $after_password = TADHelpers::xml_to_array($tad->get_user_info(['pin'=>$user_info['pin']]))['Row']['Password'];
        $tad->delete_user(['pin'=>$user_info['pin']]);

        $this->assertEquals($expected_response, $response);
        $this->assertNotEquals($after_password, $before_password);
        $this->assertEmpty($after_password);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testGetAndSetAndDeleteUserTemplate(TAD $tad)
    {
        $user_info = [
            'pin' => 123,
            'name' => 'Foo Bar',
            'password' => 8888,
            'privilege' => 0,
        ];

        $template1_vx9 = "ocosgoulTUEdNKVRwRQ0I27BDTEkdMEONK9KQQunMVSBK6VPLEENk9MwgQ+DP3PBC1FTXEEG4ihpQQQ3vFQBO4K+WwERYilHAQ8ztktBEBbKQ0ELDtJrwQ7dqCiBCz+/IgEGKrBjQQhEO0zBFQNDQYEKFbhrQQdLF1wBDxclfUELMNFXwQRvvmHBCslKUAEZfU1OQRzmIU5BXRW0eoEKPMltgQnQGUyBJQSfRIEUSzIdAQ45l3gBByHUTMEJ5yVhQQmi0UZBFHvYPUEGeKxTAQ6rFGNBCIYURoEOZS9VwR+1M4RoE5m0DRUTF8DHd6HdqxHAxWmj393M28DDX2FkanKi/t7LGsDCWqGarmt1BaL/25nAwVaiipu/cgcQGKG6mcDBU6KYmr5wChQcobmJIsDBUKKJmZ1uExyi+ZaYwMFMgU2CQCSinYdnJsDBR4Ghl3Q4owa3dnfAwUamdlZlR5p2Zi7AwUSndERlfOpWZlfAwUOiQzVkLDhDopRUVTLAwT2iQ0ZjIzVMolNFRcDBN6I0ZlQebVaiEjRVwMEyolVVUxVxXKEBRUTAwS+iZVYyD3JhoQJFTMDBLKJlVUIKcWShBVVTwMIkoWVkFQhyaaEVZ1rAwh6hVlUPAW+iNGd3wMIToWdlBnWiRWZ3aMDDCqRmZjRpZmrAxASjd2Vnh2/gAA==";
        $template1_data = [
          'pin' => $user_info['pin'],
          'finger_id' => 0,
          'size' => 514,
          'valid' => 1,
          'template' => $template1_vx9
        ];

        $no_template_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetUserTemplateResponse><Row><Result>1</Result><Information>No data!</Information></Row></GetUserTemplateResponse>';
        $expected_set_user_template_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><SetUserTemplateResponse><Row><Result>1</Result><Information>Successfully!</Information></Row></SetUserTemplateResponse>';
        $expected_delete_template_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><DeleteTemplateResponse><Row><Result>1</Result><Information>Successfully!</Information></Row></DeleteTemplateResponse>';

        // Create a test user.
        $tad->set_user_info($user_info);

        // GetUserTemplate test section.
        $before_set_template = $tad->get_user_template(['pin'=>$user_info['pin']]);
        $this->assertEquals($no_template_response, $before_set_template);

        // SetUerTemplate test section.
        $response = $tad->set_user_template($template1_data);
        $this->assertEquals($expected_set_user_template_response, $response);

        $after_set_template = $tad->get_user_template(['pin'=>$user_info['pin']]);
        $raw_after_template = TADHelpers::xml_to_array($after_set_template)['Row']['Template'];

        $this->assertNotEmpty($after_set_template);
        $this->assertEquals($template1_vx9, $raw_after_template);

        // DeleteTemplate test section.
        $response = $tad->delete_template(['pin'=>$user_info['pin']]);
        $this->assertEquals($expected_delete_template_response, $response);

        $after_delete_template = $tad->get_user_template(['pin'=>$user_info['pin']]);
        $this->assertEquals($no_template_response, $after_delete_template);

        // Delete the test user created above.
        $tad->delete_user(['pin'=>$user_info['pin']]);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testDeleteAdmin(TAD $tad)
    {
        $user_info = [
            'pin' => 123,
            'name' => 'Foo Bar',
            'password' => 8888,
            'privilege' => 14, // Superadmin.
        ];

        $expected_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><DeleteAdminResponse><Result>1</Result><Information>Succeed!</Information></DeleteAdminResponse>';

        $tad->set_user_info($user_info);

        $response = $tad->delete_admin();

        $fs = $tad->get_free_sizes();
        $total_admins = (integer) TADHelpers::xml_to_array($fs)['admins_stored'];
        $tad->delete_user(['pin'=>$user_info['pin']]);

        $this->assertEquals($expected_response, $response);
        $this->assertTrue(0 === $total_admins);
    }

    /**
     * @depends testDeviceIsOnLine
     */
    public function testRestartDevice(TAD $tad)
    {
        $expected_response = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><RestartResponse><Result>1</Result><Information>Succeed!</Information></RestartResponse>';
        $device_ip = $tad->get_ip();

        $response = $tad->restart();

        sleep(2); // Let give it a few seconds to test if it's online.
        $is_device_online = TAD::is_device_online($device_ip);

        $this->assertEquals($expected_response, $response);
        $this->assertNotTrue($is_device_online);
    }
}
