<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;

use Fgsl\Groupware\Groupbase\Controller\Record\AbstractRecord as AbstractControllerRecord;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/

/**
 * foreign id filter
 * 
 * Expects:
 * - a filtergroup in options->filtergroup
 * - a controller  in options->controller
 * 
 * Hands over all options to filtergroup
 * Hands over AclFilter functions to filtergroup
 *
 */
class ForeignId extends ForeignRecord
{
    /**
     * get foreign controller
     * 
     * @return AbstractControllerRecord
     */
    protected function _getController()
    {
        if ($this->_controller === NULL) {
            $this->_controller = call_user_func($this->_options['controller'] . '::getInstance');
        }
        
        return $this->_controller;
    }
    
    /**
     * set options 
     *
     * @param array $_options
     * @throws InvalidArgument
     */
    protected function _setOptions(array $_options)
    {
        if (! isset($_options['controller']) || ! isset($_options['filtergroup'])) {
            throw new InvalidArgument('a controller and a filtergroup must be specified in the options');
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
            $this->_foreignIds = $this->_getController()->search($this->_filterGroup, null, false, true);
        }

        if (strpos($this->_operator, 'not') === 0) {
            if (!empty($this->_foreignIds)) {
                $_select->where($this->_getQuotedFieldName($_backend) . ' NOT IN (?)', $this->_foreignIds);
            }
        } else {
            $_select->where($this->_getQuotedFieldName($_backend) . ' IN (?)',
                empty($this->_foreignIds) ? new Expression('NULL') : $this->_foreignIds);
        }
    }
    
    /**
     * set required grants
     * 
     * @param array $_grants
     */
    public function setRequiredGrants(array $_grants)
    {
        $this->_filterGroup->setRequiredGrants($_grants);
    }
    
    /**
     * get filter information for toArray()
     * 
     * @return array
     */
    protected function _getGenericFilterInformation()
    {
        list($appName, , $filterName) = explode('_', static::class);
        
        $result = array(
            'linkType'      => 'foreignId',
            'appName'       => $appName,
            'filterName'    => $filterName,
        );
        
        if (isset($this->_options['modelName'])) {
            list(,, $modelName) = explode('_', $this->_options['modelName']);
            $result['modelName'] = $modelName;
        }
        
        return $result;
    }
}
