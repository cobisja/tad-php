<?php
namespace Test;

use TADPHP\TADHelpers;

class TADHelpersTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @dataProvider datetimeFixtures
   */
  public function testSetupDateIsOk(array $datetime)
  {
    $this->assertInternalType('array', $datetime);
    
    $valid_datetime_keys = ['year', 'month', 'day', 'hour', 'minute', 'second'];
    $datetime_keys = array_keys($datetime);
    
    $this->assertEmpty( array_diff( $valid_datetime_keys, $datetime_keys ), 'key(s) inválido(s)');
  }
  
  public function testReverseHex()
  {
    $hex_data = "000000000000000000000000000000002202000000000000420400000000000043000000000000004a0a00000000000002000000020000001027000010270000400d0300ce220000ee240000fd0c0300000000000000000000000000";

    $reversed_hex = TADHelpers::reverse_hex($hex_data);
    
    $this->assertEquals( strlen($hex_data), strlen($reversed_hex));
    $this->assertEquals( $hex_data, TADHelpers::reverse_hex($reversed_hex ));
  }
  
  public function testEncodeTime()
  {
    $expected_encoded_time = 480003771; // This integer represents '2014-12-07 14:22:51' timestamp.

    $dt = ['date'=>'2014-12-07', 'time'=>'14:22:51'];
    $t = TADHelpers::setup_datetime_array($dt);    
    $encoded_time = TADHelpers::encode_time($t);
    
    $this->assertInternalType('integer', $encoded_time);
    $this->assertEquals($expected_encoded_time, $encoded_time);    
  }
  
  public function testDecodeTime()
  {
    $expected_timestamp = '2014-12-07 14:22:51';
    $timestamp = 480003771; // This integer represents $expected_timestamp value.
    
    $this->assertEquals($expected_timestamp, TADHelpers::decode_time($timestamp));
  }
  
  /**
   * @dataProvider xmlStandardFixture
   */
  public function testFilterXmlByDate($xml)
  {
    $expected_xml = "<Row><PIN>10610805</PIN><DateTime>2014-12-04 01:06:35</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>";
    
    $date_range = ['start_date'=>'2014-12-03', 'end_date'=>'2014-12-04'];    
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);
    
    $this->assertNotEmpty($filtered_xml);
    $this->assertXmlStringEqualsXmlString($expected_xml, $filtered_xml);
  }
  
  /**
   * @dataProvider xmlStandardFixture
   */
  public function testFilterXmlByDateWithDateRangeNotPresentInXml($xml)
  {
    $date_range = ['start_date'=>'2014-01-01', 'end_date'=>'2014-11-29'];
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);
    
    $this->assertXmlStringEqualsXmlString(TADHelpers::XML_NO_DATA_FOUND, $filtered_xml);
  }
  
  /**
   * @dataProvider dateRangeFixture
   */
  public function testFilterXmlByDateWithIncompleteDateRange($date_range)
  {
    $xml = '<Row><Foo><Bar></Bar></Foo></Row>';
    
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);
    
    $this->assertEquals(TADHelpers::XML_NO_DATA_FOUND, $filtered_xml);
  }     
  
  public function testFilterXmlByDateWithCustomizedRowTag()
  {
    $xml = '<CustomTag><PIN>10610805</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></CustomTag>';
    $filtered_xml = TADHelpers::filter_xml_by_date(
            $xml,
            ['start_date'=>'2014-11-30', 'end_date'=>'2014-11-30'],
            '<CustomTag>'
    );

    $this->assertXmlStringEqualsXmlString($xml, $filtered_xml);
  }
  
  /**
   * @dataProvider xmlStandardFixture
   */
  public function testFilterXmlByDateWithCustomizedRootTag($xml)
  {
    $xml = '<GetFreeSizesResponse>' . $xml . '</GetFreeSizesResponse>';
    $expected_xml = "<GetFreeSizesResponse><Row><PIN>10610805</PIN><DateTime>2014-12-04 01:06:35</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row></GetFreeSizesResponse>";
    
    $date_range = ['start_date'=>'2014-12-03', 'end_date'=>'2014-12-04'];
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);

    $this->assertXmlStringEqualsXmlString($expected_xml, $filtered_xml);
  }
  
  /**
   * @dataProvider xmlAndJsonFixtures
   */
  public function testXmlToJson($sample_xml, $expected_json)
  {
    $json = TADHelpers::xml_to_json($sample_xml);
    $this->assertEquals($json, $expected_json);
  }
  
  /**
   * @dataProvider xmlAndArrayFixtures
   */
  public function testXmlToArray($sample_xml, $expected_array)
  {
    $array = TADHelpers::xml_to_array($sample_xml);
    $this->assertTrue( $array === $expected_array );
  }
  
  /**
   * @dataProvider arrayAndXmlFixtures
   */
  public function testArrayToXml($sample_array, $expected_xml)
  {
    $xml = TADHelpers::array_to_xml(new \SimpleXMLElement('<root/>'), $sample_array);
    $this->assertEquals($xml, $expected_xml);
  }
  
  public function datetimeFixtures()
  {
    return [
      'empty_args' => [TADHelpers::setup_datetime_array()],
      'only_date'  => [TADHelpers::setup_datetime_array(['date'=>'2014-12-06'])],
      'only_time'  => [TADHelpers::setup_datetime_array(['time'=>'08:38:23'])],
      'valid_args' => [TADHelpers::setup_datetime_array(['date'=>'2014-12-06', 'time'=>'08:38:23'])] ,
      'crazy_args' => [TADHelpers::setup_datetime_array(['foo'=>'123', 'bar'=>'abc', 'baz'=>'#$%'])]
    ];
  }
  
  public function xmlStandardFixture()
  {
    return [
     ["
      <Row><PIN>10610805</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>2</PIN><DateTime>2014-11-30 18:43:27</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-11-30 20:52:44</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>0</PIN><DateTime>2014-11-30 20:52:54</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-11-30 21:24:46</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>0</PIN><DateTime>2014-12-02 08:01:11</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-12-02 08:01:23</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>0</PIN><DateTime>2014-12-02 08:01:32</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-12-04 01:06:35</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
     "]
    ];
  }
  
  public function dateRangeFixture()
  {
    return [
        [ [] ],        
        [ [ 'start_date' => '2014-01-01' ] ],
        [ [ 'end_date' => '2014-01-31' ] ]
    ];
  }
  
  public function xmlAndJsonFixtures()
  {
    return [
        ['', '{}'],
        [null, '{}'],
        ['<user><name>foo</name><lastname>bar</lastname><age>99</age></user>',
         '{"name":"foo","lastname":"bar","age":"99"}'],
        ['<vehicle><model>Arauca</model><color>Blue</color><color>Silver</color><color>Gray</color></vehicle>',
         '{"model":"Arauca","color":["Blue","Silver","Gray"]}'],
        ['<logs>
          <Row><PIN>99999999</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
          <Row><PIN>2</PIN><DateTime>2014-11-30 18:43:27</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
          <Row><PIN>11111111</PIN><DateTime>2014-11-30 20:52:44</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
          </logs>',
          '{"Row":[{"PIN":"99999999","DateTime":"2014-11-30 18:36:49","Verified":"0","Status":"0","WorkCode":"0"},{"PIN":"2","DateTime":"2014-11-30 18:43:27","Verified":"0","Status":"0","WorkCode":"0"},{"PIN":"11111111","DateTime":"2014-11-30 20:52:44","Verified":"0","Status":"0","WorkCode":"0"}]}']
    ];
  }
  
  public function xmlAndArrayFixtures()
  {
    return [
        ['', []],
        [null, []],
        ['<user><name>foo</name><lastname>bar</lastname><age>99</age></user>', ['name'=>'foo', 'lastname'=>'bar', 'age'=>'99'] ],
        ['<vehicle><model>Arauca</model><color>Blue</color><color>Silver</color><color>Gray</color></vehicle>', ['model'=>'Arauca', 'color'=>['Blue', 'Silver', 'Gray'] ] ],
        ['<logs>
          <Row><PIN>99999999</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
          <Row><PIN>2</PIN><DateTime>2014-11-30 18:43:27</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
          <Row><PIN>11111111</PIN><DateTime>2014-11-30 20:52:44</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
          </logs>',
          ['Row'=>[ 
            ['PIN'=>'99999999', 'DateTime'=>'2014-11-30 18:36:49', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0'],
            ['PIN'=>'2', 'DateTime'=>'2014-11-30 18:43:27', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0'],
            ['PIN'=>'11111111', 'DateTime'=>'2014-11-30 20:52:44', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0']
          ] ]
        ]
    ];
  }
  
  public function arrayAndXmlFixtures()
  {
    return [
        [ ["foo", "bar", "baz"], '<root><0>foo</0><1>bar</1><2>baz</2></root>' ],
        ['Row'=>[ 
            ['PIN'=>'99999999', 'DateTime'=>'2014-11-30 18:36:49', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0'],
            ['PIN'=>'2', 'DateTime'=>'2014-11-30 18:43:27', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0'],
            ['PIN'=>'11111111', 'DateTime'=>'2014-11-30 20:52:44', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0']
          ],
          "<root><0><PIN>99999999</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></0><1><PIN>2</PIN><DateTime>2014-11-30 18:43:27</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></1><2><PIN>11111111</PIN><DateTime>2014-11-30 20:52:44</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></2></root>"
        ]
    ];
  }
}