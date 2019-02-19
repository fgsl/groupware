<?php
namespace Fgsl\Groupware\Groupbase\Exception\Backend\Database;

use Fgsl\Groupware\Groupbase\Exception\Backend\Database;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Database Backend exception
 * 
 * @package     Groupware
 * @subpackage  Exception
 */
class LockTimeout extends Database
{
    /**
     * @param string $message
     * @param int $code (default: 409 concurrency conflict)
     */
    public function __construct($message, $code = 409)
    {
        parent::__construct($message, $code);
    }
}