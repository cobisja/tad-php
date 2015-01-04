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

namespace TADPHP;

use TADPHP\Exceptions\FilterArgumentError;

/**
 * TADHelpers: Abstract class that provides with several useful helpers.
 */
abstract class TADHelpers
{
    const XML_NO_DATA_FOUND = '<Row><Result>1</Result><Information>No data!</Information></Row>';

    /**
     * Transforms an XML string into a JSON format.
     *
     * @param string $xml_string input XML string.
     * @return string JSON string generated.
     */
    static public function xml_to_json($xml_string)
    {
        return !isset($xml_string) || '' === trim($xml_string) ? '{}' : json_encode(simplexml_load_string($xml_string));
    }

    /**
     * Transforms an XML string into an array.
     *
     * @param string $xml_string input XML string.
     * @return array array generated.
     */
    static public function xml_to_array($xml_string)
    {
        return !isset($xml_string) || '' === trim($xml_string) ? [] : json_decode(self::xml_to_json($xml_string), true);
    }

    /**
     * Transforms an array into an XML string.
     *
     * @param \SimpleXMLElement $object <code>SimpleXMLElement</code> instance.
     * @param array $data input array to be transformed.
     * @return string XML string generated.
     */
    public static function array_to_xml(\SimpleXMLElement $object, array $data, $encoding='utf-8')
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $new_object = $object->addChild($key);
                self::array_to_xml($new_object, $value);
            } else {
                $object->addChild($key, $value);
            }
        }

        $xml = trim(str_replace("<?xml version=\"1.0\"?>", '', $object->asXML()));
        $xml = static::normalize_xml_string($xml, $encoding);

        return $xml;
    }

    public static function __callStatic($method, $args)
    {
        $xml = count($args) === 0 ? [] : array_shift($args);

        if (preg_match('/filter_xml_by_([a-zA-Z]+)/', $method)) {
            $filters = preg_split('/(by_|_and_)/i', $method, -1);
            unset($filters[0]);

            if (count($filters) !== count($args)) {
                throw new FilterArgumentError(
                    'Invalid number of arguments: '
                    . count($filters) . ' expected, '
                    . count($args) . ' given.'
                );
            }

            foreach ($filters as $filter) {
                $filter_args = static::normalize_filter_args(array_shift($args));

                switch ($filter) {
                    case 'date':
                        $filter_regex = '/<DateTime>([0-9]{4}-[0-9]{2}-[0-9]{2})/';
                        break;
                    case 'time':
                        $filter_regex = '/([0-9]{2}:[0-9]{2}:[0-9]{2})<\/DateTime>/';
                        break;
                    case 'datetime':
                        $filter_regex = '/<DateTime>([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})<\/DateTime>/';
                        break;
                    case 'pin':
                    case 'pin2':
                        $filter_regex = '/<PIN>([0-9]+)<\/PIN>/';
                        break;
                    case 'status':
                        $filter_regex = '/<Status>([0-9]+)<\/Status>/';
                        break;
                    case 'privilege':
                        $filter_regex = '/<Privilege>([0-9]+)<\/Privilege>/';
                        break;
                    case 'card':
                        $filter_regex = '/<Card>([0-9]+)<\/Card>/';
                        break;
                }

                $xml = static::filter_xml($xml, $filter_regex, $filter_args);
            }

            return $xml;
        } else {
            throw new \Exception('Unknown method ' . $method);
        }
    }

    /**
     * Parses an XML string applying a specific filter.
     *
     * @param string $xml input XML string.
     * @param string $filter regex to be applied on input.
     * @param array $range boundaries searching criteria.
     * @param string $xml_init_row_tag XML root tag.
     * @return string XML string filtered.
     */
    public static function filter_xml($xml, $filter, array $range=[], $xml_init_row_tag='<Row>')
    {
        $xml_header = '';
        if (false !== strpos($xml, '?>')) {
            $xml_items = explode('?>', $xml);

            $xml_header = $xml_items[0] . '?>';
            $xml = $xml_items[1];
        }

        $matches = [];
        $filtered_xml = self::XML_NO_DATA_FOUND;

        $rows = explode($xml_init_row_tag, $xml);
        $main_xml_init_tag = trim(array_shift($rows));
        $main_xml_end_tag = '' !== $main_xml_init_tag  ? '</' . str_replace('<', '', $main_xml_init_tag) : '';

        if ('' !== $main_xml_end_tag) {
            $rows[] = str_replace($main_xml_end_tag, '', array_pop($rows));
        }

        if (preg_match_all($filter, $xml, $matches)) {
            $indexes = array_keys(
                array_filter(
                    $matches[1],
                    function($data) use ($range) {
                        switch (count($range)) {
                            case 1:
                                if (isset($range['start'])) {
                                    return strcmp($data, $range['start']) >= 0;
                                } else {
                                    return strcmp($data, $range['start']) <= 0;
                                }
                                break;
                            case 2:
                                return !(strcmp($data, $range['start']) < 0 ||
                                         strcmp($data, $range['end']) > 0);
                        }
                    }
                )
            );

                $filtered_xml = (
                    0 === count($indexes) ?
                    self::XML_NO_DATA_FOUND :
                    join(
                        '',
                        array_map(
                            function($index) use ($rows, $xml_init_row_tag) {
                                return $xml_init_row_tag . $rows[$index];
                            },
                            $indexes
                        )
                    )
                );
        }

        $xml = $xml_header . $main_xml_init_tag . trim($filtered_xml) . $main_xml_end_tag;

        return static::sanitize_xml_string($xml);
    }

    /**
     * Adds XML header to an XML string.
     *
     * @param string $xml input XML string
     * @param string $encoding encoding to set in XML header.
     * @return string XML string normalized.
     */
    public static function normalize_xml_string($xml, $encoding = 'utf-8')
    {
        if (preg_match('/^\<\?xml/', $xml)) {
            $xml=preg_filter('/encoding="([^"]+)"/', 'encoding="' . $encoding . '"', $xml);
        } else {
            $xml ='<?xml version="1.0" encoding="' . $encoding . '" standalone="no"?>' . $xml;
        }

        return static::sanitize_xml_string($xml);
    }

    /**
     * Cleans a XML string from undesired chars (in this case EOL).
     * @param string $xml string to be cleaned out.
     * @return string XML string cleaned out.
     */
    private static function sanitize_xml_string($xml)
    {
        return trim(str_replace([ "\n", "\r" ], '', $xml));
    }

    /**
     * Sets boundaries to be used as filter criteria.
     * @param mixed $args boundaries.
     * @return array boundaries validated.
     */
    private static function normalize_filter_args($args)
    {
        $normalized_filter_args = [];
        $valid_range_filter = ['start', 'end'];

        if (is_array($args)) {
            array_walk(
                array_keys($args),
                function ($item) use ($valid_range_filter) {
                    if (!in_array($item, $valid_range_filter)) {
                        throw new FilterArgumentError('Invalid range key ' . $item);
                    }
                }
            );

            isset($args['start']) ? $normalized_filter_args['start'] = $args['start'] : null;
            isset($args['end']) ? $normalized_filter_args['end'] = $args['end'] : null;

        } else {
            $normalized_filter_args['start'] = $normalized_filter_args['end'] = $args;
        }

        return $normalized_filter_args;
    }
}
