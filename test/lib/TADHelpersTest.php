<?php
namespace Test;

use TADPHP\TADHelpers;

class TADHelpersTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @dataProvider xmlStandardFixture
   */
  public function testFilterXmlByDate($xml)
  {
    $expected_xml = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetAttLogResponse><Row><PIN>10610805</PIN><DateTime>2014-12-04 01:06:35</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row></GetAttLogResponse>';

    $date_range = ['start_date'=>'2014-12-03', 'end_date'=>'2014-12-04'];
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);

    $this->assertNotEmpty($filtered_xml);
    $this->assertEquals( $expected_xml, $filtered_xml );
  }

  /**
   * @dataProvider xmlStandardFixture
   */
  public function testFilterXmlByDateWithDateRangeNotPresentInXml($xml)
  {
    $date_range = ['start_date'=>'2014-01-01', 'end_date'=>'2014-11-29'];
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);
    $expected_xml = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetAttLogResponse>'.TADHelpers::XML_NO_DATA_FOUND.'</GetAttLogResponse>';

    $this->assertEquals( $expected_xml, $filtered_xml );
  }

  /**
   * @dataProvider dateRangeFixture
   */
  public function testFilterXmlByDateWithIncompleteDateRange($date_range)
  {
    $xml = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetAttLogResponse><Row><Foo><Bar></Bar></Foo></Row></GetAttLogResponse>';
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);
    $expected_xml = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetAttLogResponse>'.TADHelpers::XML_NO_DATA_FOUND.'</GetAttLogResponse>';

    $this->assertEquals( $expected_xml, $filtered_xml );
  }

  public function testFilterXmlByDateWithCustomizedRowTag()
  {
    $xml = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetAttLogResponse><CustomTag><PIN>10610805</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></CustomTag></GetAttLogResponse>';
    $filtered_xml = TADHelpers::filter_xml_by_date(
            $xml,
            ['start_date'=>'2014-11-30', 'end_date'=>'2014-11-30'],
            '<CustomTag>'
    );

    $this->assertXmlStringEqualsXmlString($xml, $filtered_xml);
  }
/***
  /
    @dataProvider xmlStandardFixture

  public function testFilterXmlByDateWithCustomizedRootTag($xml)
  {
    $xml = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetFreeSizesResponse>' . $xml . '</GetFreeSizesResponse>';
    $expected_xml = '<?xml version="1.0" encoding="iso8859-1" standalone="no"?><GetFreeSizesResponse><Row><PIN>10610805</PIN><DateTime>2014-12-04 01:06:35</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row></GetFreeSizesResponse>';

    $date_range = ['start_date'=>'2014-12-03', 'end_date'=>'2014-12-04'];
    $filtered_xml = TADHelpers::filter_xml_by_date($xml, $date_range);

    $this->assertXmlStringEqualsXmlString($expected_xml, $filtered_xml);
  }
 *
 */

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
    $this->assertEquals($expected_xml, $xml);
  }


  public function xmlStandardFixture()
  {
    return [
     ['
      <?xml version="1.0" encoding="iso8859-1" standalone="no"?>
      <GetAttLogResponse>
      <Row><PIN>10610805</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>2</PIN><DateTime>2014-11-30 18:43:27</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-11-30 20:52:44</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>0</PIN><DateTime>2014-11-30 20:52:54</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-11-30 21:24:46</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>0</PIN><DateTime>2014-12-02 08:01:11</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-12-02 08:01:23</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>0</PIN><DateTime>2014-12-02 08:01:32</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      <Row><PIN>10610805</PIN><DateTime>2014-12-04 01:06:35</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></Row>
      </GetAttLogResponse>
     ']
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
        [ ["foo", "bar", "baz"], '<?xml version="1.0" encoding="utf-8" standalone="no"?><root><0>foo</0><1>bar</1><2>baz</2></root>' ],
        ['Row'=>[
            ['PIN'=>'99999999', 'DateTime'=>'2014-11-30 18:36:49', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0'],
            ['PIN'=>'2', 'DateTime'=>'2014-11-30 18:43:27', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0'],
            ['PIN'=>'11111111', 'DateTime'=>'2014-11-30 20:52:44', 'Verified'=>'0', 'Status'=>'0', 'WorkCode'=>'0']
          ],
          '<?xml version="1.0" encoding="utf-8" standalone="no"?><root><0><PIN>99999999</PIN><DateTime>2014-11-30 18:36:49</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></0><1><PIN>2</PIN><DateTime>2014-11-30 18:43:27</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></1><2><PIN>11111111</PIN><DateTime>2014-11-30 20:52:44</DateTime><Verified>0</Verified><Status>0</Status><WorkCode>0</WorkCode></2></root>'
        ]
    ];
  }
}