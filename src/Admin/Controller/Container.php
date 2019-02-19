<?php
namespace Fgsl\Groupware\Admin\Controller;

use Fgsl\Groupware\Groupbase\Controller\Record\AbstractRecord as AbstractControllerRecord;
use Fgsl\Groupware\Groupbase\Container as GroupbaseContainer;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Exception\Record\NotAllowed;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Model\Container as ModelContainer;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Addressbook\Controller\Contact;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Translation;
use Fgsl\Groupware\Groupbase\Notification\Notification;
use Fgsl\Groupware\Groupbase\Acl\Rights;
use Fgsl\Groupware\Groupbase\Application\Application;
/**
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Container Controller for Admin application
 *
 * @package     Admin
 * @subpackage  Controller
 */
class Container extends AbstractControllerRecord
{
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() 
    {
        $this->_applicationName       = 'Admin';
        $this->_modelName             = 'Fgsl\Groupware\Groupbase\Model\Container';
        $this->_doContainerACLChecks  = false;
        $this->_purgeRecords          = false;
        // modlog will be written by GroupbaseContainer aka the backend, disable it in Tinebase_Controller_Record_Abstract
        $this->_omitModLog            = true;

        // we need to avoid that anybody else gets this instance ... as it has acl turned off!
        GroupbaseContainer::destroyInstance();
        $this->_backend = GroupbaseContainer::getInstance();
        $this->_backend->doSearchAclFilter(false);
        // unset internal reference to prevent others to get instance without acl
        GroupbaseContainer::destroyInstance();
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
     * @var Container
     */
    private static $_instance = NULL;

    /**
     * the singleton pattern
     *
     * @return Container
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Container;
        }
        
        return self::$_instance;
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
        $this->_checkRight('get');
        
        $container = $this->_backend->getContainerById($_id);
        $container->account_grants = $this->_backend->getGrantsOfContainer($container, TRUE);
        
        return $container;
    }

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
        $this->_checkRight('create');

        $_record->isValid(TRUE);

        /** @var ModelContainer$_record */
        $_record->account_grants = $this->_convertGrantsToRecordSet($_record->account_grants, $_record->getGrantClass());
        GroupbaseContainer::getInstance()->checkContainerOwner($_record);

        ModificationLog::setRecordMetaData($_record, 'create');
        
        $container = $this->_backend->addContainer($_record, $_record->account_grants, TRUE);
        $container->account_grants = $this->_backend->getGrantsOfContainer($container, TRUE);
        
        return $container;
    }
    
    /**
     * convert grants to record set
     * 
     * @param RecordSet|array $_grants
     * @param string $_grantsModel
     * @return RecordSet
     */
    protected function _convertGrantsToRecordSet($_grants, $_grantsModel)
    {
        $result = (! $_grants instanceof RecordSet && is_array($_grants)) 
            ? new RecordSet($_grantsModel, $_grants)
            : $_grants;
        
        return $result;
    }
    
    /**
     * update one record
     *
     * @param   RecordInterface $_record
     * @param   array $_additionalArguments
     * @return  RecordInterface
     *
     */
    public function update(RecordInterface $_record, $_additionalArguments = array())
    {
        $container = parent::update($_record);
        
        if ($container->type === ModelContainer::TYPE_PERSONAL) {
            $this->_sendNotification($container, ((isset($_additionalArguments['note']) || array_key_exists('note', $_additionalArguments))) ? $_additionalArguments['note'] : '');
        }    
        return $container;
    }
    
    /**
     * inspect update of one record (before update)
     * 
     * @param   RecordInterface $_record      the update record
     * @param   RecordInterface $_oldRecord   the current persistent record
     * @return  void
     * @throws NotAllowed
     * 
     */
    protected function _inspectBeforeUpdate($_record, $_oldRecord)
    {
        if ($_oldRecord->application_id !== $_record->application_id) {
            throw new NotAllowed('It is not allowed to change the application of a container.');
        }

        /** @var ModelContainer $_record */
        $_record->account_grants = $this->_convertGrantsToRecordSet($_record->account_grants, $_record->getGrantClass());
        
        GroupbaseContainer::getInstance()->checkContainerOwner($_record);
        $this->_backend->setGrants($_record, $_record->account_grants, TRUE, FALSE);
    }
    
    /**
     * send notification to owner
     * 
     * @param $container
     * @param $note
     */
    protected function _sendNotification($container, $note)
    {
        if (empty($note)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Empty note: do not send notification for container ' . $container->name);
            return;
        }
        
        $ownerId = GroupbaseContainer::getInstance()->getContainerOwner($container);
        
        if ($ownerId !== FALSE) {
            try {
                $contact = Contact::getInstance()->getContactByUserId($ownerId, TRUE);
            } catch (NotFound $tenf) {
                if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                    . ' Do not send notification for container ' . $container->name . ': ' . $tenf);
                return;
            }
            
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                . ' Sending notification for container ' . $container->name . ' to ' . $contact->n_fn);
            
            $translate = Translation::getTranslation('Admin');
            $messageSubject = $translate->_('Your container has been changed');
            $messageBody = sprintf($translate->_('Your container has been changed by %1$s %2$sNote: %3$s'), Core::getUser()->accountDisplayName, "\n\n", $note);
            
            try {
                Notification::getInstance()->send(Core::getUser(), array($contact), $messageSubject, $messageBody);
            } catch (\Exception $e) {
                Core::getLogger()->WARN(__METHOD__ . '::' . __LINE__ . ' Could not send notification :' . $e);
            }
        }
    }
    
    /**
     * Deletes a set of records.
     * 
     * If one of the records could not be deleted, no record is deleted
     * 
     * @param   array array of record identifiers
     * @return  RecordSet
     */
    public function delete($_ids)
    {
        $this->_checkRight('delete');
        
        $containers = new RecordSet('ModelContainer');
        
        foreach ($_ids as $id) {
            $containers->addRecord(GroupbaseContainer::getInstance()->deleteContainer($id, true));
        }
        
        return $containers;
    }

    /**
     * set multiple container grants
     * 
     * @param RecordSet $_containers
     * @param array|string              $_grants single or multiple grants
     * @param array|string              $_accountId single or multiple account ids
     * @param string                    $_accountType
     * @param boolean                   $_overwrite replace grants?
     * 
    */
    public function setGrantsForContainers($_containers, $_grants, $_accountId, $_accountType = Rights::ACCOUNT_TYPE_USER, $_overwrite = FALSE)
    {
        $this->_checkRight('update');
        
        $accountType = ($_accountId === '0') ? Rights::ACCOUNT_TYPE_ANYONE : $_accountType;
        $accountIds = (array) $_accountId;
        $grantsArray = ($_overwrite) ? array() : (array) $_grants;
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Changing grants of containers: ' . print_r($_containers->name, TRUE));
        
        (Application::getInstance()->isInstalled('Timetracker')) 
            ? Application::getInstance()->getApplicationByName('Timetracker')
            : NULL;

        /** @var ModelContainer $container */
        foreach($_containers as $container) {
            foreach ($accountIds as $accountId) {
                if ($_overwrite) {
                    foreach((array) $_grants as $grant) {
                        $grantsArray[] = array(
                            'account_id'    => $accountId,
                            'account_type'  => $accountType,
                            $grant          => TRUE,
                        );
                    }
                } else {
                    GroupbaseContainer::getInstance()->addGrants($container->getId(), $accountType, $accountId, $grantsArray, TRUE);
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                        . ' Added grants to container "' . $container->name . '" for userid ' . $accountId . ' (' . $accountType . ').');
                }
            }
            
            if ($_overwrite) {
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Set grants for container "' . $container->name . '".');
                $grants = new RecordSet($container->getGrantClass(), $grantsArray);
                
                GroupbaseContainer::getInstance()->setGrants($container, $grants, TRUE, FALSE);
            }
        }        
    }
    
    /**
     * Removes containers where current user has no access to
     * -> remove timetracker containers, too (those are managed within the timetracker)
     * 
     * @param FilterGroup $_filter
     * @param string $_action get|update
     */
    public function checkFilterACL(FilterGroup $_filter, $_action = 'get')
    {
        if ($_action == 'get') {
            $userApps = Core::getUser()->getApplications(TRUE);
            $filterAppIds = array();
            foreach ($userApps as $app) {
                if ($app->name !== 'Timetracker') {
                    $filterAppIds[] = $app->getId();
                }
            }
            
            $appFilter = $_filter->createFilter('application_id', 'in', $filterAppIds);
            $_filter->addFilter($appFilter);
        }
    }
    
    /**
     * check if user has the right to manage containers
     * 
     * @param string $_action {get|create|update|delete}
     * @return void
     * @throws AccessDenied
     */
    protected function _checkRight($_action)
    {
        switch ($_action) {
            case 'get':
                $this->checkRight('VIEW_CONTAINERS');
                break;
            case 'create':
            case 'update':
            case 'delete':
                $this->checkRight('MANAGE_CONTAINERS');
                break;
            default;
               break;
        }

        parent::_checkRight($_action);
    }
}
