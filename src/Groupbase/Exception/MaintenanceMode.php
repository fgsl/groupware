<?php
namespace Fgsl\Groupware\Groupbase\Exception;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * MaintenanceMode
 *
 * @package     Groupbase
 * @subpackage  Exception
 */
class MaintenanceMode extends ProgramFlow
{
    public function __construct($message = 'Installation is in maintenance mode. Please try again later', $code = 503)
    {
        parent::__construct($message, $code);
    }
}
