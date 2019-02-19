<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Zend\Db\Adapter\Adapter;
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
 * FilterInt
 * 
 * filters one int in one property
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
class FilterInt extends AbstractFilter
{
    /**
     * @var integer value type to use in zend db where
     */
    protected $valueType = Adapter::INT_TYPE;

    /**
     * @var array list of allowed operators
     */
    protected $_operators = array(
        0 => 'equals',
        1 => 'startswith',
        2 => 'endswith',
        3 => 'greater',
        4 => 'less',
        5 => 'not',
        6 => 'in',
        7 => 'notin',
        8 => 'notnull',
        9 => 'isnull',
        10=> 'contains'
    );
    
    /**
     * @var array maps abstract operators to sql operators
     */
    protected $_opSqlMap = array(
        'equals'     => array('sqlop' => ' = ?'   ,     'wildcards' => '?'  ),
        'contains'   => array('sqlop' => ' LIKE ?',     'wildcards' => '%?%'),
        'startswith' => array('sqlop' => ' LIKE ?',     'wildcards' => '?%' ),
        'endswith'   => array('sqlop' => ' LIKE ?',     'wildcards' => '%?' ),
        'greater'    => array('sqlop' => ' > ?',        'wildcards' => '?'  ),
        'less'       => array('sqlop' => ' < ?',        'wildcards' => '?'  ),
        'not'        => array('sqlop' => ' NOT LIKE ?', 'wildcards' => '?'  ),
        'in'         => array('sqlop' => ' IN (?)',     'wildcards' => '?'  ),
        'notin'      => array('sqlop' => ' NOT IN (?)', 'wildcards' => '?'  ),
        'notnull'    => array('sqlop' => ' IS NOT NULL'                     ),
        'isnull'     => array('sqlop' => ' IS NULL'                         ),
    );
    
    /**
     * appends sql to given select statement
     *
     * @param Select                $_select
     * @param AbstractSql           $_backend
     */
    public function appendFilterSql($_select, $_backend)
    {
        // quote field identifier, set action and replace wildcards
        $field = $this->_getQuotedFieldName($_backend);
        $action = $this->_opSqlMap[$this->_operator];
        $value = $this->_replaceWildcards($this->_value);
        
        if (in_array($this->_operator, array('in', 'notin')) && ! is_array($value)) {
            $value = explode(' ', $this->_value);
        }
        
        if (in_array($this->_operator, array('equals', 'greater', 'less', 'in', 'notin'))) {
            $value = str_replace(array('%', '\\_'), '', $value);
            
            if (is_array($value) && empty($value)) {
                $_select->where('1=' . (substr($this->_operator, 0, 3) == 'not' ? '1/* empty query */' : '0/* impossible query */'));
            } elseif ($this->_operator == 'equals' && ($value === '' || $value === NULL || $value === false)) {
                $_select->where($field . 'IS NULL');
            } else {
                // finally append query to select object
                $_select->where($field . $action['sqlop'], $value, $this->valueType);
            }
        } else {
            // finally append query to select object
            $_select->where($field . $action['sqlop'], $value);
        }
        
        if (in_array($this->_operator, array('not', 'notin')) && $value !== '') {
            $_select->orWhere($field . ' IS NULL');
        }
    }
}
