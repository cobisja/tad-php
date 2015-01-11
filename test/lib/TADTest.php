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

namespace Test;

use TADPHP\TAD;
use TADPHP\TADFactory;
use TADPHP\Providers\TADSoap;
use TADPHP\Providers\TADZKLib;

class TADTest extends \PHPUnit_Framework_TestCase
{
    public function testSoapCommandsAvailable()
    {
        $commands = TAD::soap_commands_available(['include_command_string'=>true]);

        $this->assertInternalType('array', $commands);
        $this->assertTrue($commands !== array_values($commands));
    }

    public function testZKLibCommandsAvailable()
    {
        $commands = TAD::zklib_commands_available();

        $this->assertInternalType('array', $commands);
        $this->assertFalse($commands !== array_values($commands));
    }

    public function testGetValidCommandsArgs()
    {
        $valid_args = TAD::get_valid_commands_args();

        $this->assertInternalType('array', $valid_args);
        $this->assertFalse($valid_args !== array_values($valid_args));
    }

    /**
     * @dataProvider get_options
     */
    public function testGetOptions(array $options)
    {
        $method = array_keys($options)[0];
        $expected_value = array_values($options)[0];

        $tad_options = array_merge(['ip'=>'127.0.0.1'], $options);
        $tad = (new TADFactory($tad_options))->get_instance();

        $option_value = $tad->{"get_$method"}();

        $this->assertEquals($expected_value, $option_value);
    }

    public function testDeviceWithInvalidIPAddressIsNotAlive()
    {
        $tad = (new TADFactory(['ip'=>'1.2.3.4', 'connection_timeout'=>1]))->get_instance();

        $this->assertFalse($tad->is_alive());
    }

    /**
     * @expectedException TADPHP\Exceptions\ConnectionError
     */
    public function testTADThrowsConnectionErrorExceptionWithInvalidDeviceIPAddress()
    {
        $tad = (new TADFactory(['ip'=>'1.2.3.4', 'connection_timeout'=>1]))->get_instance();
        $tad->get_date();
    }

    /**
     * @expectedException TADPHP\Exceptions\UnrecognizedCommand
     */
    public function testTADThrowsUnrecognizedCommandExceptionWithInvalidCommand()
    {
        $tad = (new TADFactory(['ip'=>'127.0.0.1']))->get_instance();
        $tad->foo();
    }

    /**
     * @expectedException TADPHP\Exceptions\UnrecognizedArgument
     */
    public function testTADThrowsUnrecognizedArgumentExceptionWithValidCommand()
    {
        $tad = (new TADFactory(['ip'=>'127.0.0.1']))->get_instance();
        $tad->get_user_info(['foo'=>'bar']);
    }

    public function testTAD()
    {
        $options = $this->get_tad_and_soap_options();

        $tad_soap_provider  = new TADSoap(new \SoapClient(null, $options['soap']), $options['soap']);
        $zklib_provider = new TADZKLib($options['tad']);

        $tad = $this->getMockBuilder('\TADPHP\TAD')
          ->setConstructorArgs([ $tad_soap_provider, $zklib_provider, $options['tad']])
          ->setMethods(['is_alive', 'execute_command_via_tad_soap', 'execute_command_via_zklib'])
          ->getMock();

        $tad->expects($this->any())
          ->method('is_alive')
          ->will($this->returnValue(true));

        $tad->expects($this->any())
          ->method('execute_command_via_tad_soap')
          ->will($this->returnValue('<CommandResponse>Executed via SOAP</CommandResponse>'));

        $tad->expects($this->once())
          ->method('execute_command_via_zklib')
          ->will($this->returnValue('<CommandResponse>Executed via ZKLib</CommandResponse>'));

        $this->assertEquals(
            '<CommandResponse>Executed via SOAP</CommandResponse>',
            $tad->get_date()
        );
        $this->assertEquals(
            '<CommandResponse>Executed via ZKLib</CommandResponse>',
            $tad->set_date()
        );
    }

    public function testExecuteCommandViaTADSoap()
    {
        $options = $this->get_tad_and_soap_options();
        $mock_response = '<GetDateResponse><row><date>2014-01-01</date><time>12:00:00</time></row></GetDateResponse>';

        $soap_client = new \SoapClient(null, $options['soap']);

        $tad_soap = $this->getMockBuilder('TADPHP\Providers\TADSoap')
          ->setConstructorArgs([ $soap_client, $options['soap'] ])
          ->setMethods(['execute_soap_command'])
          ->getMock();

        $tad_soap->expects($this->once())
          ->method('execute_soap_command')
          ->will($this->returnValue($mock_response));

        $zklib_provider = new TADZKLib($options['tad']);

        $tad = $this->getMockBuilder('\TADPHP\TAD')
          ->setConstructorArgs([ $tad_soap, $zklib_provider, $options['tad'] ])
          ->setMethods(null)
          ->getMock();

        $response = $tad->execute_command_via_tad_soap('get_date', []);

        $this->assertNotEmpty($response);
    }

    public function testExecuteCommandViaZKLib()
    {
        $options = $this->get_tad_and_soap_options();
        $mock_response = ''
                . '<SetDateResponse>'
                . '<Result>1</Result>'
                . '<Information>Successfully!</Information>'
                . '</SetDateResponse>';

        $soap_client = new \SoapClient(null, $options['soap']);
        $tad_soap = new TADSoap($soap_client, $options['soap']);

        $zk = $this->getMockBuilder('TADPHP\Providers\TADZKLib')
          ->setConstructorArgs([ $options['tad'] ])
          ->setMethods(['__call'])
          ->getMock();

        $zk->expects($this->once())
           ->method('__call')
           ->will($this->returnValue($mock_response));

        $tad = $this->getMockBuilder('\TADPHP\TAD')
          ->setConstructorArgs([ $tad_soap, $zk, $options['tad'] ])
          ->setMethods(null)
          ->getMock();

        $response = $tad->set_date();

        $this->assertNotEmpty($response);
    }

    protected function soap_options()
    {
        return [
            []
        ];
    }

    protected function get_tad_and_soap_options()
    {
        $options = array_reduce(array_reduce($this->get_options(), 'array_merge', []), 'array_merge', []);

        $soap_options = [
            'location' => "http://{$options['ip']}/iWsService",
            'uri' => 'http://www.zksoftware/Service/message/'
        ];

        return ['tad' => $options, 'soap' => $soap_options];
    }

    public function get_options()
    {
        $options = [
            [['ip' => '127.0.0.1']],
            [['com_key' => 0]],
            [['internal_id' => 100]],
            [['description' => 'Foo']],
            [['connection_timeout'=> 1]],
            [['udp_port' => 4370]],
            [['encoding'=>'iso8859-1']]
        ];

        return $options;
    }
}
