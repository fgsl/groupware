<?php
namespace Fgsl\Groupware\Groupbase\Model\InputFilter;
use Zend\Filter\PregReplace;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * @package     Groupbase
 * @subpackage  InputFilter
 */
class RemoveWhitespace extends PregReplace 
{

    /**
     * Pattern to match
     * @var mixed
     */
    protected $_matchPattern = '/\s*/';

    /**
     * Replacement pattern
     * @var mixed
     */
    protected $_replacement = "";
}
