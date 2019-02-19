<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Fgsl\Groupware\Groupbase\Model\AdvancedSearchTrait;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Exception\Record\NotDefined;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Query
 * 
 * filters for all of the given filterstrings if it is contained in at least 
 * one of the defined fields
 * 
 * -> allow search for all Müllers who live in Munich but not all Müllers and all people who live in Munich
 * 
 * The fields to query in _must_ be defined in the options key 'fields'
 * The value string is space-exploded into multiple filterstrings
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
class Query extends FilterGroup
{
    use AdvancedSearchTrait;

    protected $_field;
    protected $_value;
    protected $_operator;

    /**
     * constructs a new filter group
     *
     * @param  array $_data
     * @param  string $_condition {AND|OR}
     * @param  array $_options
     * @throws InvalidArgument
     */
    public function __construct(array $_data = array(), $_condition = '', $_options = array())
    {
        if (count($_options) > 0) {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ .
                ' Given options are not used ... put options in $_data[\'options\']');
        }

        $condition = (0 === strpos($_data['operator'], 'not'))
            ? FilterGroup::CONDITION_AND
            : FilterGroup::CONDITION_OR;

        parent::__construct(array(),
            $condition,
            $_data['options']);

        if (isset($_data['id'])) {
            $this->setId($_data['id']);
        }
        if (isset($_data['label'])) {
            $this->setLabel($_data['label']);
        }

        $this->_field = $_data['field'];
        $this->_value = $_data['value'];
        $this->_operator = $_data['operator'];

        if (!empty($this->_value)) {
            $queries = is_array($this->_value) ? $this->_value : explode(' ', $this->_value);

            /** @var FilterGroup $parentFilterGroup */
            $parentFilterGroup = $this->_options['parentFilter'];
            /** @var FilterGroup $innerGroup */
            $innerGroup = new FilterGroup(array(),
                FilterGroup::CONDITION_AND);

            switch ($this->_operator) {
                case 'contains':
                case 'notcontains':
                case 'equals':
                case 'not':
                case 'startswith':
                case 'endswith':
                    foreach ($queries as $query) {
                        $subGroup = $this->_getSubfilterGroup($parentFilterGroup, $query, $condition);
                        $innerGroup->addFilterGroup($subGroup);
                    }
                    break;
                case 'notin':
                case 'in':
                    $this->_addFilterToInnerGroup($parentFilterGroup, $queries, $innerGroup);
                    break;
                default:
                    throw new InvalidArgument('Operator not defined: ' . $this->_operator);
            }

            $this->addFilterGroup($innerGroup);

            if (isset($this->_options['relatedModels']) && isset($this->_options['modelName'])) {
                $relationFilter = $this->_getAdvancedSearchFilter($this->_options['modelName'],
                    $this->_options['relatedModels']);
                if (null !== $relationFilter) {
                    $this->addFilter($relationFilter);
                }
            }
        }
    }

    /**
     * @param FilterGroup $parentFilterGroup
     * @param string $query
     * @param $condition
     * @return FilterGroup
     */
    protected function _getSubfilterGroup(FilterGroup $parentFilterGroup, $query, $condition)
    {
        $subGroup = new FilterGroup(array(), $condition);
        foreach ($this->_options['fields'] as $field) {
            $filter = $parentFilterGroup->createFilter($field, $this->_operator, $query);
            $this->_addFilterToGroup($subGroup, $filter);
        }


        return $subGroup;
    }

    /**
     * @param FilterGroup $parentFilterGroup
     * @param array $queries
     * @param FilterGroup $innerGroup
     */
    protected function _addFilterToInnerGroup(
        FilterGroup $parentFilterGroup,
        $queries,
        FilterGroup $innerGroup)
    {
        foreach ($this->_options['fields'] as $field) {
            $filter = $parentFilterGroup->createFilter($field, $this->_operator, $queries);
            $this->_addFilterToGroup($innerGroup, $filter);
        }
    }

    /**
     * @param FilterGroup    $group
     * @param AbstractFilter $filter
     */
    protected function _addFilterToGroup(FilterGroup $group, AbstractFilter $filter)
    {
        if (in_array($this->_operator, $filter->getOperators())
            || $filter instanceof ForeignRecord
        ) {
            if ($filter instanceof FullText) {
                if (! $filter->isQueryFilterEnabled()) {
                    return;
                }
            }

            $group->addFilter($filter);
        } else {
            if (Core::isLogLevel(LogLevel::NOTICE)) {
                Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ .
                    ' field: ' . $this->_field . ' => filter: ' . get_class($filter)
                    . ' doesn\'t support operator: ' . $this->_operator . ' => not applying filter!');
            }
        }
    }

    /**
     * returns fieldname of this filter
     *
     * @return string
     */
    public function getField()
    {
        return $this->_field;
    }

    /**
     * gets value
     *
     * @return  mixed
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * gets operator
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->_operator;
    }

    /**
     * set options 
     *
     * @param  array $_options
     * @throws NotDefined
     * @throws UnexpectedValue
     */
    protected function _setOptions(array $_options)
    {
        if (empty($_options['fields'])) {
            throw new NotDefined('Fields must be defined in the options of a query filter');
        }
        if (!isset($_options['parentFilter']) || !is_object($_options['parentFilter'])) {
            throw new UnexpectedValue('parentFilter needs to be set in options (should be done by parent filter group)');
        }
        
        parent::_setOptions($_options);
    }

    /**
     * returns array with the filter settings of this filter
     *
     * @param  bool $_valueToJson resolve value for json api?
     * @return array
     */
    public function toArray($_valueToJson = false)
    {
        $result = array(
            'field'     => $this->_field,
            'operator'  => $this->_operator,
            'value'     => $this->_value
        );

        if ($this->_id) {
            $result['id'] = $this->_id;
        }
        if ($this->_label) {
            $result['label'] = $this->_label;
        }

        return $result;
    }
}
