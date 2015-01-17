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
 * TADSoap: class that allows to interact with a Time & Attendance device using SOAP.
 */
class TADSoap
{
    const XML_FAIL_RESPONSE = 'Fail!';
    const XML_SUCCESS_RESPONSE = 'Succeed!';
    const SOAP_VERSION = SOAP_1_1;

    /**
     * @var array SOAP commands array supported by the class.
     */
    static private $soap_commands_available = [
        'get_date'            => '<GetDate><ArgComKey>%com_key%</ArgComKey></GetDate>',
        'get_att_log'         => '<GetAttLog><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></GetAttLog>',
        'get_user_info'       => '<GetUserInfo><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></GetUserInfo>',
        'get_all_user_info'   => '<GetAllUserInfo><ArgComKey>%com_key%</ArgComKey></GetAllUserInfo>',
        'get_user_template'   => '<GetUserTemplate><ArgComKey>0</ArgComKey><Arg><PIN>%pin%</PIN><FingerID>%finger_id%</FingerID></Arg></GetUserTemplate>',
        'get_combination'     => '<GetCombination><ArgComKey>%com_key%</ArgComKey></GetCombination>',
        'get_option'          => '<GetOption><ArgComKey>%com_key%</ArgComKey><Arg><Name>%option_name%</Name></Arg></GetOption>',
        'set_user_info'       => [ '<DeleteUser><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteUser>', '<SetUserInfo><ArgComKey>%com_key%</ArgComKey><Arg><Name>%name%</Name><Password>%password%</Password><Group>%group%</Group><Privilege>%privilege%</Privilege><Card>%card%</Card><PIN2>%pin%</PIN2><TZ1>%tz1%</TZ1><TZ2>%tz2%</TZ2><TZ3>%tz3%</TZ3></Arg></SetUserInfo>'],
        'set_user_template'   => '<SetUserTemplate><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN><FingerID>%finger_id%</FingerID><Size>%size%</Size><Valid>%valid%</Valid><Template>%template%</Template></Arg></SetUserTemplate>',
        'delete_user'         => '<DeleteUser><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteUser>',
        'delete_template'     => '<DeleteTemplate><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteTemplate>',
        'delete_user_password'=> '<ClearUserPassword><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></ClearUserPassword>',
        'delete_data'         => '<ClearData><ArgComKey>%com_key%</ArgComKey><Arg><Value>%value%</Value></Arg></ClearData>',
        'refresh_db'          => '<RefreshDB><ArgComKey>%com_key%</ArgComKey></RefreshDB>',
    ];

    /**
     * @var SOAPClient Holds a <code>\SoapClient</code> instance.
     */
    private $soap_client;

    /**
     * @var array Options array required by <code>SoapClient</code> class.
     */
    private $soap_client_options;


    /**
     * Returns commands available by the class.
     *
     * @param array $options options to define the information level about commands available by the class.
     * @return array commands available list.
     */
    static public function get_commands_available(array $options = [])
    {
        return (isset($options['include_command_string']) && $options['include_command_string']) ?
              self::$soap_commands_available : array_keys(self::$soap_commands_available);
    }

    /**
     * Build a <code>TADSoap</code> instance to allow communication with the device via SOAP api.
     *
     * @param \SoapClient $soap_client <code>SoapClient</code> instance
     * @param array $soap_client_options options required by <code>SoapClient</code> class.
     */
    public function __construct(\SoapClient $soap_client, array $soap_client_options)
    {
        $this->soap_client = $soap_client;
        $this->soap_client_options = $soap_client_options;
    }

    /**
     * Get a command, build the SOAP request and send it to device.
     *
     * @param mixed $soap_command command to be executed.
     * @param array $soap_command_args command arguments.
     * @return string response.
     */
    public function execute_soap_command($soap_command, array $soap_command_args, $encoding)
    {
        $soap_location = $this->get_soap_provider_options()['location'];
        $soap_request = $this->build_soap_request($soap_command, $soap_command_args, $encoding);

        $response = !is_array($soap_request) ?
                $this->execute_single_soap_request($soap_request, $soap_location) :
                $this->execute_multiple_soap_requests($soap_request, $soap_location);

        return new TADResponse($response, $encoding);
    }

    /**
     * Returns params required by <b><code>SoapClient</code></b> class.
     *
     * @return array params list.
     */
    public function get_soap_provider_options()
    {
        return $this->soap_client_options;
    }

    /**
     * Returns the SOAP request based on command.
     *
     * @param string $command command requested.
     * @param array $args command params.
     * @return string SOAP request.
     */
    public function build_soap_request($command, array $args, $encoding)
    {
        $command_string = $this->get_command_string($command);
        $soap_request = $this->parse_command_string($command_string, $args);

        if (!is_array($soap_request)) {
            $soap_request = $this->normalize_xml_string($soap_request, $encoding);
        } else {
            $soap_request = array_map(
                function ($soap_request) use ($encoding) {
                    return $this->normalize_xml_string($soap_request, $encoding);
                },
                $soap_request
            );
        }

        return $soap_request;
    }

    /**
     * Returns command SOAP definition.
     *
     * @param string $key SOAP command requested.
     * @return string SOAP definition.
     */
    private function get_command_string($key)
    {
        return self::$soap_commands_available[$key];
    }

    /**
     * Sends a SOAP command to device.
     *
     * @param mixed $soap_request SOAP command.
     * @param string $soap_location URI required by SOAP service.
     * @return string device response.
     */
    private function execute_single_soap_request($soap_request, $soap_location)
    {
        return $this->soap_client->__doRequest($soap_request, $soap_location, '', self::SOAP_VERSION);
    }

    /**
     * Sends multiple SOAP commands to the device.
     *
     * @param mixed $soap_requests SOAP commands array.
     * @param string $soap_location URI required by SOAP service.
     * @return string device response (Always returns the last command response.)
     */
    private function execute_multiple_soap_requests(array $soap_requests, $soap_location)
    {
        foreach ($soap_requests as $soap_request) {
            $result = $this->execute_single_soap_request($soap_request, $soap_location);
        }

        return $result;
    }

    /**
     * Parses SOAP request, replacing formal params with actual params.
     *
     * @param string $command_string SOAP request.
     * @param array $command_args actual args values required by SOAP request.
     * @return string SOAP request parsed.
     */
    private function parse_command_string($command_string, array $command_args)
    {
        $parseable_args = array_map(
            function($item) {
                return '%' . $item . '%';
            },
            array_keys($command_args)
        );

        $parsed_command = str_replace($parseable_args, array_values($command_args), $command_string);

        return $parsed_command;
    }

    /**
     * Build an XML header.
     *
     * @param string $xml XML string which header will be added to.
     * @param string $encoding encoding
     * @return string full XML string (Header + Body).
     */
    public static function normalize_xml_string($xml, $encoding = 'utf-8')
    {
        $xml ='<?xml version="1.0" encoding="' . $encoding . '" standalone="no"?>' . $xml;

        return trim(str_replace([ "\n", "\r" ], '', $xml));
    }
}
