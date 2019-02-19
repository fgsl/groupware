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
 * NotFound exception
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class NotFound extends ProgramFlow
{
    public function __construct($message, $code=404)
    {
        parent::__construct($message, $code);
    }
}