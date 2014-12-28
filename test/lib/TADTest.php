<?php
namespace Test;

use TADPHP\TADFactory;
use TADPHP\TAD;

use Providers\TADSoap;
use Providers\TADZKLib;


class TADTest extends \PHPUnit_Framework_TestCase
{
  public function testSoapCommandsAvailable()
  {
    $commands = TAD::soap_commands_available( ['include_command_string'=>true] );

    $this->assertInternalType('array', $commands);
    $this->assertTrue( $commands !== array_values($commands) );
  }

  public function testZKLibCommandsAvailable()
  {
    $commands = TAD::zklib_commands_available();

    $this->assertInternalType('array', $commands);
    $this->assertFalse( $commands !== array_values($commands) );
  }

  public function testGetValidCommandsArgs()
  {
    $valid_args = TAD::get_valid_commands_args();

    $this->assertInternalType('array', $valid_args);
    $this->assertFalse( $valid_args !== array_values($valid_args) );
  }

  public function testGetIp()
  {
    $expected_ip = '192.168.100.156';
    $tad = (new TADFactory(['ip'=>$expected_ip]))->get_instance();

    $this->assertEquals($tad->get_ip(), $expected_ip);
  }

  public function testGetInternalId()
  {
    $tad_options = ['ip'=>'127.0.0.1', 'internal_id'=>100];
    $tad = (new TADFactory($tad_options))->get_instance();

    $this->assertEquals($tad->get_internal_id(), $tad_options['internal_id']);
  }

  public function testGetComKey()
  {
    $tad_options = ['ip'=>'127.0.0.1', 'internal_id'=>100, 'comkey'=>'0'];
    $tad = (new TADFactory($tad_options))->get_instance();

    $this->assertEquals($tad->get_com_key(), $tad_options['comkey']);
  }

  public function testGetDescription()
  {
    $tad_options = ['ip'=>'127.0.0.1', 'internal_id'=>100, 'description'=>'Lorem Ipsum'];
    $tad = (new TADFactory($tad_options))->get_instance();

    $this->assertEquals($tad->get_description(), $tad_options['description']);
  }

  public function testDeviceWithInvalidIPAddressIsNotAlive()
  {
    $tad = (new TADFactory(['ip'=>'1.2.3.4', 'connection_timeout'=>1]))->get_instance();

    $this->assertFalse( $tad->is_alive() );
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

    $tad_soap_provider  = new TADSoap( new \SoapClient( null, $options['soap'] ), $options['soap'] );
    $zklib_provider = new TADZKLib( $options['tad'] );

    $tad = $this->getMockBuilder('\TADPHP\TAD')
      ->setConstructorArgs( [ $tad_soap_provider, $zklib_provider, $options['tad'] ] )
      ->setMethods( ['is_alive', 'execute_command_via_tad_soap', 'execute_command_via_zklib'] )
      ->getMock();

    $tad->expects( $this->any() )
      ->method('is_alive')
      ->will( $this->returnValue(true) );

    $tad->expects( $this->any() )
      ->method('execute_command_via_tad_soap')
      ->will( $this->returnValue('<CommandResponse>Executed via SOAP</CommandResponse>') );

    $tad->expects( $this->once() )
      ->method('execute_command_via_zklib')
      ->will( $this->returnValue('<CommandResponse>Executed via ZKLib</CommandResponse>') );

    $this->assertEquals( '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><CommandResponse>Executed via SOAP</CommandResponse>',  $tad->get_date() );
    $this->assertEquals( '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><CommandResponse>Executed via ZKLib</CommandResponse>', $tad->set_date() );
  }

  public function testExecuteCommandViaTADSoap()
  {
    $options = $this->get_tad_and_soap_options();
    $mock_response = '<GetDateResponse><row><date>2014-01-01</date><time>12:00:00</time></row></GetDateResponse>';

    $soap_client = new \SoapClient( null, $options['soap'] );

    $tad_soap = $this->getMockBuilder('Providers\TADSoap')
      ->setConstructorArgs( [ $soap_client, $options['soap'] ] )
      ->setMethods( ['execute_soap_command'] )
      ->getMock();

    $tad_soap->expects( $this->once() )
      ->method('execute_soap_command')
      ->will( $this->returnValue( $mock_response ) );

    $zklib_provider = new TADZKLib( $options['tad'] );

    $tad = $this->getMockBuilder('\TADPHP\TAD')
      ->setConstructorArgs( [ $tad_soap, $zklib_provider, $options['tad'] ] )
      ->setMethods( null )
      ->getMock();

    $response = $tad->execute_command_via_tad_soap( 'get_date', [] );

    $this->assertNotEmpty($response);
  }

  public function testExecuteCommandViaZKLib()
  {
    $options = $this->get_tad_and_soap_options();
    $mock_response = '<SetDateResponse><Result>1</Result><Information>Successfully!</Information></SetDateResponse>';

    $soap_client = new \SoapClient( null, $options['soap'] );
    $tad_soap = new \Providers\TADSoap( $soap_client, $options['soap'] );

    $zk = $this->getMockBuilder('\Providers\TADZKLib')
      ->setConstructorArgs( [ $options['tad'] ] )
      ->setMethods( ['__call'] )
      ->getMock();

    $zk->expects( $this->once() )
       ->method('__call')
       ->will( $this->returnValue( $mock_response ) );

    $tad = $this->getMockBuilder('\TADPHP\TAD')
      ->setConstructorArgs( [ $tad_soap, $zk, $options['tad'] ] )
      ->setMethods( null )
      ->getMock();

    $response = $tad->set_date();

    $this->assertNotEmpty($response);
  }

  protected function get_tad_and_soap_options($mock_ip='127.0.0.1')
  {
    $options = [
        'ip' => $mock_ip,
        'com_key' => 0,
        'internal_id' => '100',
        'description' => '',
        'connection_timeout'=> 1,
        'udp_port' => 4370,
        'encoding'=>'iso8859-1'
    ];

    $soap_options = [
        'location' => "http://$mock_ip/iWsService",
        'uri' => 'http://www.zksoftware/Service/message/'
    ];

    return ['tad' => $options, 'soap' => $soap_options];
  }
}