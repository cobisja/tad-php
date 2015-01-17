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

namespace TADPHP;

use TADPHP\Providers\TADSoap;
use TADPHP\Providers\TADZKLib;
use TADPHP\Exceptions\ConnectionError;
use TADPHP\Exceptions\UnrecognizedArgument;
use TADPHP\Exceptions\UnrecognizedCommand;

/**
 * TAD: Time & Attendance Device is a class that implements some function presents in time and attendance device.
 *
 *
 * For developing purposes, it's been used Fingertec Q2i device (that it's been using were I work).
 *
 * Methods exposed by TAD class have been tested using Q2i deviced only , that has a Linux 2.6.21 kernel (ZEM-600).
 * However, it's possible that TAD class works with similar devices, since most of them use the same SOAP API from
 * ZK Software.
 *
 * There are some SOAP functions that it's suppossed,  according to the official docs (which incidentally it's
 * very limited and so poor!!!) must show a expected behaviour, but when they are invoked don't work like
 * it's expected, so they become useless (e.g. Restart SOAP call). For these situations, I found (after googling for
 * hours, and hours, and hours!!!), a PHP class named PHP_ZKLib (@link http://dnaextrim.github.io/php_zklib/ ) that
 * take a different approach to "talk to device": it uses UDP protocol at device standard port 4370.
 *
 * PHP_ZKLib class it's been fully integrated, after a refactoring process to take out all duplicated code (DRY)
 *
 * TAD Class has been tested as far as I could. If you check the test code coverage you'll notice that it does not
 * reach 100%. The reason is that it was not possible to write some tests fof PHP_ZKLib class. I have to admit that
 * I not have fully understanding about what it's done by some methods. I could not find technical information
 * about UDP protocol for ZK devices.
 *
 * For practical purposes, you don't have to be worried about when to use TAD class or PHP_ZKLib class because
 * you only have to get a TAD instance (as shown below) and call any of methods available. The class decides
 * about when run the method invoked using TAD class or PHP_ZKLib class.
 *
 * To get a TAD instance, call <code><b>get_instance()</b></code> method from <code><b>TADFactory</b></code> class.
 *
 * Please note that all device's responses are handled by TAD through <b><code>TADResponse</code></b> class. For
 * that reason all responses are returned embedded in <code>TADResponse</code> object.
 *
 * You can get responses in XML, JSON or Array using the respective methods exposed by <code>TADResponse</code> class.
 * (<code>to_xml(), to_json(), to_array(), get_reponse()</code>)
 *
 * Some examples:
 *
 * - Get a TAD instance for device with ip = '192.168.100.156':
 * <code>
 * $b1 = (new TADFactory(['ip'=>'192.168.100.156']))->get_instance();
 * </code>
 *
 * Getting device time and date:
 * <code>
 * $dt = $b1->get_date(); // method executed via TAD class.
 * </code>
 *
 * Setting device date and time:
 * <code>
 * $r = $b1->set_date(['date'=>'2014-12-31', 'time'=>'23:59:59']); // method executed via PHP_ZKLib.
 * </code>
 *
 * All device responses are <code>TADResponse</code> objects. You can transform them as shown below:
 *
 * Get an array with logs of user with pin = 99999999:
 * <code>
 * $logs = $b1->get_att_log(['pin'=>'99999999'])->to_array();
 * </code>
 *
 * If you want to filter logs by date:
 * <code>
 * $logs = $b1->get_att_log(['pin'=>'99999999']);
 * $logs = $logs->filter_by_date(['start_date'=>'2014-11-27', 'end_date'=>'2014-12-02']);
 * </code>
 *
 * For more information see README.md
 *
 * @author Jorge Cobis - email: jcobis@gmail.com / twitter: @cobisja
 */
class TAD
{
    /**
     * Valid commands args array.
     *
     * @var array
     */
    static private $parseable_args = [
        'com_key', 'pin', 'time', 'template',
        'name', 'password', 'group', 'privilege',
        'card', 'pin2', 'tz1', 'tz2', 'tz3',
        'finger_id', 'option_name', 'date',
        'size', 'valid', 'value'
    ];

    /**
     * @var string Device ip address.
     */
    private $ip;

    /**
     * @var mixed Device internal id.
     */
    private $internal_id;

    /**
     * @var string Device description (just for info purposes).
     */
    private $description;

    /**
     * Security communication code (required for SOAP functions calls).
     *
     * @var mixed
     */
    private $com_key;

    /**
     * @var int Connection timeout in seconds.
     */
    private $connection_timeout;

    /**
     * @var string Encoding for XML commands and responses.
     */
    private $encoding;

    /**
     * @var int UDP port number.
     */
    private $udp_port;

    /**
     * Holds a <code>TADSoap</code> instance to talk to device via SOAP.
     *
     * @var object
     */
    private $tad_soap;

    /**
     * Holds <code>PHP_ZKLib</code> instance to talk to device via UDP.
     *
     * @var object
     */
    private $zklib;


    /**
     * Returns an array with a full list of commands available.
     *
     * @return array list of commands available.
     */
    public static function commands_available()
    {
        return array_merge(static::soap_commands_available(), static::zklib_commands_available());
    }

    /**
     * Returns an array with SOAP commands list available.
     *
     * @return array SOAP commands list.
     */
    public static function soap_commands_available(array $options = [])
    {
        return TADSoap::get_commands_available($options);
    }

    /**
     * Returns an array with PHP_ZKLib commands available.
     *
     * @return array PHP_ZHLib commands list.
     */
    public static function zklib_commands_available()
    {
        return TADZKLib::get_commands_available();
    }

    /**
     * Returns valid commands arguments list.
     *
     * @return array arguments list.
     */
    public static function get_valid_commands_args()
    {
        return self::$parseable_args;
    }

    /**
     * Tells if device is "online" to process commands requests.
     *
     * @param string $ip device ip address
     * @param int $timeout seconds to wait for device.
     * @return boolean <b>true</b> if device is alive, <b>false</b> otherwise.
     */
    public static function is_device_online($ip, $timeout = 1)
    {
        $handler = curl_init($ip);
        curl_setopt_array($handler, [ CURLOPT_TIMEOUT => $timeout, CURLOPT_RETURNTRANSFER => true ]);
        $response = curl_exec($handler);
        curl_close($handler);

        return (boolean)$response;
    }

    /**
     * Get a new TAD class instance.
     *
     * @param TADSoap $soap_provider code><b>TADSoap</b></code> class instance.
     * @param TADZKLib $zklib_provider <code><b>ZKLib</b></code> class instance.
     * @param array $options device parameters.
     */
    public function __construct(TADSoap $soap_provider, TADZKLib $zklib_provider, array $options = [])
    {
        $this->ip = $options['ip'];
        $this->internal_id = (integer) $options['internal_id'];
        $this->com_key = (integer) $options['com_key'];
        $this->description = $options['description'];
        $this->connection_timeout = (integer) $options['connection_timeout'];
        $this->encoding = strtolower($options['encoding']);
        $this->udp_port = (integer) $options['udp_port'];

        $this->tad_soap = $soap_provider;
        $this->zklib = $zklib_provider;
    }

    /**
     * Magic __call method overriding to define in runtime the methods should be called based on method invoked.
     * (Something like Ruby metaprogramming :-P). In this way, we decrease the number of methods required
     * (usually should be one method per SOAP or PHP_ZKLib command exposed).
     *
     * Note:
     *
     * Those methods that add, update o delete device information, call SOAP method <b><code>refresh_db()</code></b>
     * to properly update device database.
     *
     * @param string $command command to be invoked.
     * @param array $args commands args.
     * @return string device response in XML format.
     * @throws ConnectionError.
     * @throws UnrecognizedCommand.
     * @throws UnrecognizedArgument.
     */
    public function __call($command, array $args)
    {
        $command_args = count($args) === 0 ? [] : array_shift($args);

        $this->check_for_connection() &&
        $this->check_for_valid_command($command) &&
        $this->check_for_unrecognized_args($command_args);

        if (in_array($command, TADSoap::get_commands_available())) {
            $response = $this->execute_command_via_tad_soap($command, $command_args);
        } else {
            $response = $this->execute_command_via_zklib($command, $command_args);
        }

        $this->check_for_refresh_tad_db($command);

        return $response;
    }

    /**
     * Send a command to device using a <code>TADSoap</code> instance class.
     *
     * @param string $command command to be sending.
     * @param array $args command args.
     * @return string device response.
     */
    public function execute_command_via_tad_soap($command, array $args = [])
    {
        $command_args = $this->config_array_items(array_merge(['com_key' => $this->get_com_key()], $args));

        return $this->tad_soap->execute_soap_command($command, $command_args, $this->encoding);
    }

    /**
     * Send a command to device using <code>PHP_ZKLib</code> class.
     *
     * All responses generate by PHP_ZKLib class are not in XML format, it is used <code>build_command_response</code>
     * to build an XML response, just to keep the TAD class behavior. For this purpose, the method uses class constans
     * <code>ZKLib::XML_SUCCESS_RESPONSE</code> and <code>ZKLib::XML_FAIL_RESPONSE</code>.
     *
     * @param string $command command to be sending.
     * @param array $args command args.
     * @return string string device response.
     */
    public function execute_command_via_zklib($command, array $args = [])
    {
        $command_args = $this->config_array_items($args);
        $response = $this->zklib->{$command}(array_merge(['encoding'=>$this->encoding], $command_args));

        return $response;
    }

    /**
     * Returns device's IP address.
     *
     * @return string IP address.
     */
    public function get_ip()
    {
        return $this->ip;
    }

    /**
     * Returns device's internal code.
     *
     * @return int internal code.
     */
    public function get_internal_id()
    {
        return $this->internal_id;
    }

    /**
     * Returns device's comm code.
     *
     * @return int code.
     */
    public function get_com_key()
    {
        return $this->com_key;
    }

    /**
     * Returns device's string description.
     *
     * @return string device description.
     */
    public function get_description()
    {
        return $this->description;
    }

    /**
     * Returns device's connection timeout.
     *
     * @return int connection timeout.
     */
    public function get_connection_timeout()
    {
        return $this->connection_timeout;
    }

    /**
     * Returns device's encoding (used for SOAP requests and responses).
     *
     * @return string encoding.
     */
    public function get_encoding()
    {
        return $this->encoding;
    }

    /**
     * Return device's UDP port number.
     *
     * @return int port number.
     */
    public function get_udp_port()
    {
        return $this->udp_port;
    }

    /**
     * Tells if device is ready (alive) to process requests.
     *
     * @return boolean <b>true</b> if device is alive, <b>false</b> otherwise.
     */
    public function is_alive()
    {
        return static::is_device_online($this->get_ip(), $this->connection_timeout);
    }

    /**
     * Throws an Exception when device is not alive.
     *
     * @return boolean <b><code>true</code></b> if there is a connection with the device.
     * @throws ConnectionError
     */
    private function check_for_connection()
    {
        if (!$this->is_alive()) {
            throw new ConnectionError('Imposible iniciar conexión con dispositivo ' . $this->get_ip());
        }

        return true;
    }

    /**
     * Tells if the command requested is in valid commands set.
     *
     * @param string $command command requested.
     * @return boolean <code><b>true</b></code> if the command es known by the class.
     * @throws UnrecognizedCommand
     */
    private function check_for_valid_command($command)
    {
        $tad_commands = static::commands_available();

        if (!in_array($command, $tad_commands)) {
            throw new UnrecognizedCommand("Comando $command no reconocido!");
        }

        return true;
    }

    /**
     * Tells if the arguments supplied are in valid args set.
     *
     * @param array $args args array to be verified.
     * @return <b><code>true</code></b> if all args supplied are valid (known by the class).
     * @throws TAD\Exceptions\UnrecognizedArgument
     */
    private function check_for_unrecognized_args(array $args)
    {
        if (0 !== count($unrecognized_args = array_diff(array_keys($args), static::get_valid_commands_args()))) {
            throw new UnrecognizedArgument('Parámetro(s) desconocido(s): ' . join(', ', $unrecognized_args));
        }

        return true;
    }

    /**
     * Tells if it's necessary to do a device database update. To do this, the method verified the command
     * executed to see if it did any adding, deleting or updating of database device. In that case, a
     * <code>refesh_db</code> command is executed.
     *
     * @param string $command_executed command executed.
     */
    private function check_for_refresh_tad_db($command_executed)
    {
        preg_match('/^(set_|delete_)/', $command_executed) && $this->execute_command_via_tad_soap('refresh_db', []);
    }

    /**
     * Returns an array with all parseable_args, allowed by the class, initialized with specific values
     * passed through $values array. Those args not passed in method param will be set to null.
     *
     * @param array $values array values to be analized.
     * @return array array generated.
     */
    private function config_array_items(array $values)
    {
        $normalized_args = [];

        foreach (static::get_valid_commands_args() as $parseable_arg_key) {
            $normalized_args[$parseable_arg_key] =
                    isset($values[$parseable_arg_key]) ? $values[$parseable_arg_key] : null;
        }

        return $normalized_args;
    }
}
