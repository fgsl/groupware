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
 * Implementation of Zend_Filter_PregReplace
 * 
 * @package     Groupbase
 * @subpackage  InputFilter
 */
class CrlfConvert extends PregReplace {

    /**
     * Pattern to match
     * @var mixed
     */
    protected $_matchPattern = '/\r\n/';

    /**
     * Replacement pattern
     * @var mixed
     */
    protected $_replacement = "\n";
}
