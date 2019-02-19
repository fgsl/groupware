<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;

use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * FilterBool
 * 
 * filters one boolean in one property
 * 
 * @package     Tinebase
 * @subpackage  Filter
 */
class FilterBool extends AbstractFilter
{
    /**
     * use this as value to indicate that the boolfilter should not be applied at all
     * this can be handy if you need to set a filterline e.g. in UI without effect
     */
    const VALUE_NOTSET = '#NOTSET#';

    /**
     * @var array list of allowed operators
     */
    protected $_operators = array(
        0 => 'equals',
    );
    
    /**
     * @var array maps abstract operators to sql operators
     */
    protected $_opSqlMap = array(
        'equals'     => array('sqlop' => ' = ?'),
    );
    
    /**
     * appends sql to given select statement
     *
     * @param Select                $_select
     * @param AbstractSql $_backend
     */
     public function appendFilterSql($_select, $_backend)
     {
         if ($this->_value === self::VALUE_NOTSET) {
             return;
         }

         $action = $this->_opSqlMap[$this->_operator];
         
         $db = $_backend->getAdapter();
         
         // prepare value
         $value = $this->_value ? 1 : 0;
        
         if (! empty($this->_options['fields'])) {
             foreach ((array) $this->_options['fields'] as $fieldName) {
                 $quotedField = $db->quoteIdentifier(strpos($fieldName, '.') === false ? $_backend->getTableName() . '.' . $fieldName : $fieldName);
                 if ($value) {
                     $_select->where($quotedField . $action['sqlop'], $value);
                 } else {
                     $_select->orwhere($quotedField . $action['sqlop'], $value);
                 }
             }
         } else if (! empty($this->_options['leftOperand'])) {
             $_select->where($this->_options['leftOperand'] . $action['sqlop'], $value);
         } else {
             $_select->where($this->_getQuotedFieldName($_backend) . $action['sqlop'], $value);
         }
     }
}
