<?php
namespace Fgsl\Groupware\Groupbase\Exception;

use Fgsl\Groupware\Groupbase\Exception;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Backend exception
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class Backend extends Exception
{
    /**
    * @param string $message
    * @param int $code (default: 503 Service Unavailable)
    */
    public function __construct($message, $code = 503)
    {
        parent::__construct($message, $code);
    }
}