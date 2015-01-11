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

use TADPHP\Providers\TADSoap;
use TADPHP\Providers\TADZKLib;
use TADPHP\TAD;

class TADFactory
{
    private $options;

    /**
     * Registers attributes values for <code>TAD\TAD</code> and <code>Providers\ZKLib</code> classes.
     *
     * @param array $options attributes values.
     */
    public function __construct(array $options = [])
    {
        $this->options  = $options;
    }

    /**
     * Returns an <code><b>TAD\TAD</b></code> class instance.
     *
     * @return TAD class instance.
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

        $soap_client = new \SoapClient(null, $soap_options);

        return new TAD(
            new TADSoap($soap_client, $soap_options),
            new TADZKLib($options),
            $options
        );
    }

    /**
     * Returns a default values array used by <code>TAD\TAD</code> y <code>Providers\ZKLib</code> classes.
     *
     * @return array default values.
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
        $default_options['encoding'] = 'iso8859-1';

        return $default_options;
    }

    /**
     * Set all array items to a known default values.
     *
     * @param array $base_options default values
     * @param array $options default values to be changed to a known values.
     */
    private function set_options(array $base_options, array &$options)
    {
        foreach ($base_options as $key => $default) {
            !isset($options[$key]) ? $options[$key] = $default : null;
        }
    }
}
