<?php
namespace Fgsl\Groupware\Groupbase\Exception\Record;

use Fgsl\Groupware\Groupbase\Exception;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * record exception
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class Validation extends Exception 
{
    /**
    * the constructor
    *
    * @param string $_message
    * @param int $_code (default: 505 Validation)
    */
    public function __construct($message, $code = 505)
    {
        parent::__construct($message, $code);
    }
}
