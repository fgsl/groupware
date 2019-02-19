<?php
namespace Fgsl\Groupware\Groupbase\Server\Plugin;

use Fgsl\Groupware\Groupbase\Server\ServerInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Server Interface for plugins
 *
 * @package     Groupbase
 * @subpackage  Server
 */
interface PluginInterface
{
    /**
     * return server class of $request matches specific criteria
     * 
     * @param \Zend\Http\Request $request
     * @return ServerInterface
     */
    public static function getServer(\Zend\Http\Request $request);
}
