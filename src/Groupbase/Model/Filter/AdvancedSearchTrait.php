<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Relations;
use Fgsl\Groupware\Groupbase\Model\Filter\Id;
use Fgsl\Groupware\Groupbase\Preference;
use Fgsl\Groupware\Groupbase\Model\Filter\RelationFilter;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * AdvancedSearchTrait
 *
 * trait to share advanced search filter between abstract filter and query filter extending filter group
 *
 * @package     Groupbase
 * @subpackage  Filter
 */

trait AdvancedSearchTrait {

    /**
     * append relation filter
     *
     * @param string $ownModel
     * @param array $relationsToSearchIn
     * @return Id
     */
    protected function _getAdvancedSearchFilter($ownModel = null, $relationsToSearchIn = null)
    {
        if (  Core::get('ADVANCED_SEARCHING') ||
            ! Core::getPreference()->getValue(Preference::ADVANCED_SEARCH, false) ||
            empty($relationsToSearchIn))
        {
            return null;
        }

        if (0 === strpos($this->_operator, 'not')) {
            $not = true;
            $operator = substr($this->_operator, 3);
        } else {
            $not = false;
            $operator = $this->_operator;
        }
        $ownIds = array();
        foreach ((array) $relationsToSearchIn as $relatedModel) {
            $filterModel = $relatedModel . 'Filter';
            // prevent recursion here
            // TODO find a better way for this, maybe we could pass this an option to all filters in filter model
            Core::set('ADVANCED_SEARCHING', true);
            $relatedFilter = new $filterModel(array(
                array('field' => 'query',   'operator' => $operator, 'value' => $this->_value),
            ));

            try {
                $relatedIds = Core::getApplicationInstance($relatedModel)->search($relatedFilter, NULL, FALSE, TRUE);
            } catch (AccessDenied $tead) {
                continue;
            }
            Core::set('ADVANCED_SEARCHING', false);

            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Found ' . count($relatedIds) . ' related ids');

            $relationFilter = new RelationFilter(array(
                array('field' => 'own_model'    , 'operator' => 'equals', 'value' => $relatedModel),
                array('field' => 'own_backend'  , 'operator' => 'equals', 'value' => 'Sql'),
                array('field' => 'own_id'       , 'operator' => 'in'    , 'value' => $relatedIds),
                array('field' => 'related_model', 'operator' => 'equals', 'value' => $ownModel)
            ));
            $ownIds = array_merge($ownIds, Relations::getInstance()->search($relationFilter, NULL)->related_id);
        }

        return new Id('id', $not?'notin':'in', $ownIds);
    }
}