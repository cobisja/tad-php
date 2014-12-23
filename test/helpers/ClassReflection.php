<?php
namespace Test\Helpers;

abstract class ClassReflection
{
  /**
   * Permite invocar a métodos protegidos (protected) o privados (private) de una clase.
   * 
   * Código tomado del sitio web @link https://jtreminio.com/2013/03/unit-testing-tutorial-part-3-testing-protected-private-methods-coverage-reports-and-crap/
   *
   * @param object &$object instancia del objeto del cual se desean invocar los métodos.
   * @param string $method_name método a invocar
   * @param array  $parameters array de parámetros a ser pasados al método.
   *
   * @return mixed Resultado generado por el método invocado.
   */
  static public function invoke_method(&$object, $method_name, array $parameters = [])
  {
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($method_name);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $parameters);
  }
}