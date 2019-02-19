<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Zend\Db\Sql\Select;

/**
 * @package     Groupbase
 * @subpackage  Filter
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * foreign id filter
 * 
 * Expects:
 * - a record class in options->recordClassName
 * - a controller class in options->controllerClassName
 * 
 * Hands over all options to filtergroup
 * Hands over AclFilter functions to filtergroup
 */
class ForeignRecords extends ForeignId
{
    /**
     * set options
     *
     * @param array $_options
     * @throws InvalidArgument::
     */
    protected function _setOptions(array $_options)
    {
        if (! isset($_options['refIdField'])) {
            throw new InvalidArgument('refIdField is required');
        }
        if (! isset($_options['filtergroup']) && isset($_options['recordClassName'])) {
            $_options['filtergroup'] = $_options['recordClassName'] . 'Filter';
        }
        if (! isset($_options['controller']) && isset($_options['controllerClassName'])) {
            $_options['controller'] = $_options['controllerClassName'];
        }
        parent::_setOptions($_options);
    }

    /**
     * appends sql to given select statement
     *
     * @param Select                $_select
     * @param AbstractSql           $_backend
     */
    public function appendFilterSql($_select, $_backend)
    {
        if (! is_array($this->_foreignIds) && null !== $this->_filterGroup) {
            $this->_foreignIds = array_keys($this->_getController()
                ->search($this->_filterGroup, null, false, $this->_options['refIdField']));
        }

        // TODO allow to configure id property or get it from model config
        $orgField = $this->_field;
        $this->_field = 'id';

        try {
            parent::appendFilterSql($_select, $_backend);
        } finally {
            $this->_field = $orgField;
        }
    }
}