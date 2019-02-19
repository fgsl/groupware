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
 * Login Failed Exception
 *
 * @package     Tinebase
 * @subpackage  Exception
 */
class MonthFormat extends ProgramFlow
{
    /**
     * the title of the Exception (may be shown in a dialog)
     *
     * @var string
     */
    protected $_title = 'Wrong month format!'; // _('Wrong month format!')
    
    /**
     * @see \Exception
     */
    protected $message = 'The month must have the format YYYY-MM!'; //_('The month must have the format YYYY-MM!')
    
    /**
     * @see \Exception
    */
    protected $code = 913;
}
