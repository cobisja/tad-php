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

namespace TADPHP\Providers;

use TADPHP\TADResponse;

/**
 * TADZKlib: class that allows to interact with a Time & Attendance device using UDP protocol.
 *
 * This is a modified class of PHP_ZKLib (@link http://dnaextrim.github.io/php_zklib/ )
 *
 * This class has been modified by refactoring most of methods, taking out all duplicated code. The
 * original behavior it's been kept.
 */
class TADZKLib
{
    const USHRT_MAX = 65535;
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLEDEVICE = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_RESTART = 1004;
    const CMD_POWEROFF = 1005;
    const CMD_ACK_OK = 2000;
    const CMD_ACK_ERROR = 2001;
    const CMD_ACK_DATA = 2002;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1501;
    const CMD_USERTEMP_RRQ = 9;
    const CMD_ATTLOG_RRQ = 13;
    const CMD_CLEAR_DATA = 14;
    const CMD_CLEAR_ATTLOG = 15;
    const CMD_WRITE_LCD = 66;
    const CMD_GET_TIME = 201;
    const CMD_SET_TIME = 202;
    const CMD_VERSION = 1100;
    const CMD_AUTH = 1102;
    const CMD_DEVICE = 11;
    const CMD_CLEAR_ADMIN = 20;
    const CMD_SET_USER = 8;
    const CMD_GET_FREE_SIZES = 50;

    const EMPTY_STRING = '';
    const CUSTOMIZED_COMMAND_STRING = null;

    const DEVICE_GENERAL_INFO_STRING_LENGTH = 184;

    const XML_FAIL_RESPONSE    = 'Fail!';
    const XML_SUCCESS_RESPONSE = 'Successfully!';

    /**
     * @var string Device's ip address.
     */
    private $ip;

    /**
     * @var int Device's UDP port.
     */
    private $port;

    /**
     * @var TADZKlib holds a class instance.
     */
    private $zkclient;

    /**
     * @var string device's response (low level format).
     */
    private $data_recv = '';

    /**
     * @var int session id associated to UDP transaction.
     */
    private $session_id = 0;

    /**
     * @var boolean tells if result was successfully (<code>true</code>) or fail (<code>false</code>).
     */
    private $result;

    /**
     * @var array commands set supported by <code>TADZKLib</code> class.
     */
    static private $zklib_commands = [
        'get_platform' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~Platform',
            'should_disconnect' => true,
            'result_filter_string'=>'~Platform='
        ],
        'get_fingerprint_algorithm' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~ZKFPVersion',
            'should_disconnect' => true,
            'result_filter_string'=>'~ZKFPVersion='
        ],
        'get_serial_number' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~SerialNumber',
            'should_disconnect' => true,
            'result_filter_string'=>'~SerialNumber='
        ],
        'get_oem_vendor' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~OEMVendor',
            'should_disconnect' => true,
            'result_filter_string'=>'~OEMVendor='
        ],
        'get_mac_address' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => 'MAC',
            'should_disconnect' => true,
            'result_filter_string'=>'MAC='
        ],
        'get_device_name' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~DeviceName',
            'should_disconnect' => true,
            'result_filter_string'=>'~DeviceName='
        ],
        'get_manufacture_time' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~ProductTime',
            'should_disconnect' => true,
            'result_filter_string'=>'~ProductTime='
        ],
        'get_antipassback_mode' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~APBFO',
            'should_disconnect' => true,
            'result_filter_string'=>'~APBFO='
        ],
        'get_workcode' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~WCFO',
            'should_disconnect' => true,
            'result_filter_string'=>'~WCFO='
        ],
        'get_ext_format_mode' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~ExtendFmt',
            'should_disconnect' => true,
            'result_filter_string'=>'~ExtendFmt='
        ],
        'get_encrypted_mode' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => 'encrypt_out',
            'should_disconnect' => true,
            'result_filter_string'=>'encrypt_out='
        ],
        'get_pin2_width' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~PIN2Width',
            'should_disconnect' => true,
            'result_filter_string'=>'~PIN2Width='
        ],
        'get_ssr_mode' => [
            'command_id' => self::CMD_DEVICE,
            'command_string' => '~SSR',
            'should_disconnect' => true,
            'result_filter_string'=>'~SSR='
        ],
        'get_firmware_version' => [
            'command_id' => self::CMD_VERSION,
            'command_string' => self::EMPTY_STRING,
            'should_disconnect' => true,
            'result_filter_string'=>false
        ],
        'get_free_sizes' => [
            'command_id' => self::CMD_GET_FREE_SIZES,
            'command_string' => self::EMPTY_STRING,
            'should_disconnect' => true,
            'result_filter_string'=>false
        ],
        'set_date' => [
            'command_id' => self::CMD_SET_TIME,
            'command_string' => self::CUSTOMIZED_COMMAND_STRING,
            'should_disconnect' => true,
            'result_filter_string'=>false
        ],
        'delete_admin' => [
            'command_id' => self::CMD_CLEAR_ADMIN,
            'command_string' => self::EMPTY_STRING,
            'should_disconnect' => true,
            'result_filter_string'=>false
        ],
        'enable' => [
            'command_id' => self::CMD_ENABLEDEVICE,
            'command_string' => self::EMPTY_STRING,
            'should_disconnect' => true,
            'result_filter_string'=>false
        ],
        'disable' => [
            'command_id' => self::CMD_DISABLEDEVICE,
            'command_string' => self::EMPTY_STRING,
            'should_disconnect' => false,
            'result_filter_string'=>false
        ],
        'restart' => [
            'command_id' => self::CMD_RESTART,
            'command_string' => self::EMPTY_STRING,
            'should_disconnect' => true,
            'result_filter_string'=>false
        ],
        'poweroff' => [
            'command_id' => self::CMD_POWEROFF,
            'command_string' => self::EMPTY_STRING,
            'should_disconnect' => true,
            'result_filter_string'=>false
        ]
    ];

    /**
     * Returns commands available by the class.
     *
     * @return array commands list.
     */
    static public function get_commands_available()
    {
        return array_keys(self::$zklib_commands);
    }

    /**
     * Iniatialize TADZKLib class and sets its attributes.
     *
     * @param array $options options (ip, udp port and connection timeout).
     */
    public function __construct(array $options)
    {
        $this->ip = $options['ip'];
        $this->port = $options['udp_port'];

        $this->zkclient = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        $timeout = ['sec' => $options['connection_timeout'], 'usec' => 500000];
        socket_set_option($this->zkclient, SOL_SOCKET, SO_RCVTIMEO, $timeout);
    }

    /**
     * Magic call to implement dynamic method calling.
     *
     * @param string $command method invoked.
     * @param array $args arguments passed.
     * @return TADResponse
     */
    public function __call($command, array $args)
    {
        $should_disconnect = true;
        $args = count($args) === 0 ? [] : array_shift($args);
        $encoding = $args['encoding'];
        unset($args['encoding']);

        $this->connect();

        switch($command){
            case 'set_date':
                $response = $this->zk_set_date($args);
                break;

            case 'get_free_sizes':
                $response = $this->zk_get_free_sizes();
                break;

            default:
                $should_disconnect = self::$zklib_commands[$command]['should_disconnect'];

                $response = $this->send_command_to_device(
                    self::$zklib_commands[$command]['command_id'],
                    self::$zklib_commands[$command]['command_string']
                );
        }

        $result_filter_string = self::$zklib_commands[$command]['result_filter_string'];
        $response = $this->build_command_response($command, $this->result, $response, $encoding, $result_filter_string);
        $should_disconnect && $this->disconnect();

        return new TADResponse($response, $encoding);
    }

    /**
     * Establish a connection to the device.
     *
     * @return boolean <b><code>true</code></b> on successfully conection, otherwise returns <b><code>false</code></b>.
     */
    private function connect()
    {
        $command = self::CMD_CONNECT;
        $command_string = self::EMPTY_STRING;
        $chksum = 0;
        $session_id = 0;
        $reply_id = -1 + self::USHRT_MAX;

        $buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        socket_sendto($this->zkclient, $buf, strlen($buf), 0, $this->ip, $this->port);

        try {
            socket_recvfrom($this->zkclient, $this->data_recv, 1024, 0, $this->ip, $this->port);
            if (strlen($this->data_recv) > 0) {
                $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($this->data_recv, 0, 8));

                $this->session_id =  hexdec($u['h6'].$u['h5']);
                return $this->checkValid($this->data_recv);
            } else {
                return false;
            }
        } catch (ErrorException $e) {
            return false;
        } catch (exception $e) {
            return false;
        }
    }

    /**
     * Disconnects from the device.
     *
     * @return boolean <b><code>true</code></b> on successfully, otherwise returns <b><code>false</code></b>.
     */
    private function disconnect()
    {
        $command = self::CMD_EXIT;
        $command_string = '';
        $chksum = 0;
        $session_id = $this->session_id;

        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->data_recv, 0, 8));
        $reply_id = hexdec($u['h8'].$u['h7']);

        $buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        socket_sendto($this->zkclient, $buf, strlen($buf), 0, $this->ip, $this->port);
        try {
            socket_recvfrom($this->zkclient, $this->data_recv, 1024, 0, $this->ip, $this->port);

            return $this->checkValid($this->data_recv);
        } catch (ErrorException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Sets device's time and date.
     *
     * @param array $dt date and time data.
     * @return boolean <b><code>true</code></b> on successfully, otherwise returns <b><code>false</code></b>.
     */
    private function zk_set_date(array $dt = [])
    {
        $normalized_datetime = $this->setup_datetime($dt);
        $encoded_time = $this->encode_time($normalized_datetime);
        return $this->send_command_to_device(self::CMD_SET_TIME, pack('I', $encoded_time));
    }

    /**
     * Gets device's information about current device's storage.
     *
     * @return array device's storage information.
     */
    private function zk_get_free_sizes()
    {
        $fs = [];
        $free_sizes_info = $this->reverse_hex(bin2hex($this->send_command_to_device(self::CMD_GET_FREE_SIZES)));

        if (!$free_sizes_info) {
            $fs = false;
        } else {
            if (self::DEVICE_GENERAL_INFO_STRING_LENGTH > strlen($free_sizes_info)) {
                $free_sizes_info = '000000000000000000000000' . $free_sizes_info;
            }

            $fs['att_logs_available']  = hexdec(substr($free_sizes_info, 27, 5));
            $fs['templates_available'] = hexdec(substr($free_sizes_info, 44, 4));
            $fs['att_logs_capacity']   = hexdec(substr($free_sizes_info, 51, 5));
            $fs['templates_capacity']  = hexdec(substr($free_sizes_info, 60, 4));
            $fs['passwords_stored']    = hexdec(substr($free_sizes_info, 76, 4));
            $fs['admins_stored']       = hexdec(substr($free_sizes_info, 84, 4));
            $fs['att_logs_stored']     = hexdec(substr($free_sizes_info, 116, 4));
            $fs['templates_stored']    = hexdec(substr($free_sizes_info, 132, 4));
            $fs['users_stored']        = hexdec(substr($free_sizes_info, 148, 4));
        }

        return $fs;
    }

    /**
     * Helper that allows sending command to device.
     *
     * @param integer $command command code.
     * @param string $command_string subcommand.
     * @param int $reply_id device's reply.
     * @return boolean <b><code>true</code></b> on successfully, otherwise returns <b><code>false</code></b>.
     */
    private function send_command_to_device($command, $command_string = '', $reply_id =null)
    {
        $chksum = 0;
        $session_id = $this->session_id;

        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->data_recv, 0, 8));

        if (is_null($reply_id)) {
            $reply_id = hexdec($u['h8'].$u['h7']);
        }

        $buf = $this->createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        socket_sendto($this->zkclient, $buf, strlen($buf), 0, $this->ip, $this->port);

        try {
            socket_recvfrom($this->zkclient, $this->data_recv, 1024, 0, $this->ip, $this->port);
            $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($this->data_recv, 0, 8));
            $this->session_id =  hexdec($u['h6'].$u['h5']);

            $this->result = $this->checkValid($this->data_recv);

            return substr($this->data_recv, 8);
        } catch (ErrorException $e) {
            return false;
        } catch (exception $e) {
            return false;
        }
    }

    /**
     * Calculates the chksum of the packet to be sent to the device.
     *
     * @param string $p packed sent to the device.
     * @return string checksum calculated.
     */
    private function createChkSum($p)
    {
        /*This function

        Copied from zkemsdk.c*/

        $l = count($p);
        $chksum = 0;
        $i = $l;
        $j = 1;
        while ($i > 1) {
            $u = unpack('S', pack('C2', $p['c'.$j], $p['c'.($j+1)]));

            $chksum += $u[1];

            if ($chksum > self::USHRT_MAX) {
                $chksum -= self::USHRT_MAX;
            }

            $i-=2;
            $j+=2;
        }

        if ($i) {
            $chksum = $chksum + $p['c'.strval(count($p))];
        }

        while ($chksum > self::USHRT_MAX) {
            $chksum -= self::USHRT_MAX;
        }

        if ($chksum > 0) {
            $chksum = -($chksum);
        } else {
            $chksum = abs($chksum);
        }

        $chksum -= 1;
        while ($chksum < 0) {
            $chksum += self::USHRT_MAX;
        }

        return pack('S', $chksum);
    }

    /**
     *  Creates UDP header to be sent to the device.
     *
     * @param int $command command id.
     * @param string $chksum checksum associated.
     * @param int $session_id session id associated.
     * @param int $reply_id reply id associated.
     * @param string $command_string subcomand.
     * @return string UDP header.
     */
    private function createHeader($command, $chksum, $session_id, $reply_id, $command_string)
    {
        /*This function puts a the parts that make up a packet together and
        packs them into a byte string*/
        $buf = pack('SSSS', $command, $chksum, $session_id, $reply_id).$command_string;

        $buf = unpack('C'.(8+strlen($command_string)).'c', $buf);

        $u = unpack('S', $this->createChkSum($buf));

        if (is_array($u)) {
            while (list($key) = each($u)) {
                $u = $u[$key];
                break;
            }
        }

        $chksum = $u;
        $reply_id += 1;

        if ($reply_id >= self::USHRT_MAX) {
            $reply_id -= self::USHRT_MAX;
        }

        $buf = pack('SSSS', $command, $chksum, $session_id, $reply_id);

        return $buf.$command_string;
    }

    /**
     * Checks a returned packet to see if it returned CMD_ACK_OK, indicating success.
     *
     * @param string $reply packet received from the device.
     *
     * @return boolean <b><code>true</code></b> on valid packet, otherwise returns <b><code>false</code></b>.
     */
    private function checkValid($reply)
    {
        $u = unpack('H2h1/H2h2', substr($reply, 0, 8));

        $command = hexdec($u['h2'].$u['h1']);

        if ($command == self::CMD_ACK_OK) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Builds a command response with a XML format to keep TAD behavior.
     *
     * @param string $command command executed.
     * @param mixed $result command result.
     * @return string XML response.
     */
    private function build_command_response($command, $result_code, $result, $encoding, $result_filter_string=false)
    {
        $response_data = [];

        $xml_tag = str_replace('_', ' ', $command);
        $base_xml_tag = join('', explode(' ', ucwords($xml_tag))) . 'Response';

        if (is_array($result)) {
            if (0 === count($result)) {
                $xml_header = '';
                $response = $xml_header . '<' . $base_xml_tag . '>' . '</' . $base_xml_tag . '>';
                return $response;
            }
            $response_data = ['Row'=>$result];
        } else {
            if (!is_bool($result) && true === $result_code) {
                $result_filter_string = $result_filter_string ? $result_filter_string : null;

                $result_data = str_replace($result_filter_string, '', $result);
            } else {
                $result_data = ($result_code ? self::XML_SUCCESS_RESPONSE : self::XML_FAIL_RESPONSE);
            }

            $result_code = $result_code ? '1' : '0';
            $response_data = ['Row'=>['Result'=> $result_code, 'Information'=> $result_data]];
        }

        return $this->array_to_xml(new \SimpleXMLElement('<' . $base_xml_tag . '/>'), $response_data, $encoding);
    }

    /**
     * Take an array in the form of <code>['date'=>date_value, 'time'=>time_value]</code> y returns
     * another array with the following form:
     *
     * <code>
     * ['year'=>foo_year, 'month'=>bar_month, 'day'=>taz_day,
     *  'hour'=>foo_hour, 'minute=>bar_minute, 'second'=>taz_minute]
     * </code>
     *
     * Any missing item from input array is replaced by corresponding element generated from
     * current date and time.
     *
     * @param array $dt input 'datetime' array.
     * @return array array generated.
     */
    private function setup_datetime(array $dt=[])
    {
        $now = explode(' ', date("Y-m-d H:i:s"));
        $dt = array_filter($dt, 'strlen');

        !isset($dt['date']) ? $dt['date'] = $now[0] : null;
        !isset($dt['time']) ? $dt['time'] = $now[1] : null;

        $date = explode('-', $dt['date']);
        $time = explode(':', $dt['time']);

        return [
            'year'=>$date[0], 'month'=>$date[1], 'day'=>$date[2],
            'hour'=>$time[0], 'minute'=>$time[1], 'second'=>$time[2]];
    }

    /**
     * Method taken from PHPLib @link http://dnaextrim.github.io/php_zklib/ project.
     *
     * @param string $hexstr hex string.
     * @return string hex string reversed.
     */
    private function reverse_hex($hexstr)
    {
        $tmp = '';

        for ($i=strlen($hexstr); $i>=0; $i--) {
            $tmp .= substr($hexstr, $i, 2);
            $i--;
        }

        return $tmp;
    }

    /**
     * Method taken from PHPZKLib @link http://dnaextrim.github.io/php_zklib/ project.
     *
     * It's been modified to accept an associative array as input.
     *
     * @param array $t array with a timestamp data.
     * @return int timestamp encoded.
     */
    private function encode_time(array $t)
    {
        /*Encode a timestamp send at the timeclock

        copied from zkemsdk.c - EncodeTime*/
        $d = ( ($t['year'] % 100) * 12 * 31 + (($t['month'] - 1) * 31) + $t['day'] - 1) *
             (24 * 60 * 60) + ($t['hour'] * 60 + $t['minute']) * 60 + $t['second'];

        return $d;
    }

    /**
     * Transforms an array into an XML string.
     *
     * @param \SimpleXMLElement $object <code>SimpleXMLElement</code> instance.
     * @param array $data input array to be transformed.
     * @return string XML string generated.
     */
    private function array_to_xml(\SimpleXMLElement $object, array $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $new_object = $object->addChild($key);
                $this->array_to_xml($new_object, $value);
            } else {
                $object->addChild($key, $value);
            }
        }

        $xml = trim(str_replace("<?xml version=\"1.0\"?>", '', $object->asXML()));

        return $xml;
    }
}
