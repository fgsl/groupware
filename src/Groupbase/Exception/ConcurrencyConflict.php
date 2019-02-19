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
 * Concurrency Conflict exception
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class ConcurrencyConflict extends ProgramFlow
{
    protected $code = 409;
}