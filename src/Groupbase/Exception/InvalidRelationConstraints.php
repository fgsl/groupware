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
 * Tinebase exception with exception data
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class InvalidRelationConstraints extends ProgramFlow
{
    /**
     * the title of the Exception (may be shown in a dialog)
     *
     * @var string
     */
    protected $_title = 'Invalid Relations'; // _('Invalid Relations')
    
    /**
     * construct
     *
     * @param string $_message
     * @param integer $_code
     * @return void
     */
     public function __construct($_message = "You tried to create a relation which is forbidden by the constraints config of one of the models.", $_code = 912) {
        // _("You tried to create a relation which is forbidden by the constraints config of one of the models.")
            parent::__construct($_message, $_code);
    }
}
