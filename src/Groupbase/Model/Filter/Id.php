<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;

use Fgsl\Groupware\Groupbase\Controller\AbstractRecord;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Psr\Log\LogLevel;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Id
 * 
 * filters one or more ids
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
class Id extends AbstractFilter
{
    /**
     * @var array list of allowed operators
     */
    protected $_operators = array(
        'equals',
        'not',
        'in',
        'notin',
        'isnull',
    );
    
    /**
     * @var array maps abstract operators to sql operators
     */
    protected $_opSqlMap = array(
        'equals'     => array('sqlop' => ' = ?'   ),
        'not'        => array('sqlop' => ' != ?'  ),
        'in'         => array('sqlop' => ' IN (?)'),
        'notin'      => array('sqlop' => ' NOT IN (?)'),
        'isnull'     => array('sqlop' => ' IS NULL'),
    );
    
    /**
     * controller for record resolving
     * 
     * @var AbstractRecord
     */
    protected $_controller = NULL;
    
    /**
     * appends sql to given select statement
     *
     * @param Select                $_select
     * @param AbstractSql $_backend
     */
    public function appendFilterSql($_select, $_backend)
    {
        $action = $this->_opSqlMap[$this->_operator];
         
        if (empty($this->_value) && $this->_value != '0') {
             // prevent sql error
             if ($this->_operator == 'in' || $this->_operator == 'equals') {
                 if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                     . ' Empty value with "' . $this->_operator . '"" operator (model: '
                     . (isset($this->_options['modelName']) ? $this->_options['modelName'] : 'unknown / no modelName defined in filter options'). ')');
                 $_select->where('1=0');
             } else if ($this->_operator == 'not') {

                 $field = $this->_getQuotedFieldName($_backend);
                 $_select->where($field . " != '' AND " . $field . " IS NOT NULL");
            }
        } else if ($this->_operator == 'equals' && is_array($this->_value)) {
             if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                 . ' Unexpected array value with "equals" operator (model: ' 
                 . (isset($this->_options['modelName']) ? $this->_options['modelName'] : 'unknown / no modelName defined in filter options') . ')');
             $_select->where('1=0');
         } else {
             $type = $this->_getFieldType($_backend);
             $this->_enforceValueType($type);
             
             $field = $this->_getQuotedFieldName($_backend);
             // finally append query to select object
             $_select->where($field . $action['sqlop'], $this->_value, $type);
         }
     }
     
     /**
      * get field type from schema
      * 
      * @param AbstractSql $backend
      */
     protected function _getFieldType($backend)
     {
         $schema = $backend->getSchema();
         if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
             . ' schema: ' . print_r($schema, TRUE));
         
         $type = isset($schema[$this->_field]) ? $schema[$this->_field]['DATA_TYPE'] : NULL;
         return $type;
     }
     
     /**
      * enforce value typecast
      * 
      * @param string $type
      * 
      * @todo add more type strings / move to db adapter?
      */
     protected function _enforceValueType($type)
     {
         switch (strtoupper($type)) {
             case 'VARCHAR':
             case 'TEXT':
                 $this->_enforceStringValue();
                 break;
             case 'INTEGER':
             case 'TINYINT':
             case 'SMALLINT':
             case 'INT':
                 $this->_enforceIntValue();
                 break;
             default:
                 // do not cast / enforce type
         }
     }
     
    /**
     * returns array with the filter settings of this filter
     *
     * @param  bool $_valueToJson resolve value for json api?
     * @return array
     */
    public function toArray($_valueToJson = false)
    {
        $result = parent::toArray($_valueToJson);
        
        if ($_valueToJson) {
            if (is_array($result['value'])) {
                foreach ($result['value'] as $key => $value) {
                    $result['value'][$key] = $this->_resolveRecord($value);
                }
            } else {
                $result['value'] = $this->_resolveRecord($result['value']);
            }
        }
        
        return $result;
    }
    
    /**
     * enforce string data type for correct sql quoting
     */
    protected function _enforceStringValue()
    {
        if (is_array($this->_value)) {
            foreach ($this->_value as &$value) {
                $value = (string) $value;
            }
        } else {
            $this->_value = (string) $this->_value;
        }
    }
    
    /**
     * enforce integer data type for correct sql quoting
     */
    protected function _enforceIntValue()
    {
        if (is_array($this->_value)) {
            foreach ($this->_value as &$value) {
                if (! is_numeric($value)) {
                    throw new UnexpectedValue("$value is not a number");
                } 
                $value = (int) $value;
            }
        } else {
            if (! is_numeric($this->_value)) {
                throw new UnexpectedValue("$this->_value is not a number");
            } 
            $this->_value = (int) $this->_value;
        }
    }
    
    /**
     * get controller
     * 
     * @return AbstractRecord|null
     */
    protected function _getController()
    {
        if ($this->_controller === null) {
            if (isset($this->_options['controller'])) {
                $cname = $this->_options['controller'];
                $this->_controller = $cname::getInstance();
            } elseif (isset($this->_options['modelName'])) {
                $this->_controller = Core::getApplicationInstance($this->_options['modelName']);
            } else {
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__
                    . ' No modelName or controller defined in filter options, can not resolve record.');
                return null;
            }
        }
        
        return $this->_controller;
    }
    
    /**
     * resolves a record
     * 
     * @param string $value
     * @return array|string
     */
    protected function _resolveRecord($value)
    {
        $controller = $this->_getController();
        if ($controller === NULL) {
            return $value;
        }
        
        try {
            if (method_exists($controller, 'get')) {
                $recordArray = $controller->get($value, /* $_containerId = */ null, /* $_getRelatedData = */ false)->toArray();
            } else {
                Core::getLogger()->NOTICE(__METHOD__ . '::' . __LINE__ . ' Controller ' . get_class($controller) . ' has no get method');
                return $value;
            }
        } catch (\Exception $e) {
            $recordArray = $value;
        }
        
        return $recordArray;
    }
}
