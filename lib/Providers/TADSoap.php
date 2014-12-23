<?php
namespace Providers;

use TADPHP\TADHelpers;


class TADSoap
{
  const XML_FAIL_RESPONSE = 'Fail!';
  const XML_SUCCESS_RESPONSE = 'Succeed!';
  const SOAP_VERSION = SOAP_1_1;
  
  /**
   * SOAP commands array supported by the class.
   *
   * @var array
   */
  static private $soap_commands_available = [
      'get_date'            => '<GetDate><ArgComKey>%com_key%</ArgComKey></GetDate>',
      'get_att_log'         => '<GetAttLog><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></GetAttLog>',
      'get_user_info'       => '<GetUserInfo><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></GetUserInfo>',
      'get_all_user_info'   => '<GetAllUserInfo><ArgComKey>%com_key%</ArgComKey></GetAllUserInfo>',
      'get_user_template'   => '<GetUserTemplate><ArgComKey>0</ArgComKey><Arg><PIN>%pin%</PIN><FingerID>%finger_id%</FingerID></Arg></GetUserTemplate>',
      'get_combination'     => '<GetCombination><ArgComKey>%com_key%</ArgComKey></GetCombination>',
      'get_option'          => '<GetOption><ArgComKey>%com_key%</ArgComKey><Arg><Name>%option_name%</Name></Arg></GetOption>',      
      'set_date'            => '<SetDate><ArgComKey>%com_key%<Arg><Date>%date%</Date><Time>%time%</Time></Arg></ArgComKey></SetDate>',
      'set_user_info'       => [ '<DeleteUser><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteUser>', '<SetUserInfo><ArgComKey>%com_key%</ArgComKey><Arg><Name>%name%</Name><Password>%password%</Password><Group>%group%</Group><Privilege>%privilege%</Privilege><Card>%card%</Card><PIN2>%pin%</PIN2><TZ1>%tz1%</TZ1><TZ2>%tz2%</TZ2><TZ3>%tz3%</TZ3></Arg></SetUserInfo>'],
//      'set_user_info'       => '<SetUserInfo><ArgComKey>%com_key%</ArgComKey><Arg><Name>%name%</Name><Password>%password%</Password><Group>%group%</Group><Privilege>%privilege%</Privilege><Card>%card%</Card><PIN2>%pin2%</PIN2><TZ1>%tz1%</TZ1><TZ2>%tz2%</TZ2><TZ3>%tz3%</TZ3></Arg></SetUserInfo>',
      'set_user_template'   => '<SetUserTemplate><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN><FingerID>%finger_id%</FingerID><Size>%size%</Size><Valid>%valid%</Valid><Template>%template%</Template></Arg></SetUserTemplate>',
      'delete_user'         => '<DeleteUser><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteUser>',
      'delete_template'     => '<DeleteTemplate><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></DeleteTemplate>',
      'delete_user_password'=> '<ClearUserPassword><ArgComKey>%com_key%</ArgComKey><Arg><PIN>%pin%</PIN></Arg></ClearUserPassword>',
      'delete_data'         => '<ClearData><ArgComKey>%com_key%</ArgComKey><Arg><Value>%value%</Value></Arg></ClearData>',
      'refresh_db'          => '<RefreshDB><ArgComKey>%com_key%</ArgComKey></RefreshDB>',
      'restart'             => '<Restart><ArgComKey>%com_key%</ArgComKey></Restart>'
  ];
  
  /**
   * Holds a <code>\SoapClient</code> instance.
   * 
   * @var object 
   */
  private $soap_client;
  
  /**
   * Options array required by <code>SoapClient</code> class.
   * 
   * @var array 
   */
  private $soap_client_options;


  /**
   * Returns commands available by the class.
   * 
   * @param array $options options to define the information level about commands available by the class.
   * @return array commands available list.
   */
  static public function get_commands_available(array $options = [])
  {
    return (isset($options['include_command_string']) && $options['include_command_string']) ? 
          self::$soap_commands_available : array_keys(self::$soap_commands_available);
  }
  
  /**
   * Build a <code>TADSoap</code> instance to allow communication with the device via SOAP api.
   * 
   * @param \SoapClient $soap_client <code>SoapClient</code> instance
   * @param array $soap_client_options options required by <code>SoapClient</code> class.
   */
  public function __construct(\SoapClient $soap_client, array $soap_client_options)
  {
    $this->soap_client = $soap_client;
    $this->soap_client_options = $soap_client_options;
  }

  /**
   * Get a command, build the SOAP request and send it to device.
   * 
   * @param mixed $soap_command command to be executed.
   * @param array $command_args command arguments.
   * @param array $parseable_args valid args supported by the class.
   * @return string response.
   */
  public function execute_soap_command($soap_command, array $command_args, array $parseable_args)
  {
    $soap_request = $this->build_soap_request(
            $parseable_args,
            $soap_command,
            TADHelpers::config_array_items( $parseable_args, $command_args )
    );
    
    $soap_location = $this->get_soap_provider_options()['location'];
    
    $response = !is_array($soap_request) ?
            $this->execute_single_soap_request($soap_request, $soap_location) :
            $this->execute_multiple_soap_requests($soap_request, $soap_location);
    
    if($this->is_response_with_no_data($response)){      
      $response = $this->build_no_data_response($response);
    }
    
    return $response;
  }
  
  /**
   * Returns params required by <b><code>SoapClient</code></b> class.
   * 
   * @return array params list.
   */
  public function get_soap_provider_options()
  {
    return $this->soap_client_options;
  }
  
  /**
   * Returns the SOAP request based on command.
   * 
   * @param string $command command requested.
   * @param array $args command params.
   * @return string SOAP request.
   */
  public function build_soap_request(array $valid_args, $command, array $args=[])
  { 
    $command_string = $this->get_command_string($command);
    $soap_request = $this->parse_command_string($valid_args, $command_string, $args);
    
    return $soap_request;
  }

  /**
   * Returns command SOAP definition.
   * 
   * @param string $key SOAP command requested.
   * @return string SOAP definition.
   */
  private function get_command_string($key)
  {
    return self::$soap_commands_available[$key];
  }
  
  /**
   * Sends a SOAP command to device.
   * 
   * @param mixed $soap_request SOAP command.
   * @param string $soap_location URI required by SOAP service.
   * @return string device response.
   */
  private function execute_single_soap_request($soap_request, $soap_location)
  {
    return $this->soap_client->__doRequest($soap_request, $soap_location, '', self::SOAP_VERSION);
  }
  
  /**
   * Sends multiple SOAP commands to the device.
   * 
   * @param mixed $soap_requests SOAP commands array.
   * @param string $soap_location URI required by SOAP service.
   * @return string device response (Always returns the last command response.)
   */
  private function execute_multiple_soap_requests(array $soap_requests, $soap_location)
  {
    foreach($soap_requests as $soap_request){
      $result = $this->execute_single_soap_request($soap_request, $soap_location);
    }
    
    return $result;
  }
  
  /**
   * Parses SOAP request, replacing formal params with actual params.
   * 
   * @param array $valid_args valid args allowed by the class.
   * @param string $command_string SOAP request.
   * @param array $args actual args values required by SOAP request.
   * @return string SOAP request parsed.
   */  
  private function parse_command_string(array $valid_args, $command_string, array $args)
  {
    $parseable_args = array_map( function($item){ return '%' . $item . '%'; }, $valid_args );
    $parsed_command = str_replace($parseable_args, $args, $command_string);
    
    return $parsed_command;
  }
  
  /**
   * Tells if device response represents an empty response, represented by an empty XML string
   * (a string with just an open and end tags).
   * 
   * @param string $response device response.
   * @return boolean <b><code>true</code></b> if it is a empty response.
   */
  private function is_response_with_no_data($response)
  {
    if( is_null($response) ){
      return 0;
    }
    
    $response_xml = new \SimpleXMLElement($response);
    $response_items = $response_xml->count();
    
    return 0 === $response_items ? true : false;
  }
  
  /**
   * Generates a modified XML response with a NO DATA text.
   * 
   * @param string $response device response.
   * @return string modified XML response.
   */
  private function build_no_data_response($response)
  {
    is_null($response) ? $response = '<Response></Response>' : null;
    
    $pos = strpos($response, '>');
    $no_data_response = substr_replace($response, TADHelpers::XML_NO_DATA_FOUND, $pos + 1, false);
    
    return $no_data_response;
  }
}