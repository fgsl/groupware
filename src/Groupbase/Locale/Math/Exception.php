<?php
namespace Fgsl\Groupware\Groupbase\Application;

use Fgsl\Groupware\Groupbase\Locale\Exception as LocaleException;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */


/**
 * @package    Groupbase
 * @subpackage Locale
 */
class Exception extends LocaleException
{
    protected $op1 = null;
    protected $op2 = null;
    protected $result = null;
    
    public function __construct($message, $op1 = null, $op2 = null, $result = null)
    {
        $this->op1 = $op1;
        $this->op2 = $op2;
        $this->result = $result;
        parent::__construct($message);
    }
    
    public function getResults()
    {
        return array($this->op1, $this->op2, $this->result);
    }
}
