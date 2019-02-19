<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Zend\Db\Adapter\Adapter;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * Float
 * 
 * filters one float in one property
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
class Float extends FilterInt
{
    /**
     * @var integer value type to use in zend db where
     */
    protected $valueType = Adapter::FLOAT_TYPE;
}
