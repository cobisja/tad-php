<?php
namespace Test\Providers;

use Providers\TADZKLib;
use Test\Helpers\ClassReflection;


class TADZKLibTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @dataProvider build_commands_fixtures
   */
  public function testBuildCommandResponse($command, $result, $expected_xml)
  {
    $zk = new TADZKLib( ['ip' => '127.0.0.1', 'udp_port' => 4370, 'connection_timeout'=>1] );
    
    $result = ClassReflection::invoke_method( $zk, 'build_command_response', [ $command, $result ] );

    $this->assertEquals($expected_xml, $result);
  }
  
  
  public function build_commands_fixtures()
  {
    return [
      [ 'restart', false, '<RestartResponse><Result>1</Result><Information>Succeed!</Information></RestartResponse>'],
      [ 'poweroff', true, '<PoweroffResponse><Result>0</Result><Information>Fail!</Information></PoweroffResponse>'],
      [ 'foo', ['bar'=>0, 'taz'=>0], '<FooResponse><bar>0</bar><taz>0</taz></FooResponse>'],
      [ 'foo', [], '<FooResponse><Row><Result>1</Result><Information>No data!</Information></Row></FooResponse>']
    ];
  }
}