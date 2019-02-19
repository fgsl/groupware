<?php
namespace Fgsl\Groupware\Groupbase\Server;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Server Interface with handle function
 * 
 * @package     Groupbase
 * @subpackage  Server
 */
interface ServerInterface
{
    /**
     * handler for tine requests
     * 
     * @param  \Zend\Http\Request  $request
     * @param  string       $body
     * @return boolean
     */
    public function handle(\Zend\Http\Request $request = null, $body = null);
    
    /**
     * returns request method
     * 
     * @return string|NULL
     */
    public function getRequestMethod();
}
