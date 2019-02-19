<?php
namespace Fgsl\Groupware\Groupbase\Exception\Expressive;

use Fgsl\Groupware\Groupbase\Exception\ProgramFlow;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Http Status exception
 *
 * @package     Groupbase
 * @subpackage  Exception
 */
class HttpStatus extends ProgramFlow
{
    /**
     * the constructor
     *
     * @param string $_message
     * @param int $_code
     */
    public function __construct($message, $code)
    {
        parent::__construct($message, $code);
    }
}