<?php

/*
 * tad-php
 *
 * (The MIT License)
 *
 * Copyright (c) 2015 Jorge Cobis <jcobis@gmail.com / http://twitter.com/cobisja>.
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

use TADPHP\TADResponse;


class TADResponseTest  extends \PHPUnit_Framework_TestCase
{
    public function testTADResponseIsInstantiatedCorrectly()
    {
        $header = '<?xml version="1.0" encoding="utf-8" standalone="no"?>';
        $response = '<FooResponse><value_1>Bar</value_1><value_2>Taz</value_2></FooResponse>';
        $encoding = 'utf-8';

        $tr = new TADResponse($header . $response, $encoding);

        $this->assertInstanceOf('TADPHP\TADResponse', $tr);

        $this->assertEquals($encoding, $tr->get_encoding());
        $this->assertEquals($header, $tr->get_header());
        $this->assertEquals($response, $tr->get_response_body());
        $this->assertEquals($header . $response, $tr->get_response());

        return $tr;
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     */
    public function testGetResponseInDifferentFormats(TADResponse $tr)
    {
        $xml_response = $tr->get_response(['format'=>'xml']);
        libxml_use_internal_errors(true);
        $valid_xml = simplexml_load_string($xml_response);
        libxml_use_internal_errors(false);
        $this->assertNotFalse($valid_xml);

        $json_response = $tr->get_response(['format'=>'json']);
        $this->assertNotNull(json_decode($json_response));

        $array_response = $tr->get_response(['format'=>'array']);
        $this->assertTrue(is_array($array_response) && 0 !== count($array_response));
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     */
    public function testSetEmptyResponse(TADResponse $tr)
    {
        $tr->set_response('');
        $expected_empty_response = ''
                . '<?xml version="1.0" encoding="utf-8" standalone="no"?>'
                . '<Response>'
                . '<Row><Result>0</Result><Information>No data!</Information></Row>'
                . '</Response>';
        $this->assertEquals(1, $tr->count());
        $this->assertTrue($tr->is_empty_response());
        $this->assertEquals($expected_empty_response, $tr->get_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     */
    public function testChangeAnAlreadySetResponse(TADResponse $tr)
    {
        $tr = new TADResponse('<Response><data>Foo</data></Response>', 'iso8859-1');
        $first_response = $tr->to_xml();
        $tr->set_response(''
                . '<?xml version="1.0" encoding="utf-8" standalone="no"?>'
                . '<Response><data>Foo</data></Response>'
        );
        $last_response = $tr->to_xml();

        $this->assertNotEquals($last_response, $first_response);
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     */
    public function testGetHeader(TADResponse $tr)
    {
        $expected_header = '<?xml version="1.0" encoding="utf-8" standalone="no"?>';
        $this->assertEquals($expected_header, $tr->get_header());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     */
    public function testSetHeader(TADResponse $tr)
    {
        $expected_header = '<?xml version="1.1" encoding="iso8859-1" standalone="yes"?>';
        $tr->set_header($expected_header);

        $this->assertEquals($expected_header, $tr->get_header());
    }

    public function testGetResponseBody()
    {
        $tr = new TADResponse('<Response><data>Foo</data></Response>', 'iso8859-1');
        $response_body = '<Response><data>Foo</data></Response>';

        $this->assertEquals($response_body, $tr->get_response_body());
    }

    public function testIsEmptyResponse()
    {
        $tr = new TADResponse('<Response></Response>', 'iso8859-1');
        $this->assertTrue($tr->is_empty_response());
    }

    public function testCount()
    {
        $tr = new TADResponse('<Response></Response>', 'iso8859-1');
        $this->assertTrue(0 === $tr->count()-1);
    }

    /**
     * @expectedException \Exception
     */
    public function testExceptionIsThrownWhenUnknownMethodIsInvoked()
    {
        $tr = new TADResponse('<Response></Response>', 'iso8859-1');
        $tr->foo();
    }

    /**
     * @expectedException TADPHP\Exceptions\FilterArgumentError
     */
    public function testFilterArgumentExecptionisThrownWhenWrongArgumentNumber()
    {
        $tr = new TADResponse('<Response></Response>', 'iso8859-1');
        $tr->filter_by_date_and_pin(123);
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlAttLogFixture
     * @expectedException TADPHP\Exceptions\FilterArgumentError
     */
    public function testFilterResponseByDateThrowsFilterArgumentExceptionWithInvalidRangeKey($xml, TADResponse $tr)
    {
        $tr->set_response($xml);

        $date_range = ['foo'=>'2014-01-01', 'end'=>'2014-11-29'];
        $tr->filter_by_date($date_range);
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlAttLogFixture
     */
    public function testFilterResponseByDate($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(9, $tr->filter_by_date(['start'=>'2014-01-01'])->count());
        $this->assertEquals(1, $tr->filter_by_date(['start'=>'2014-12-04'])->count());
        $this->assertTrue($tr->filter_by_date(['start'=>'2014-12-05'])->is_empty_response());

        $tr->set_response($xml);
        $this->assertEquals(9, $tr->filter_by_date(['end'=>'2014-12-31'])->count());
//        $this->assertEquals(1, $tr->filter_by_date(['end'=>'2014-11-30'])->count());
        $this->assertTrue($tr->filter_by_date(['end'=>'2014-11-29'])->is_empty_response());

        $tr->set_response($xml);
        $this->assertEquals(9, $tr->filter_by_date(['start'=>'2014-11-01', 'end'=>'2014-12-31'])->count());
        $this->assertTrue($tr->filter_by_date(['start'=>'2015-01-01', 'end'=>'2015-12-31'])->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlAttLogFixture
     */
    public function testFilterResponseByTime($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(5, $tr->filter_by_time(['start'=>'18:00:00'])->count());
        $this->assertEquals(2, $tr->filter_by_time(['end'=>'19:00:00'])->count());
        $this->assertEquals(1, $tr->filter_by_time(['start'=>'00:00:00', 'end'=>'02:00:00'])->count());
        $this->assertTrue($tr->filter_by_date(['start'=>'00:00:00', 'end'=>'01:00:00'])->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlAttLogFixture
     */
    public function testFilterResponseByDateTime($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(9, $tr->filter_by_datetime(['start'=>'2014-11-30 18:00:00'])->count());
        $this->assertEquals(9, $tr->filter_by_datetime(['end'=>'2014-12-31 19:00:00'])->count());
        $this->assertEquals(1, $tr->filter_by_datetime('2014-12-02 08:01:32')->count());
        $this->assertTrue($tr->filter_by_datetime('2015-01-01 00:00:00')->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlAttLogFixture
     */
    public function testFilterResponseByStatus($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(9, $tr->filter_by_status(0)->count());
        $this->assertTrue($tr->filter_by_status(1)->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlUserInfoFixture
     */
    public function testFilterResponseByPin($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(9, $tr->filter_by_pin(['start'=>5])->count());

        $tr->set_response($xml);
        $this->assertEquals(9, $tr->filter_by_pin(['end'=>9])->count());

        $tr->set_response($xml);
        $this->assertEquals(1, $tr->filter_by_pin(1)->count());

        $tr->set_response($xml);
        $this->assertTrue($tr->filter_by_pin(0)->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlUserInfoFixture
     */
    public function testFilterResponseByPrivilege($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(1, $tr->filter_by_privilege(14)->count());

        $tr->set_response($xml);
        $this->assertTrue($tr->filter_by_privilege(2)->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlUserInfoFixture
     */
    public function testFilterResponseByCard($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(1, $tr->filter_by_card(55555)->count());

        $tr->set_response($xml);
        $this->assertTrue($tr->filter_by_card(999999)->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlUserInfoFixture
     */
    public function testFilterResponseUsingLikeOperator($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertEquals(2, $tr->filter_by_name(['like'=>'Dolor'])->count());

        $tr->set_response($xml);
        $this->assertTrue($tr->filter_by_name(['like'=>'ultricies'])->is_empty_response());
    }

    /**
     * @depends testTADResponseIsInstantiatedCorrectly
     * @dataProvider xmlUserInfoFixture
     */
    public function testFilterResponseUsingTooManyFilterArguments($xml, TADResponse $tr)
    {
        $tr->set_response($xml);
        $this->assertTrue($tr->filter_by_name(['like'=>'Dolor', 'start'=>'Foo', 'end'=>'Bar' ])->is_empty_response());
    }


    public function xmlAttLogFixture()
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

    public function xmlUserInfoFixture()
    {
        return [
            [
                ''
                . '<?xml version="1.0" encoding="iso8859-1" standalone="no"?>'
                . '<GetAllUserInfoResponse>'
                . '<Row><PIN>1</PIN><Name>Lorem</Name><Password>1234</Password><Group>1</Group><Privilege>14</Privilege><Card>55555</Card><PIN2>1001</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>2</PIN><Name>Ipsum</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1002</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>3</PIN><Name>Dolor Sed</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1003</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>4</PIN><Name>Sit</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1004</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>5</PIN><Name>Amet</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1005</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>6</PIN><Name>Consectetur</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1006</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>7</PIN><Name>Adipiscing</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1007</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>8</PIN><Name>Elit</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1008</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>9</PIN><Name>Nulla</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1009</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>10</PIN><Name>Imperdiet</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1010</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>11</PIN><Name>Molestie</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1011</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>12</PIN><Name>Ante</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1012</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '<Row><PIN>13</PIN><Name>Elit Luctus Dolor</Name><Password></Password><Group>1</Group><Privilege>0</Privilege><Card>0</Card><PIN2>1013</PIN2><TZ1>0</TZ1><TZ2>0</TZ2><TZ3>0</TZ3></Row>'
                . '</GetAllUserInfoResponse>'
            ]

        ];
    }
}
