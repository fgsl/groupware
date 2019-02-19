<?php
namespace Fgsl\Groupware\Groupbase\Server\Plugin;

use Fgsl\Groupware\Groupbase\Server\Cli as ServerCli;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * server plugin to dispatch CLI requests
 *
 * @package     Groupbase
 * @subpackage  Server
 */
class Cli implements PluginInterface
{
    /**
     * (non-PHPdoc)
     * @see PluginInterface::getServer()
     */
    public static function getServer(\Zend\Http\Request $request)
    {
        /**************************** CLI API *****************************/
        if (php_sapi_name() == 'cli') {
            return new ServerCli();
        }
    }
}
