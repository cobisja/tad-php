<?php
namespace TADPHP;

use TADPHP\TAD;
use Providers\TADZKLib;
use Providers\TADSoap;

class TADFactory
{
  private $options;
  
  /**
   * Registra los valores de los atributos de las clases <code>TAD\TAD</code> y <code>Providers\ZKLib</code>.
   * 
   * @param array $options valores de los atributos.
   */
  public function __construct(array $options = [])
  {
    $this->options  = $options;
  }
  
  /**
   * Retorna una instancia de la clase <code><b>TAD\TAD</b></code>.
   * 
   * @return TAD instancia de la clase.
   */
  public function get_instance()
  {
    $options = $this->options;    
    $this->set_options($this->get_default_options(), $options);
    
    $soap_options = [ 
        'location' => "http://{$options['ip']}/iWsService",
        'uri' => 'http://www.zksoftware/Service/message/',
        'connection_timeout' => $options['connection_timeout'],
        'exceptions' => false,
        'trace' => true
    ];
    
    $soap_client = new \SoapClient( null, $soap_options );

    return new TAD( new TADSoap( $soap_client, $soap_options ), new TADZKLib( $options ), $options );
  }
  
  /**
   * Retorna un arreglo con valores para las opciones por defecto con las que se incializan
   * las clases <code>TAD\TAD</code> y <code>Providers\ZKLib</code>.
   * 
   * @return array arreglo con valores.
   */
  private function get_default_options()
  {
    $default_options['ip'] = '169.254.0.1';
    $default_options['internal_id'] = 1;
    $default_options['com_key'] = 0;
    $default_options['description'] = 'N/A';
    $default_options['connection_timeout'] = 5;
    $default_options['soap_port'] = 80;
    $default_options['udp_port'] = 4370;
    
    return $default_options;
  }
  
  /**
   * Genera un arreglo alterando los valores por defecto que se indican.
   * 
   * @param type $base_options arreglo con los valores por defecto.
   * @param type $options arreglo con los valores por defecto que deben ser alterados.
   */
  private function set_options($base_options, &$options)
  {
    foreach ($base_options as $key => $default)
    {
      !isset($options[$key]) ? $options[$key] = $default : null;
    }
  }  
}