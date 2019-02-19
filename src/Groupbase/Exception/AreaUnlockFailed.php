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
 * AreaLocked exception
 * 
 * @package     Tinebase
 * @subpackage  Exception
 */
class AreaUnlockFailed extends AreaLocked
{
    /**
     * @var string _('Area could not b\\e unlocked')
     */
    protected $_title = 'Area could not be unlocked';

    /**
     * @param null $_message
     * @param int $_code
     */
    public function __construct($message, $code = 631)
    {
        parent::__construct($message, $code);
    }
}