<?php
namespace Test\Providers;

use TADPHP\TAD;
use TADPHP\Providers\TADSoap;
use Test\Helpers\ClassReflection;


class TADSoapTest extends \PHPUnit_Framework_TestCase
{
  public function testBuildTADSoap()
  {
    $soap_options = $this->get_soap_options();

    $soap_client = new \SoapClient( null, $soap_options );
    $tad_soap = new TADSoap($soap_client, $soap_options);

    $this->assertNotNull($tad_soap);
    $this->assertInstanceOf('TADPHP\Providers\TADSoap', $tad_soap);

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
  public function testBuildSoapRequest($command, array $args, $expected_soap_string, $encoding, TADSoap $tad_soap)
  {
    $args += array_fill_keys( TAD::get_valid_commands_args(), null );
    $soap_request = $tad_soap->build_soap_request( $command, $args, $encoding );

    $this->assertEquals( $expected_soap_string, $soap_request );
  }

  /**
   * @depends testBuildTADSoap
   */
  public function testBuildMultipleSoapRequest(TADSoap $tad_soap)
  {
    $args = array_fill_keys( TAD::get_valid_commands_args(), null );

    // We uses 'set_user_info' command defined in TADSoap class.
    // Maybe there is a better way to test this. :-P
    $soap_request = $tad_soap->build_soap_request('set_user_info', $args, 'iso8859-1');

    $this->assertInternalType('array', $soap_request);
  }

  public function testExecuteSoapRequest()
  {
    $mock_response = '<GetUserInfoResponse></GetUserInfoResponse>';
    $encoding = 'iso8859-1';

    $soap_options = $this->get_soap_options();

    $soap_client = $this->getMockBuilder('\SoapClient')
      ->setConstructorArgs( [ null, [ 'location'=>$soap_options['location'], 'uri'=>$soap_options['uri'] ] ] )
      ->setMethods( [ '__doRequest' ] )
      ->getMock();

    $soap_client->expects( $this->any() )
      ->method( '__doRequest' )
      ->with( $this->anything(), $soap_options['location'], '', SOAP_1_1 )
      ->will( $this->returnValue( $mock_response ) );

    $tad_soap = $this->getMockBuilder('TADPHP\Providers\TADSoap')
      ->setConstructorArgs( [ $soap_client, $soap_options ] )
      ->setMethods( null )
      ->getMock();

    $args = array_fill_keys( TAD::get_valid_commands_args(), null );
    $args['com_key'] = 0;
    $args['pin'] = 123;

    $response = $tad_soap->execute_soap_command( 'get_user_info', $args, $encoding );

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

    $tad_soap = $this->getMockBuilder('TADPHP\Providers\TADSoap')
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
    $encoding = 'iso8859-1';

    return [
      [ 'get_date', ['com_key'=>0], '<?xml version="1.0" encoding="'. $encoding .'" standalone="no"?><GetDate><ArgComKey>0</ArgComKey></GetDate>', $encoding ],
      [ 'get_att_log', ['com_key'=>0], '<?xml version="1.0" encoding="' . $encoding. '" standalone="no"?><GetAttLog><ArgComKey>0</ArgComKey><Arg><PIN></PIN></Arg></GetAttLog>', $encoding ],
      [ 'get_att_log', ['com_key'=>0, 'pin'=>'99999999'], '<?xml version="1.0" encoding="' . $encoding . '" standalone="no"?><GetAttLog><ArgComKey>0</ArgComKey><Arg><PIN>99999999</PIN></Arg></GetAttLog>', $encoding ],
      [ 'set_user_template', [
              'com_key' => 0,
              'pin' => '999',
              'finger_id' => '0',
              'size' => '514',
              'valid' => '1',
              'template' => 'foobartaz123'
            ],
          '<?xml version="1.0" encoding="' . $encoding . '" standalone="no"?><SetUserTemplate><ArgComKey>0</ArgComKey><Arg><PIN>999</PIN><FingerID>0</FingerID><Size>514</Size><Valid>1</Valid><Template>foobartaz123</Template></Arg></SetUserTemplate>',
          $encoding
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