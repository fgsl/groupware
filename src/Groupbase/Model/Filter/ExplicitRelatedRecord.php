<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Relations;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * explicit related record filter definition
 * 
 * filtergroup definition:
 * 
 * 'contract' => array('filter' => 'Tinebase_Model_Filter_ExplicitRelatedRecord', 'options' => array(
 *     'controller' => 'Sales_Controller_Contract',
 *     'filtergroup' => 'Sales_Model_ContractFilter',
 *     'own_filtergroup' => 'Timetracker_Model_TimeaccountFilter',
 *     'own_controller' => 'Timetracker_Controller_Timeaccount',
 *     'related_model' => 'Sales_Model_Contract',
 * ))
 * 
 * @package     Groupbase
 * @subpackage  Filter
 */
class ExplicitRelatedRecord extends Relation
{
    /**
     * returns own ids defined by relation filter
     *
     * @param string $_modelName
     * @return array
     */
    protected function _getOwnIds($_modelName)
    {
        if (! $this->_options['own_filtergroup']) {
            throw new InvalidArgument('own filter group has to be defined!');
        }
        if (! $this->_options['own_controller']) {
            throw new InvalidArgument('own controller has to be defined!');
        }

        $idProperty = 'id';
        $filtergroup = $this->_options['own_filtergroup'];
        $controller = $this->_options['own_controller'];

        if (!($this->_value[0]['value'])) {
            $relationFilter = new RelationFilter(array(
                array('field' => 'own_model',     'operator' => 'equals', 'value' => $_modelName),
                array('field' => 'related_model', 'operator' => 'equals', 'value' => $this->_options['related_model']),
            ));

            $notInIds = Relations::getInstance()->search($relationFilter, NULL)->own_id;
            $filter = new $filtergroup(array(

            ),'AND');

            // Deal with generic filtermodel!
            if ($this->_options['own_filtergroup'] === FilterGroup::class) {
                $filter->setConfiguredModel($_modelName);
            }

            $filter->addFilter(new Text(array('field' => $idProperty, 'operator' => 'notin', 'value' => $notInIds)));

            return $controller::getInstance()->search($filter, null, false, true);
        }

        return parent::_getOwnIds($_modelName);
    }

    /**
     * (non-PHPdoc)
     * @see Relation::toArray()
     */
    public function toArray($_valueToJson = false)
    {
        $ret = parent::toArray($_valueToJson);
        foreach($ret['value'] as &$filter) {
            if ($filter['field'] == ':id' && $filter['operator'] == 'equals' && is_string($filter['value']) && strlen($filter['value']) == 40) {
                $split = explode('_Model_', $this->_options['related_model']);
                $cname = $split[0] . '_Controller_' . $split[1];
                $fr = $cname::getInstance()->get($filter['value'], /* $_containerId = */ null, /* $_getRelatedData = */ false);
                $fr->relations = null;
                $filter['value'] = $fr->toArray();
            }
        }
        return $ret;
    }
}