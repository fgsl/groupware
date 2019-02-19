<?php
namespace Fgsl\Groupware\Groupbase\Model\CustomField;
use Fgsl\Groupware\Groupbase\Model\CustomField\Grant as ModelCustomFieldGrant; 
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Filter\AclFilter;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\CustomField\CustomField;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * CustomFieldConfig filter class
 * 
 * @package     Groupbase
 * @subpackage  Filter 
 */
class ConfigFilter extends FilterGroup implements AclFilter
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';
    
    /**
     * @var array one of these grants must be met
     */
    protected $_requiredGrants = array(
        ModelCustomFieldGrant::GRANT_READ
    );
        
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'                => array('filter' => 'Tinebase_Model_Filter_Id'),
        'application_id'    => array('filter' => 'Tinebase_Model_Filter_Id'),
        'name'              => array('filter' => 'Tinebase_Model_Filter_Text'),
        'model'             => array('filter' => 'Tinebase_Model_Filter_Text'),
    );
    
    /**
     * is acl filter resolved?
     *
     * @var boolean
     */
    protected $_isResolved = FALSE;
    
    /**
     * check for customfield ACLs
     *
     * @var boolean
     * 
     */
    protected $_customfieldACLChecks = TRUE;
    
    /**
     * set/get checking ACL
     * 
     * @param  boolean optional
     * @return boolean
     */
    public function customfieldACLChecks()
    {
        $currValue = $this->_customfieldACLChecks;
        if (func_num_args() === 1) {
            $paramValue = (bool) func_get_arg(0);
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Resetting customfieldACLChecks to ' . (int) $paramValue);
            $this->_customfieldACLChecks = $paramValue;
        }
        
        return $currValue;
    }
    
    /**
     * sets the grants this filter needs to assure
     *
     * @param array $_grants
     */
    public function setRequiredGrants(array $_grants)
    {
        $this->_requiredGrants = $_grants;
        $this->_isResolved = FALSE;
    }

    /**
     * appends sql to given select statement
     *
     * @param  Select                $_select
     * @param  AbstractSql           $_backend
     * @throws UnexpectedValue
     */
    public function appendFilterSql($_select, $_backend)
    {
        if ($this->_customfieldACLChecks) {
            // only search for ids for which the user has the required grants
            if (! $this->_isResolved) {
                $result = array();
                foreach ($this->_requiredGrants as $grant) {
                    $result = array_merge($result, CustomField::getInstance()->getCustomfieldConfigIdsByAcl($grant));
                }
                $this->_validCustomfields = array_unique($result);
                $this->_isResolved = TRUE;
                
                if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                    . ' Found ' . print_r($result, TRUE));
            }
            
            $db = Core::getDb();
            
            $field = $db->quoteIdentifier('id');
            $where = $db->quoteInto("$field IN (?)", empty($this->_validCustomfields) ? array('') : $this->_validCustomfields);
            
            $_select->where($where);
        }
    }
}