<?php
namespace TADPHP;

use TADPHP\Exceptions\ConnectionError;
use TADPHP\Exceptions\UnrecognizedCommand;
use TADPHP\Exceptions\UnrecognizedArgument;

use TADPHP\TADHelpers;

use Providers\TADSoap;
use Providers\TADZKLib;

/**
 * TAD: Time & Attendance Device is a class that implements some function presents in time and attendance device.
 * 
 * @author Jorge Cobis - email: jcobis@gmail.com / twitter: @cobisja
 * 
 * For developing purposes, it' been used Fingertec Q2i device (that it's been using were I work).
 * 
 * Methods exposed by TAD class have been tested using Q2i deviced only , that has a Linux 2.6.21 kernel (ZEM-600).
 * However, it's possible that TAD class works with similar devices, since most of them use the same SOAP API from
 * ZK Software.
 * 
 * There are some SOAP functions that it's suppossed,  according to the official docs (which incidentally it's
 * very limited and so poor!!!) must show a expected behaviour, but when they are invoked don't work like
 * it's expected, so they become useless (e.g. Restart SOAP call). For these situations, I found (after googling for
 * hours, and hours, and hours!!!), a PHP class named PHP_ZKLib (@link http://dnaextrim.github.io/php_zklib/ ) that
 * take a different approach to "talk to device": it uses UDP protocol at device standard port 4370.
 * 
 * PHP_ZKLib class it's been fully integrated, after a refactoring process to take out all duplicated code (DRY)
 * 
 * TAD Class has been tested as far as I could. If you check the test code coverage you'll notice that it does not
 * reach 100%. The reason is that it was not possible to write test fot PHP_ZKLib class. I have to admit that
 * I not have fully understanding about what it's done by some methods. I could not find technical information
 * about UDP protocol for ZK devices.
 * 
 * For practical purposes, you don't have to be worried about when to use TAD class or PHP_ZKLib class because
 * you only have to get a TAD instance (as shown below) and call any of methods available. The class decides
 * about when run the method invoked using TAD class or PHP_ZKLib class.
 * 
 * To get a TAD instance, you have call <code><b>get_instance()</b></code> method from <code><b>TADFactory</b></code> class.
 * 
 * Some examples:
 * 
 * - Get a TAD instance for device with ip = '192.168.100.156':
 * <code>
 * $b1 = (new TADFactory(['ip'=>'192.168.100.156']))->get_instance();
 * </code>
 * 
 * Getting device time and date:
 * <code>
 * $dt = $b1->get_date(); // method executed via TAD class.
 * </code>
 * 
 * Setting device date and time:
 * <code>
 * $r = $b1->set_date(['date'=>'2014-12-31', 'time'=>'23:59:59']); // method executed via PHP_ZKLib.
 * </code>
 * 
 * All device responses are in XML format. When you need to get them in a different format you can use
 * some utilitites (helpers) methods exposed by TAD class:
 * 
 * Some examples:
 * 
 * Get an array with logs of user with pin = 99999999:
 * <code>
 * $logs = TADHelpers::xml_to_array($b1->get_att_log(['pin'=>'99999999']);
 * </code>
 * 
 * If you want to filter logs by date:
 * <code>
 * $logs = $b1->get_att_log(['pin'=>'99999999']);
 * $logs = TADHelpers::filter_xml_by_date($logs, ['start_date'=>'2014-11-27', 'end_date'=>'2014-12-02']);
 * </code>
 * 
 * To get wich commands are available to run via TADSoap class and wich ones are run via PHP_ZKLib you have the
 * following 2 static methods <code><b>soap_commands_available</b></code> and <code><b>zklib_commands_available</b></code>
 *  
 * TAD class Limitations:
 * 
 * <li><code>set_user_template</code> method only works using BioBridge VX 9.0 algorithm (using VX 10, the device freezes!!!)</li>.
 * <li><code>set_user_info</code> methos works properly only if the user you create don't exist previously</li>.
 * <li>If you want to edit user info, you have to delete the user first, then you have to create it using <code>set_user_info</code>.
 * 
 */
class TAD
{
  /**
   * TADSoap commands available array.
   * 
   * @var array
   */  
  static private $soap_commands_available = [
      'get_date', 'get_att_log', 'get_user_info',
      'get_all_user_info', 'get_user_template',
      'get_combination', 'get_option', 'set_date',
      'set_user_info', 'set_user_template',
      'delete_user', 'delete_template', 'delete_data',
      'delete_user_password', 'refresh_db', 'restart'      
  ];
  
  /**
   * PHP_ZKLib commands available array (Commands defined here have highest priority!!!).
   * 
   * @var array
   */
  static private $zklib_commands_available = [
      'set_date', 'enable_device', 'disable_device',
      'restart', 'poweroff', //'set_user_info',
      'get_free_sizes', 'delete_admin'
  ];  
  
  /**
   * Valid commands args array.
   * 
   * @var array
   */
  static private $parseable_args = [
      'com_key', 'pin', 'time', 'template',
      'name', 'password', 'group', 'privilege',
      'card', 'pin2', 'tz1', 'tz2', 'tz3',
      'finger_id', 'option_name', 'date',
      'size', 'valid', 'value'
  ];
  
  /**
   * Device ip address.
   * 
   * @var string
   */
  private $ip;
  
  /**
   * Device internal id.
   * 
   * @var mixed
   */
  private $internal_id;
  
  /**
   * Device description (just for info purposes).
   * 
   * @var string
   */
  private $description;
  
  /**
   * Security communication code (required for SOAP functions calls).
   * 
   * @var mixed
   */
  private $com_key;
  
  /**
   * Connection timeout in seconds.
   * 
   * @var int 
   */
  private $connection_timeout;
  
  /**
   * Holds a <code>TADSoap</code> instance to talk to device via SOAP.
   * 
   * @var object
   */
  private $tad_soap;
  
  /**
   * Holds <code>PHP_ZKLib</code> instance to talk to device via UDP.
   * 
   * @var object 
   */
  private $zklib;
  

  /**
   * Returns an array with SOAP commands list available.
   * 
   * @return array SOAP commands list.
   */  
  static public function soap_commands_available(array $options=[])
  {
    return TADSoap::get_commands_available($options);
  }
  
  /**
   * Returns an array with PHP_ZKLib commands available.
   * 
   * @return array PHP_ZHLib commands list.
   */  
  static public function zklib_commands_available()
  {
    return TADZKLib::get_commands_available();
  }  
  
  /**
   * Returns valid commands arguments list.
   * 
   * @return array arguments list.
   */
  static public function get_valid_commands_args()
  {
    return self::$parseable_args;
  }
  
  /**
   * Transform an XML string in JSON format.
   * 
   * @param string $xml_string XML string.
   * @return string XML string in JSON format.
   */
  static public function xml_to_json($xml_string='')
  {
    return TADHelpers::xml_to_json($xml_string);
  }
  
  /**
   * Transform an XML string in a array format.
   * 
   * @param string $xml_string XML string
   * @return array XML string transformed in an associative array.
   */
  static public function xml_to_array($xml_string='')
  {
    return TADHelpers::xml_to_array($xml_string);
  }
  
  /**
   * Transform an array in a XML string.
   * 
   * @param array $data array to be transformed.
   * @param string $root_tag root XML tag to be used in the transformation process.
   * @return string XML string generated.
   */
  static public function array_to_xml(array $data, $root_tag='<root/>')
  {
    return TADHelpers::array_to_xml(new \SimpleXMLElement($root_tag), $data);
  }
  
  /**
   * Get a new TAD class instance.
   * 
   * @param TADSoap $soap_provider code><b>TADSoap</b></code> class instance.
   * @param TADZKLib $zklib_provider <code><b>ZKLib</b></code> class instance.
   * @param array $options device parameters. 
   */
  public function __construct(TADSoap $soap_provider, TADZKLib $zklib_provider, array $options=[])
  {
    $this->ip = $options['ip'];
    $this->internal_id = $options['internal_id'];
    $this->com_key = $options['com_key'];
    $this->description = $options['description'];
    $this->connection_timeout = $options['connection_timeout'];
    
    $this->tad_soap = $soap_provider;
    $this->zklib = $zklib_provider;
  }

  /**
   * Magic __call method overriding to define in runtime the methods should be called based on method invoked.
   * (Something like Ruby metaprogramming :-P). In this way, we decrease the number of methods required
   * (usually should be one method per SOAP or PHP_ZKLib command exposed).
   * 
   * Note:
   * 
   * Those methods that add, update o delete device information, call SOAP method <b><code>refresh_db()</code></b>
   * to properly update device database.
   * 
   * @param string $command command to be invoked.
   * @param array $args commands args.
   * @return string device response in XML format.
   * @throws ConnectionError.
   * @throws UnrecognizedCommand.
   * @throws UnrecognizedArgument.
   */
  public function __call($command, array $args)
  {
    $command_args = count($args) === 0 ? [] : array_shift($args);
    $this->check_for_connection() && $this->check_for_valid_command($command) && $this->check_for_unrecognized_args($command_args);
    
    if( in_array($command, self::$zklib_commands_available) ){      
      $response = $this->execute_command_via_zklib($command, $command_args);
    }
    else {
      $response = $this->execute_command_via_tad_soap($command, $command_args);
    }

    $this->check_for_refresh_tad_db($command);
    
    return $response;
  }
  
  /**
   * Send a command to device using a <code>TADSoap</code> instance class.
   * 
   * @param string $command command to be sending.
   * @param array $args command args.
   * @return string device response.
   */
  public function execute_command_via_tad_soap($command, array $args=[])
  {
    return $this->tad_soap->execute_soap_command(
            $command,
            array_merge( ['com_key' => $this->get_com_key()], $args ),
            self::$parseable_args
    );
  }
  
  /**
   * Send a command to device using <code>PHP_ZKLib</code> class.
   *
   * Because responses generate by PHP_ZKLib class are not in XML format, it is used <code>build_command_response</code>
   * to build an XML response, just to keep the TAD class behavior. For this purpose, the method uses class constans
   * <code>ZKLib::XML_SUCCESS_RESPONSE</code> and <code>ZKLib::XML_FAIL_RESPONSE</code>.
   * 
   * @param string $command command to be sending.
   * @param array $args command args.
   * @return string string device response.
   */
  public function execute_command_via_zklib($command, array $args=[])
  {
    $command_args = TADHelpers::config_array_items(self::$parseable_args, $args, ['include_keys'=>true]);
    $response = $this->zklib->{$command}($command_args);
    
    return $response;
  }
  
  /**
   * Returns device IP address.
   * 
   * @return string IP address.
   */
  public function get_ip()
  {
    return $this->ip;
  }

  /**
   * Returns internal device code.
   * 
   * @return int internal code.
   */
  public function get_internal_id()
  {
    return $this->internal_id;
  }

  /**
   * Returns device comm code.
   * 
   * @return int code.
   */
  public function get_com_key()
  {
    return $this->com_key;
  }

  /**
   * Returns device string description.
   * 
   * @return string device description.
   */
  public function get_description()
  {
    return $this->description;
  }
  
  /**
   * Tells is device is ready (alive) to process requests.
   *
   * @return boolean <b>true</b> if device is alive, <b>false</b> otherwise.
   */
  public function is_alive()
  {
    $handler = curl_init( $this->get_ip() );
    curl_setopt_array( $handler, [ CURLOPT_TIMEOUT => $this->connection_timeout, CURLOPT_RETURNTRANSFER => true ] );
    $response = curl_exec ($handler);  
    curl_close($handler);
    
    return (boolean)$response;
  }  
  
  /**
   * Throws an Exception when device is not alive.
   * 
   * @return boolean <b><code>true</code></b> if there is a connection with the device.
   * @throws ConnectionError
   */
  private function check_for_connection()
  {
    if( !$this->is_alive() ){
      throw new ConnectionError( 'Imposible iniciar conexión con dispositivo ' . $this->get_ip() );
    }
    
    return true;
  }
  
  /**
   * Tells if the command requested is in valid commands set.
   * 
   * @param string $command command requested.
   * @return boolean <code><b>true</b></code> if the command es known by the class.
   * @throws UnrecognizedCommand
   */
  private function check_for_valid_command($command)
  {
    $tad_commands = array_merge( self::$zklib_commands_available, self::$soap_commands_available );
    
    if(!in_array($command, $tad_commands)){
      throw new UnrecognizedCommand("Comando $command no reconocido!");
    }
    
    return true;
  }
  
  /**
   * Tells if the arguments supplied are in valid args set.
   * 
   * @param array $args args array to be verified.
   * @return <b><code>true</code></b> if all args supplied are valid (known by the class).
   * @throws TAD\Exceptions\UnrecognizedArgument
   */
  private function check_for_unrecognized_args(array $args)
  {
    if( 0 !== count( $unrecognized_args = array_diff( array_keys($args), self::$parseable_args ) ) ){
      throw new UnrecognizedArgument( 'Parámetro(s) desconocido(s): ' . join(', ', $unrecognized_args) );
    }
    
    return true;
  }
  
  /**
   * Tells if it's necessary to do a device database update. To do this, the method verified the command
   * executed to see if it did any adding, deleting or updating of database device. In that case, a
   * <code>refesh_db</code> command is executed.
   * 
   * @param string $command_executed command executed.
   */
  private function check_for_refresh_tad_db($command_executed)
  {
    preg_match('/^(set_|delete_)/', $command_executed) && $this->execute_command_via_tad_soap('refresh_db', []);
  }
}