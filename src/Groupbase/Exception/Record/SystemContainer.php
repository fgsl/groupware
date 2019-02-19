<?php
namespace Fgsl\Groupware\Groupbase\Exception\Backend\Database;

use Fgsl\Groupware\Groupbase\Exception\Backend\Database;
use Fgsl\Groupware\Groupbase\Exception\SystemGeneric;
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
class SystemContainer extends SystemGeneric
{
    /**
     * @var string _('System Container')
     */
    protected $_title = 'System Container';
    
   /**
    * the constructor
    * _('This is a system container which could not be deleted!')
    * 
    * @param string $_message
    * @param int    $_code 
    */
    public function __construct($message = 'This is a system container which could not be deleted!', $code=600)
    {
        parent::__construct($message, $code);
    }
}
