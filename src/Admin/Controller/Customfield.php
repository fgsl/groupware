<?php
namespace Fgsl\Groupware\Admin\Controller;

use Fgsl\Groupware\Groupbase\Controller\Record\AbstractRecord as AbstractControllerRecord;
use Fgsl\Groupware\Groupbase\CustomField\CustomField as GroupbaseCustomField;
use Fgsl\Groupware\Groupbase\CustomField\Config as GroupbaseCustomFieldConfig;
use Fgsl\Groupware\Admin\Controller\Container as ControllerContainer;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Exception\SystemGeneric;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
/**
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Customfield Controller for Admin application
 *
 * @package     Admin
 * @subpackage  Controller
 */
class Customfield extends AbstractControllerRecord
{
    /**
     * tinebase customfield controller/backend
     * 
     * @var GroupbaseCustomfield
     */
    protected $_customfieldController = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() 
    {
        $this->_applicationName       = 'Admin';
        $this->_modelName             = 'Fgsl\Groupware\Groupbase\Model\CustomField\Config';
        $this->_doContainerACLChecks  = FALSE;
                
        $this->_backend = new GroupbaseCustomFieldConfig();
        
        $this->_customfieldController = GroupbaseCustomField::getInstance();
    }

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }

    /**
     * holds the instance of the singleton
     *
     * @var ControllerContainer
     */
    private static $_instance = NULL;

    /**
     * the singleton pattern
     *
     * @return Customfield
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Customfield;
        }
        
        return self::$_instance;
    }
    
    /**************** overriden methods ***************************/

    /**
     * add one record
     *
     * @param   RecordInterface $_record
     * @param   boolean $_duplicateCheck
     * @return  RecordInterface
     * @throws  AccessDenied
     */
    public function create(RecordInterface $_record, $_duplicateCheck = true)
    {
        return $this->_customfieldController->addCustomField($_record);
    }

    /**
     * get by id
     *
     * @param string $_id
     * @param int $_containerId
     * @param bool         $_getRelatedData
     * @param bool $_getDeleted
     * @return RecordInterface
     * @throws AccessDenied
     */
    public function get($_id, $_containerId = NULL, $_getRelatedData = TRUE, $_getDeleted = FALSE)
    {
        return $this->_customfieldController->getCustomField($_id);
    }
    
    /**
     * Deletes a set of records.
     *  
     * @param   array array of record identifiers
     * @return  array
     * @throws NotFound|Exception
     */
    public function delete($ids)
    {
        if (!is_array($this->_requestContext) || !isset($this->_requestContext['skipUsageCheck']) || !$this->_requestContext['skipUsageCheck']) {
            $this->_checkCFUsage($ids);
        }
        foreach ((array) $ids as $id) {
            $this->_customfieldController->deleteCustomField($id);
        }
        
        return (array) $ids;
    }
    
    /**
     * checks if customfield(s) are still in use (have values)
     * 
     * @param array $ids
     * @throws SystemGeneric
     */
    protected function _checkCFUsage($ids)
    {
        $filter = new Tinebase_Model_CustomField_ValueFilter(array(array(
            'field'     => 'customfield_id',
            'operator'  => 'in',
            'value'     => (array) $ids
        )));

        $result = $this->_customfieldController->search($filter);
        if ($result->count() > 0) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' ' . count($result) . ' records still have custom field values.');

            $foundIds = array_values(array_unique($result->customfield_id));

            $filter = new Tinebase_Model_CustomField_ConfigFilter(array(array(
                'field'     => 'id',
                'operator'  => 'in',
                'value'     => (array) $foundIds
            )));

            $result = $this->search($filter);
            $names = $result->name;
            
            throw new Tinebase_Exception_Record_StillInUse('Customfields: ' . join(', ', $names) . ' are still in use! Are you sure you want to delete them?');
        }
    }
    
    /**
    * inspect update of one record (after update)
    *
    * @param   RecordInterface $updatedRecord   the just updated record
    * @param   RecordInterface $record          the update record
    * @param   RecordInterface $currentRecord   the current record (before update)
    * @return  void
    */
    protected function _inspectAfterUpdate($updatedRecord, $record, $currentRecord)
    {
        $this->_customfieldController->clearCacheForConfig($updatedRecord);
    }
}
