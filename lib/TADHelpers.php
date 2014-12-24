<?php
namespace TADPHP;

/**
 * TADHelpers: Abstract class that provides with several useful helpers. * 
 */
abstract class TADHelpers
{
  const XML_NO_DATA_FOUND = '<Row><Result>1</Result><Information>No data!</Information></Row>';
  
  /**
   * Take an array in the form of <code>['date'=>date_value, 'time'=>time_value]</code> y returns
   * another array with the following form: 
   * 
   * <code>
   * ['year'=>foo_year, 'month'=>bar_month, 'day'=>taz_day,
   *  'hour'=>foo_hour, 'minute=>bar_minute, 'second'=>taz_minute]
   * </code>
   * 
   * Any missing item from input array is replaced by corresponding element generate from 
   * current date and time.
   * 
   * @param array $dt input 'datetime' array.
   * @return array array generated.
   */
  public static function setup_datetime_array(array $dt=[])
  {
    $now = explode(' ', date("Y-m-d H:i:s"));
    $dt = array_filter($dt, 'strlen');

    !isset($dt['date']) ? $dt['date'] = $now[0] : null;
    !isset($dt['time']) ? $dt['time'] = $now[1] : null;
    
    $date = explode('-', $dt['date']);
    $time = explode(':', $dt['time']);
    
    return ['year'=>$date[0], 'month'=>$date[1], 'day'=>$date[2],'hour'=>$time[0], 'minute'=>$time[1], 'second'=>$time[2]];
  }
  
  /**
   * Method taken from PHPLib @link http://dnaextrim.github.io/php_zklib/ project.
   * 
   * @param string $hexstr hex string.
   * @return string hex string reversed.
   */
  public static function reverse_hex($hexstr)
  {
    $tmp = '';

    for ( $i=strlen($hexstr); $i>=0; $i-- ) {
      $tmp .= substr($hexstr, $i, 2);
      $i--;
    }

    return $tmp;
  }
  
  /**
   * Method taken from PHPZKLib @link http://dnaextrim.github.io/php_zklib/ project.
   * 
   * It's been modified to accept an associative array as input.
   * 
   * @param array $t array with a timestamp data.
   * @return int timestamp encoded.
   */
  public static function encode_time(array $t)
  {
    /*Encode a timestamp send at the timeclock

    copied from zkemsdk.c - EncodeTime*/
    $d = ( ($t['year'] % 100) * 12 * 31 + (($t['month'] - 1) * 31) + $t['day'] - 1) *
         (24 * 60 * 60) + ($t['hour'] * 60 + $t['minute']) * 60 + $t['second'];

    return $d;    
  }
  
  /**
   * Method taken from PHPZKLib @link http://dnaextrim.github.io/php_zklib/ project.
   * 
   * @param int $t encoded integer that represents a timestamp data.
   * @return string timestamp string with "yyyy-mm-dd hh:mm:ss" format.
   */
  public static function decode_time($t)
  {
    /*Decode a timestamp retrieved from the timeclock

    copied from zkemsdk.c - DecodeTime*/
    $second = $t % 60;
    $t = $t / 60;

    $minute = $t % 60;
    $t = $t / 60;

    $hour = $t % 24;
    $t = $t / 24;

    $day = $t % 31+1;
    $t = $t / 31;

    $month = $t % 12+1;
    $t = $t / 12;

    $year = floor( $t + 2000 );

    $d = date("Y-m-d H:i:s", strtotime( $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second) );

    return $d;    
  }
  
  /**
   * Parses an XML string searchig for datetime data to filter it based on date criteria supplied.
   * 
   * @param string $xml input XML string.
   * @param array $range date searching criteria.
   * @param string $xml_init_row_tag XML root tag.
   * @return string XML string filtered.
   */
  public static function filter_xml_by_date($xml, array $range=[], $xml_init_row_tag='<Row>')
  {
    $matches = [];
    $filtered_xml = self::XML_NO_DATA_FOUND;
    
    $rows = explode($xml_init_row_tag, $xml);
    $main_xml_init_tag = trim(array_shift($rows));
    $main_xml_end_tag = '' !== $main_xml_init_tag  ? '</' . str_replace('<', '', $main_xml_init_tag) : '';
    
    if('' !== $main_xml_end_tag){
      $rows[] = str_replace($main_xml_end_tag, '', array_pop($rows));
    }
    
    if( isset($range['start_date']) &&
        isset($range['end_date']) &&
        preg_match_all('/<DateTime>([0-9]{4}-[0-9]{2}-[0-9]{2}).+<\/DateTime>/', $xml, $matches) ){
      $indexes = array_keys( array_filter( $matches[1], function($date) use($range){ return !(strcmp($date, $range['start_date']) < 0 || strcmp($date, $range['end_date']) > 0);} ) );
      $filtered_xml =               
              (0 === count($indexes) ?
                      self::XML_NO_DATA_FOUND :
                      join( '', array_map( function($index) use($rows, $xml_init_row_tag){ return $xml_init_row_tag . $rows[$index]; }, $indexes ) )
              );
    }
    
    return $main_xml_init_tag . $filtered_xml . $main_xml_end_tag;
  }
  
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
   * The XML generated does not include <code><?xml version="1.0"?></code> tag.
   * 
   * @param \SimpleXMLElement $object <code>SimpleXMLElement</code> instance.
   * @param array $data input array to be transformed.
   * @return string XML string generated.
   */
  public static function array_to_xml(\SimpleXMLElement $object, array $data)
  {
    foreach ($data as $key => $value)
    {   
      if (is_array($value))
      {   
        $new_object = $object->addChild($key);
        self::array_to_xml($new_object, $value);
      }   
      else
      {   
        $object->addChild($key, $value);
      }   
    }
    
    $xml = trim( str_replace( [ "\n", "\r", "<?xml version=\"1.0\"?>" ], ' ', $object->asXML() ) );
    
    return $xml;
  }
}