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
 * Groupbase exception that is only thrown in the "normal" program flow
 *
 * this exception
 * - is not logged to sentry
 * - is logged with a higher level (NOTICE by default) to log
 *
 * 
 * @package     Tinebase
 * @subpackage  Exception
 */
class ProgramFlow extends Exception
{
    /**
     * send this to sentry?
     *
     * @var bool
     */
    protected $_logToSentry = false;

    /**
     * default log level for Tinebase_Exception::log()
     *
     * @var string
     */
    protected $_logLevelMethod = 'notice';
}
