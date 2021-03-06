<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;

use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue\UnexpectedValue;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */


/**
 * Relation
 * 
 * filters own ids match result of related filter
 * 
 * <code>
 *      'contact'        => array('filter' => 'Groupbase\Model\Filter\Relation', 'options' => array(
 *          'related_model'     => 'Addressbook\Model\Contact',
 *          'filtergroup'    => 'Addressbook\Model\ContactFilter'
 *      )
 * </code>     
 * 
 * @package     Tinebase
 * @subpackage  Filter
 */
class Relation extends ForeignRecord
{
    /**
     * relation type filter data
     * 
     * @var array
     */
    protected $_relationTypeFilter = NULL;
    
    /**
     * get foreign controller
     * 
     * @return AbstractRecord
     */
    protected function _getController()
    {
        if ($this->_controller === NULL) {
            $this->_controller = AbstractRecord::getController($this->_options['related_model']);
        }
        
        return $this->_controller;
    }
    
    /**
     * get foreign filter group
     * 
     * @return FilterGroup
     */
    protected function _setFilterGroup()
    {
        if ($this->_valueIsNull) {
            return;
        }
        $filters = $this->_getRelationFilters();
        $this->_filterGroup = new $this->_options['filtergroup']($filters, $this->_operator);
    }
    
    /**
     * set options 
     *
     * @param array $_options
     */
    protected function _setOptions(array $_options)
    {
        if (! isset($_options['related_model'])) {
            throw new UnexpectedValue('related model is needed in options');
        }

        if (! isset($_options['filtergroup'])) {
            $_options['filtergroup'] = $_options['related_model'] . 'Filter';
        }
        
        parent::_setOptions($_options);
    }
    
    /**
     * appends sql to given select statement
     *
     * @param Select                $_select
     * @param AbstractSql $_backend
     */
    public function appendFilterSql($_select, $_backend)
    {
        if (isset($this->_options['own_model'])) {
            $ownModel = $this->_options['own_model'];
        } else {
            $ownModel = $_backend->getModelName();
        }
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' 
            . 'Adding Relation filter: ' . $ownModel . ' <-> ' . $this->_options['related_model']);

        $idField = (isset($this->_options['idProperty']) || array_key_exists('idProperty', $this->_options)) ? $this->_options['idProperty'] : 'id';
        $db = $_backend->getAdapter();
        $qField = $db->quoteIdentifier($_backend->getTableName() . '.' . $idField);

        if (!$this->_valueIsNull) {
            $this->_resolveForeignIds();
            $ownIds = $this->_getOwnIds($ownModel);

            if (empty($ownIds)) {
                $_select->where('1=0');
            } else {
                $_select->where($db->quoteInto("$qField IN (?)", $ownIds));
            }
        } else {
            $ownIds = $this->_getOwnIds($ownModel);
            if (empty($ownIds)) {
                $_select->where('1=1');
            } else {
                $_select->where($db->quoteInto("$qField NOT IN (?)", $ownIds));
            }
        }
    }
    
    /**
     * resolve foreign ids
     */
    protected function _resolveForeignIds()
    {
        if (! is_array($this->_foreignIds)) {
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' foreign filter values: ' 
                . print_r($this->_filterGroup->toArray(), TRUE));
            try {
                $this->_foreignIds = $this->_getController()->search($this->_filterGroup, null, false, true);
            } catch(Tinebase_Exception_AccessDenied $e) {
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' no access to related app ');
                $this->_foreignIds = [];
            }
        }

        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' foreign ids: ' 
            . print_r($this->_foreignIds, TRUE));
    }
    
    /**
     * returns own ids defined by relation filter
     * 
     * @param string $_modelName
     * @return array
     */
    protected function _getOwnIds($_modelName)
    {
        $filter = array(
            array('field' => 'own_model',     'operator' => 'equals', 'value' => $_modelName),
            array('field' => 'related_model', 'operator' => 'equals', 'value' => $this->_options['related_model'])
        );
        if (null !== $this->_foreignIds) {
            $filter[] = array('field' => 'related_id', 'operator' => 'in'    , 'value' => $this->_foreignIds);
        }
        if (isset($this->_options['type'])) {
            $filter[] = array('field' => 'type',       'operator' => 'equals', 'value' => $this->_options['type']);
        }

        $relationFilter = new Tinebase_Model_RelationFilter($filter);
        
        if ($this->_relationTypeFilter) {
            $typeValue = $this->_relationTypeFilter['value'];
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' 
                . 'Adding Relation type filter: ' . ((is_array($typeValue)) ? implode(',', $typeValue) : $typeValue));
            $relationFilter->addFilter($relationFilter->createFilter('type', $this->_relationTypeFilter['operator'], $typeValue));
        }
        $ownIds = Tinebase_Relations::getInstance()->search($relationFilter, NULL)->own_id;

        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' own ids: ' 
            . print_r($ownIds, TRUE));
        
        return $ownIds;
    }
    
    /**
     * get relation filters
     * 
     * @return array
     */
    protected function _getRelationFilters()
    {
        $filters = $this->_value;
        foreach ($filters as $idx => $filterData) {
            
            if (isset($filters[$idx]['field']) && $filters[$idx]['field'] === 'relation_type') {
                $this->_relationTypeFilter = $filters[$idx];
                unset($filters[$idx]);
            }
        }
        
        return $filters;
    }
    
    /**
     * returns filter group filters
     * 
     * @param  bool $_valueToJson resolve value for json api?
     * @param  array $_additionalFilters
     * @return array
     */
    protected function _getForeignFiltersForToArray($_valueToJson, $_additionalFilters = array())
    {
        $additionalFilters = ($this->_relationTypeFilter) ? array($this->_relationTypeFilter) : $_additionalFilters;
        $result = parent::_getForeignFiltersForToArray($_valueToJson, $additionalFilters);
        
        return $result;
    }
    
    /**
     * get filter information for toArray()
     * 
     * @return array
     */
    protected function _getGenericFilterInformation()
    {
        list($appName, $i, $modelName) = explode('_', $this->_options['related_model']);
            
        $result = array(
            'linkType'      => 'relation',
            'appName'       => $appName,
            'modelName'     => $modelName,
        );
        
        return $result;
    }
}
