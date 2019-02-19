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
 * AccessDenied exception
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class AccessDenied extends ProgramFlow
{
    /**
     * the constructor
     * 
     * @param string $message
     * @param int $code
     */
    public function __construct($message, $code = 403)
    {
        parent::__construct($message, $code);
    }
}
