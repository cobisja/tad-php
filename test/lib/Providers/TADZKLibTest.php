<?php
namespace Test\Providers;

use TADPHP\Providers\TADZKLib;
use Test\Helpers\ClassReflection;


class TADZKLibTest extends \PHPUnit_Framework_TestCase
{

  public function testBuildTADZKLibIsOk()
  {
    $options = ['ip' => '127.0.0.1', 'udp_port' => 4370, 'connection_timeout'=>1];
    $zk = new TADZKLib( $options );

    $this->assertNotNull($zk);
    $this->assertInstanceOf('TADPHP\Providers\TADZKLib', $zk);

    return $zk;
  }
  /**
   * @depends testBuildTADZKLibIsOk
   * @dataProvider build_commands_fixtures
   */
  public function testBuildCommandResponse($command, $result_code, $result, $expected_xml, $encoding, TADZKLib $zk)
  {
    $result = ClassReflection::invoke_method($zk, 'build_command_response', [ $command, $result_code, $result, $encoding ]);

    $this->assertEquals($expected_xml, $result);
  }

  /**
   * @depends testBuildTADZKLibIsOk
   * @dataProvider datetime_fixtures
   */
  public function testSettingUpDateIsOk($datetime, TADZKLib $zk)
  {
    $valid_datetime_keys = ['year', 'month', 'day', 'hour', 'minute', 'second'];

    $result = ClassReflection::invoke_method( $zk, 'setup_datetime', [$datetime] );
    $result_keys = array_keys($result);

    $this->assertEmpty( array_diff( $valid_datetime_keys, $result_keys ), 'invalid keys found!');
  }

  /**
   * @depends testBuildTADZKLibIsOk
   */
  public function testReverseHex(TADZKLib $zk)
  {
    $hex_data = "000000000000000000000000000000002202000000000000420400000000000043000000000000004a0a00000000000002000000020000001027000010270000400d0300ce220000ee240000fd0c0300000000000000000000000000";

    $reversed_hex = ClassReflection::invoke_method($zk, 'reverse_hex', [$hex_data]);
    $reversed_reversed_hex = ClassReflection::invoke_method($zk, 'reverse_hex', [$reversed_hex]);

    $this->assertEquals( strlen($hex_data), strlen($reversed_hex));
    $this->assertEquals( $hex_data, $reversed_reversed_hex );
  }

  /**
   * @depends testBuildTADZKLibIsOk
   */
  public function testEncodeTime(TADZKLib $zk)
  {
    $expected_encoded_time = 480003771; // This integer represents '2014-12-07 14:22:51' timestamp.

    $dt = ['date'=>'2014-12-07', 'time'=>'14:22:51'];
    $t = ClassReflection::invoke_method($zk, 'setup_datetime', [$dt]);
    $encoded_time = ClassReflection::invoke_method($zk, 'encode_time', [$t]);

    $this->assertInternalType('integer', $encoded_time);
    $this->assertEquals($expected_encoded_time, $encoded_time);
  }



  public function build_commands_fixtures()
  {
    $encoding = 'iso8859-1';
    return [
      [ 'restart', true, true, '<RestartResponse><Row><Result>1</Result><Information>Successfully!</Information></Row></RestartResponse>', $encoding],
      [ 'poweroff', false, false, '<PoweroffResponse><Row><Result>0</Result><Information>Fail!</Information></Row></PoweroffResponse>', $encoding],
      [ 'foo', true, ['bar'=>0, 'taz'=>0], '<FooResponse><Row><bar>0</bar><taz>0</taz></Row></FooResponse>', $encoding],
      [ 'foo', true, [], '<FooResponse></FooResponse>', $encoding]
    ];
  }

  public function datetime_fixtures()
  {
    return [
      'empty_args' => [ [] ],
      'only_date'  => [ ['date'=>'2014-12-06'] ],
      'only_time'  => [ ['time'=>'08:38:23'] ],
      'valid_args' => [ ['date'=>'2014-12-06', 'time'=>'08:38:23'] ] ,
      'crazy_args' => [ ['foo'=>'123', 'bar'=>'abc', 'baz'=>'#$%'] ]
    ];
  }
}