<?php
namespace Fgsl\Groupware\Groupbase\Controller\Record;
use Fgsl\Groupware\Groupbase\Controller\AbstractRecord;
use Fgsl\Groupware\Groupbase\Backend\Sql\Grants as SqlGrants;
use Fgsl\Groupware\Groupbase\Model\Pagination;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Admin\Acl\Rights;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Acl\Roles;
use Fgsl\Groupware\Groupbase\Group\Group;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Model\Grants as ModelGrants;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Model\User;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Exception\Backend as ExceptionBackend;
use Fgsl\Groupware\Groupbase\TransactionManager;
use Fgsl\Groupware\Groupbase\Exception\Exception;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

abstract class Grants extends AbstractRecord
{
   /**
     * record grants backend class
     *
     * @var SqlGrants
     */
    protected $_grantsBackend;

   /**
     * record grants model class
     *
     * @var string
     */
    protected $_grantsModel;

    /**
     * @var string acl record property for join with acl table
     */
    protected $_aclIdProperty = 'id';

    /**
     * get list of records
     *
     * @param FilterGroup $_filter
     * @param Pagination $_pagination
     * @param boolean $_getRelations
     * @param boolean $_onlyIds
     * @param string $_action for right/acl check
     * @return RecordSet|array
     */
    public function search(FilterGroup $_filter = NULL, Pagination $_pagination = NULL, $_getRelations = FALSE, $_onlyIds = FALSE, $_action = 'get')
    {
        $result = parent::search($_filter, $_pagination, $_getRelations, $_onlyIds, $_action);
        
        // @todo allow to configure if grants are needed
        $this->_getGrants($result);
        
        return $result;
    }
    
    /**
     * check grant for action (CRUD)
     *
     * @param RecordInterface $record
     * @param string $action
     * @param boolean $throw
     * @param string $errorMessage
     * @param RecordInterface $oldRecord
     * @return boolean
     * @throws AccessDenied
     */
    protected function _checkGrant($record, $action, $throw = true, $errorMessage = 'No Permission.', $oldRecord = null)
    {
        if (! $this->_doContainerACLChecks) {
            return TRUE;
        }

        $hasGrant = parent::_checkGrant($record, $action, $throw, $errorMessage, $oldRecord);
        
        if (! $record->getId() || $action === 'create') {
            // no record based grants for new records
            return $hasGrant;
        }
        
        // always get current record grants
        $currentRecord = $this->_backend->get($record->getId());
        $this->_getGrants($currentRecord);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Checked record (incl. grants): ' . print_r($currentRecord->toArray(), true));
        
        switch ($action) {
            case 'get':
                $hasGrant = $this->hasGrant($currentRecord, ModelGrants::GRANT_READ);
                break;
            case 'update':
                $hasGrant = $this->hasGrant($currentRecord, ModelGrants::GRANT_EDIT);
                break;
            case 'delete':
                $hasGrant = $this->hasGrant($currentRecord, ModelGrants::GRANT_DELETE);
                break;
        }
        
        if (! $hasGrant) {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' No permissions to ' . $action . ' record.');
            if ($throw) {
                throw new AccessDenied($errorMessage);
            }
        }
        
        return $hasGrant;
    }
    
    /**
     * checks if user has grant for record
     * 
     * @param RecordInterface $record
     * @param string $grant
     * @param User $account
     * @return boolean
     */
    public function hasGrant($record, $grant, User $account = null)
    {
        // always get current grants
        $recordset = new RecordSet($this->_modelName, array($record));
        $this->_grantsBackend->getGrantsForRecords($recordset, $this->_aclIdProperty);

        if (! empty($record->grants)) {
            /**
             * @var ModelGrants $grantRecord
             */
            foreach ($record->grants as $grantRecord) {
                if ($grantRecord->userHasGrant($grant, $account)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * set relations / tags / alarms / grants
     * 
     * @param   RecordInterface $updatedRecord   the just updated record
     * @param   RecordInterface $record          the update record
     * @param   RecordInterface $currentRecord   the original record if one exists
     * @param   boolean $returnUpdatedRelatedData
     * @param   boolean $isCreate
     * @return  RecordInterface
     */
    protected function _setRelatedData(RecordInterface $updatedRecord, RecordInterface $record, RecordInterface $currentRecord = null, $returnUpdatedRelatedData = false, $isCreate = false)
    {
        $updatedRecord->grants = $record->grants;
        $this->setGrants($updatedRecord);
        
        return parent::_setRelatedData($updatedRecord, $record, $currentRecord, $returnUpdatedRelatedData, $isCreate);
    }

    /**
     * set grants of record
     *
     * @param RecordInterface $record
     * @param bool $addDuringSetup -> let admin group have all rights instead of user
     * @return RecordSet of record grants
     * @throws UnexpectedValue
     * @throws ExceptionBackend
     *
     * @todo improve algorithm: only update/insert/delete changed grants
     */
    public function setGrants(RecordInterface $record, $addDuringSetup = false)
    {
        $recordId = $record->getId();
        
        if (empty($recordId)) {
            throw new UnexpectedValue('record id required to set grants');
        }
        
        if (! $this->_validateGrants($record)) {
            $this->_setDefaultGrants($record, $addDuringSetup);
        }
        
        try {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                . ' Setting grants for record ' . $recordId);
            
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                . ' Grants: ' . print_r($record->grants->toArray(), true));
            
            $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
            $this->_grantsBackend->deleteByProperty($recordId, 'record_id');

            $uniqueGate = [];
            /** @var ModelGrants $newGrant */
            foreach ($record->grants as $newGrant) {
                $uniqueKey = $newGrant->account_type . $newGrant->account_id;
                if (isset($uniqueGate[$uniqueKey])) {
                    continue;
                }
                $uniqueGate[$uniqueKey] = true;
                
                foreach (call_user_func($this->_grantsModel . '::getAllGrants') as $grant) {
                    if ($newGrant->{$grant}) {
                        $newGrant->id = null;
                        $newGrant->account_grant = $grant;
                        $newGrant->record_id = $recordId;
                        $this->_grantsBackend->create($newGrant);
                    }
                }
            }
            
            TransactionManager::getInstance()->commitTransaction($transactionId);
            
        } catch (\Exception $e) {
            Exception::log($e);
            TransactionManager::getInstance()->rollBack();
            throw new ExceptionBackend($e->getMessage());
        }
        
        return $record->grants;
    }
    
    /**
     * check for "valid" grants: one "edit" / admin? grant should always exist.
     * 
     * -> returns false if no edit grants were found
     * 
     * @param RecordInterface $record
     * @return boolean
     */
    protected function _validateGrants($record)
    {
        if (empty($record->grants)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                . ' Record has no grants.');
            return false;
        }
        
        if (is_array($record->grants)) {
            $record->grants = new RecordSet($this->_grantsModel, $record->grants);
        }
        
        $editGrants = $record->grants->filter(ModelGrants::GRANT_EDIT, true);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Number of edit grants: ' . count($editGrants));
        
        return (count($editGrants) > 0);
    }

    /**
     * add default grants
     * 
     * @param   RecordInterface $record
     * @param   boolean $addDuringSetup -> let admin group have all rights instead of user
     */
    protected function _setDefaultGrants(RecordInterface $record, $addDuringSetup = false)
    {
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
            . ' Setting default grants ...');
        
        $record->grants = new RecordSet($this->_grantsModel);
        /** @var ModelGrants $grant */
        $grant = new $this->_grantsModel(array(
            'account_type' => $addDuringSetup ? Rights::ACCOUNT_TYPE_GROUP : Rights::ACCOUNT_TYPE_USER,
            'record_id'    => $record->getId(),
        ));
        $grant->sanitizeAccountIdAndFillWithAllGrants();
        $record->grants->addRecord($grant);
    }

    /**
     * @param string $recordId
     */
    public function deleteGrantsOfRecord($recordId)
    {
        $this->_grantsBackend->deleteByProperty($recordId, 'record_id');
    }

    /**
     * this function creates a new record with default grants during inital setup
     * 
     * TODO  think about adding a ignoreAcl, ignoreModlog param to normal create()
     *   OR    allow add setup user that can do everything
     *   OR    add helper function to disable all ACL and user stuff
     * 
     * @param RecordInterface $record
     * @return  RecordInterface
     */
    public function createDuringSetup(RecordInterface $record)
    {
        $createdRecord = $this->_backend->create($record);
        $createdRecord->grants = $record->grants;
        $this->setGrants($createdRecord, /* addDuringSetup = */ true);
        return $createdRecord;
    }
    
    /**
     * add related data / grants to record
     * 
     * @param RecordInterface $record
     */
    protected function _getRelatedData($record)
    {
        parent::_getRelatedData($record);
        
        if (empty($record->grants)) {
            // grants may have already been fetched
            $this->_getGrants($record);
        }
    }
    
    /**
     * get record grants
     * 
     * @param RecordInterface|RecordSet $records
     */
    protected function _getGrants($records)
    {
        $recordset = ($records instanceof RecordInterface)
            ? new RecordSet($this->_modelName, array($records))
            : ($records instanceof RecordSet ? $records : new RecordSet($this->_modelName, $records));
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Get grants for ' . count($recordset). ' records.');
        
        $this->_grantsBackend->getGrantsForRecords($recordset, $this->_aclIdProperty);
    }

    /**
     * get grants for account
     * 
     * @param User $user
     * @param RecordInterface $record
     * @return ModelGrants
     * 
     * @todo force refetch from db or add user param to _getGrants()?
     */
    public function getGrantsOfAccount($user, $record)
    {
        if ($user === null) {
            $user = Core::getUser();
        }

        if (empty($record->grants)) {
            $this->_getGrants($record);
        }

        $roleMemberships = Roles::getInstance()->getRoleMemberships($user);
        $groupMemberships = Group::getInstance()->getGroupMemberships($user);
        $accountGrants = new $this->_grantsModel(array(
            'account_type' => Rights::ACCOUNT_TYPE_USER,
            'account_id'   => $user->getId(),
            'record_id'    => ($this->_aclIdProperty === 'id' ? $record->getId() : $record->{$this->_aclIdProperty}),
        ));
        if (empty($record->grants)) {
            // grants might still be empty
            return $accountGrants;
        }
        foreach ($record->grants as $grantRecord) {
            foreach (call_user_func($this->_grantsModel . '::getAllGrants') as $grant) {
                if ($grantRecord->{$grant} &&
                    (
                        $grantRecord->account_type === Rights::ACCOUNT_TYPE_ANYONE ||
                        $grantRecord->account_type === Rights::ACCOUNT_TYPE_GROUP && in_array($grantRecord->account_id, $groupMemberships) ||
                        $grantRecord->account_type === Rights::ACCOUNT_TYPE_USER && $user->getId() === $grantRecord->account_id ||
                        $grantRecord->account_type === Rights::ACCOUNT_TYPE_ROLE && in_array($grantRecord->account_id, $roleMemberships)
                    )
                ) {
                    $accountGrants->{$grant} = true;
                }
            }
        }
        
        return $accountGrants;
    }

    /**
     * returns grants of record
     *
     * @param RecordInterface $record
     * @return  RecordSet subtype ModelGrants
     */
    public function getGrantsForRecord($record)
    {
        if (empty($record->grants)) {
            $this->_getGrants($record);
        }

        return $record->grants;
    }
}
