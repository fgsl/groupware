<?php
namespace Fgsl\Groupware\Groupbase\Date;

use Fgsl\Groupware\Groupbase\Exception\Exception as GroupbaseException;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * @package    Groupware
 * @subpackage Date
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Exception extends GroupbaseException
{
    protected $operand = null;
    
    public function __construct($message, $code = 0, $e = null, $op = null)
    {
        $this->operand = $op;
        parent::__construct($message, $code, $e);
    }
    
    public function getOperand()
    {
        return $this->operand;
    }
}