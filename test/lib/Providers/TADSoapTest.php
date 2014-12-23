<?php
namespace Test\Providers;

use TADPHP\TAD;
use TADPHP\TADHelpers;
use Providers\TADSoap;
use Test\Helpers\ClassReflection;


class TADSoapTest extends \PHPUnit_Framework_TestCase
{  
  public function testBuildTADSoap()
  {
    $soap_options = $this->get_soap_options();
    
    $soap_client = new \SoapClient( null, $soap_options );    
    $tad_soap = new TADSoap($soap_client, $soap_options);
    
    $this->assertNotNull($tad_soap);
    $this->assertInstanceOf('Providers\TADSoap', $tad_soap);
    
    return $tad_soap;
  }
  
  /**
   * @depends testBuildTADSoap
   */
  public function testGetSoapProviderOptions($tad_soap_instance)
  {
    $soap_providers_options = $tad_soap_instance->get_soap_provider_options();
    
    $this->assertInternalType('array', $soap_providers_options);
    $this->assertArrayHasKey('location', $soap_providers_options);
    $this->assertArrayHasKey('uri', $soap_providers_options);
    $this->assertEquals('http://127.0.0.1/iWsService', $soap_providers_options['location']);
    $this->assertEquals('http://www.zksoftware/Service/message/', $soap_providers_options['uri']);
  }
  
  /**
   * @depends testBuildTADSoap
   * @dataProvider soapCommandsFixtures
   */
  public function testBuildSoapRequest($valid_args, $command, array $args, $expected_soap_string, TADSoap $tad_soap)
  { 
    $args = TADHelpers::config_array_items( $valid_args, $args );
    $soap_request = $tad_soap->build_soap_request( $valid_args, $command, $args );
    
    $this->assertEquals( $expected_soap_string, $soap_request );
  }
  
  public function testExecuteSoapRequest()
  {    
    $mock_response = '<GetUserInfoResponse></GetUserInfoResponse>';
    
    $soap_options = $this->get_soap_options();
    
    $soap_client = $this->getMockBuilder('\SoapClient')
      ->setConstructorArgs( [ null, [ 'location'=>$soap_options['location'], 'uri'=>$soap_options['uri'] ] ] )
      ->setMethods( [ '__doRequest' ] )
      ->getMock();
    
    $soap_client->expects( $this->any() )
      ->method( '__doRequest' )
      ->with( $this->anything(), $soap_options['location'], '', SOAP_1_1 )
      ->will( $this->returnValue( $mock_response ) );
    
    $tad_soap = $this->getMockBuilder('Providers\TADSoap')
      ->setConstructorArgs( [ $soap_client, $soap_options ] )
      ->setMethods( null )
      ->getMock();
    
    $response = $tad_soap->execute_soap_command(
            'get_user_info',
            ['com_key'=>0, 'pin'=>'123'],
            TAD::get_valid_commands_args()
    );
    
    $this->assertNotEmpty( $response );
  }
  
  public function testExecuteMultipleSoapRequests()
  {
    $soap_requests = [
        '<GetDate><ArgComKey>0</ArgComKey></GetDate>',
        '<Restart><ArgComKey>0</ArgComKey></Restart>'
    ];
    
    $mock_response = '<RestartResponse><Row><Result>1</Result><Information>Success!</Information></Row></RestartResponse>';
    
    $soap_options = $this->get_soap_options();
    
    $soap_client = $this->getMockBuilder('\SoapClient')
      ->setConstructorArgs( [ null, [ 'location'=>$soap_options['location'], 'uri'=>$soap_options['uri'] ] ] )
      ->setMethods( [ '__doRequest' ] )
      ->getMock();    
    
    $soap_client->expects( $this->any() )
      ->method( '__doRequest' )
      ->with( $this->anything(), $soap_options['location'], '', SOAP_1_1 )
      ->will( $this->returnValue( $mock_response ) );    

    $tad_soap = $this->getMockBuilder('Providers\TADSoap')
      ->setConstructorArgs( [ $soap_client, $soap_options ] )
      ->setMethods( null )
      ->getMock();
    
    
    $result = ClassReflection::invoke_method(
            $tad_soap,
            'execute_multiple_soap_requests',
            [ $soap_requests, $soap_options['location'] ] );
    
    $this->assertNotEmpty( $result );
  }

  
  public function soapCommandsFixtures()
  {
    $valid_args = TAD::get_valid_commands_args();
    
    return [
      [ $valid_args, 'get_date', ['com_key'=>0], '<GetDate><ArgComKey>0</ArgComKey></GetDate>' ],
      [ $valid_args,'get_att_log', ['com_key'=>0], '<GetAttLog><ArgComKey>0</ArgComKey><Arg><PIN></PIN></Arg></GetAttLog>' ],
      [ $valid_args, 'get_att_log', ['com_key'=>0, 'pin'=>'99999999'], '<GetAttLog><ArgComKey>0</ArgComKey><Arg><PIN>99999999</PIN></Arg></GetAttLog>' ],
      [ $valid_args,
          'set_user_template', [
              'com_key' => 0,
              'pin' => '999',
              'finger_id' => '0',
              'size' => '514',
              'valid' => '1',
              'template' => 'foobartaz123'
            ],
          '<SetUserTemplate><ArgComKey>0</ArgComKey><Arg><PIN>999</PIN><FingerID>0</FingerID><Size>514</Size><Valid>1</Valid><Template>foobartaz123</Template></Arg></SetUserTemplate>'
      ]
    ];
  }
  
  private function get_soap_options()
  {
    $soap_options = [ 
        'location' => "http://127.0.0.1/iWsService",
        'uri' => 'http://www.zksoftware/Service/message/',
        'connection_timeout' => 1,
        'exceptions' => false,
        'trace' => true
    ];
    
    return $soap_options;
  }
}