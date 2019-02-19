<?php
namespace Fgsl\Groupware\Groupbase\Backend\Sql\Filter;

use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup as ModelFilterFilterGroup;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Model\Filter\AbstractFilter;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * FilterGroup SQL Backend
 *
 * @package    Groupbase
 * @subpackage Filter
 */
class FilterGroup
{
    /**
     * appends tenor of filters to sql select object
     * 
     * NOTE: In order to archive nested filters we use the extended 
     *       ModelFilterFilterGroup select object. This object
     *       appends all contained filters at once concated by the concetation
     *       operator of the filtergroup
     *
     * @param  Select                            $_select
     * @param  ModelFilterFilterGroup            $_filters
     * @param  AbstractSql                       $_backend
     * @param  boolean                           $_appendFilterSql
     */
    public static function appendFilters($_select, $_filters, $_backend, $_appendFilterSql = TRUE)
    {
        // support for direct sql filter append in derived filter groups
        if ($_appendFilterSql && method_exists($_filters, 'appendFilterSql')) {
            $_filters->appendFilterSql($_select, $_backend);
        }
        
        foreach ($_filters->getFilterObjects() as $filter) {
            $groupSelect = new GroupSelect($_select);
            
            if ($filter instanceof AbstractFilter) {
                $filter->appendFilterSql($groupSelect, $_backend);
            } else {
                self::appendFilters($groupSelect, $filter, $_backend);
            }
            
            $groupSelect->appendWhere($_filters->getCondition());
        }
    }
}
