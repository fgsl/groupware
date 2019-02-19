<?php
namespace Fgsl\Groupware\Groupbase\Exception\Backend;

use Fgsl\Groupware\Groupbase\Exception\Backend;

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
 * @package     Tinebase
 * @subpackage  Exception
 */
class Database extends Backend
{
    /**
     * the constructor
     * 
     * @param string $_message
     * @param int $_code (default: 503 Service Unavailable)
     */
    public function __construct($message, $code = 503)
    {
        parent::__construct($message, $code);
    }
}