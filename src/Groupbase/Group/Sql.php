<?php
namespace Fgsl\Groupware\Groupbase\Group;
use Fgsl\Groupware\Groupbase\Controller\Record\ModlogTrait;
use Fgsl\Groupware\Groupbase\Db\Table;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Model\Group as ModelGroup;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Helper;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * SQL implementation of the groups interface
 * 
 * @package     Groupbase
 * @subpackage  Group
 */
class Sql extends AbstractGroup
{
    use ModlogTrait;


    /**
     * Model name
     *
     * @var string
     *
     * @todo perhaps we can remove that and build model name from name of the class (replace 'Controller' with 'Model')
     */
    protected $_modelName = 'ModelGroup';

    /**
     * @var AdapterInterface
     */
    protected $_db;
    
    /**
     * the groups table
     *
     * @var Table
     */
    protected $groupsTable;
    
    /**
     * the groupmembers table
     *
     * @var Table
     */
    protected $groupMembersTable;
    
    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'groups';
    
    /**
     * set to true is addressbook table is found
     * 
     * @var boolean
     */
    protected $_addressBookInstalled = false;
    
    /**
     * in class cache 
     * 
     * @var array
     */
    protected $_classCache = array (
        'getGroupMemberships' => array()
    );
    
    /**
     * the constructor
     */
    public function __construct() 
    {
        $this->_db = Core::getDb();
        
        $this->groupsTable = new Table(array('name' => SQL_TABLE_PREFIX . $this->_tableName));
        $this->groupMembersTable = new Table(array('name' => SQL_TABLE_PREFIX . 'group_members'));
        
        try {
            // MySQL throws an exception         if the table does not exist
            // PostgreSQL returns an empty array if the table does not exist
            $adbSchema = Table::getTableDescriptionFromCache(SQL_TABLE_PREFIX . 'addressbook');
            $adbListsSchema = Table::getTableDescriptionFromCache(SQL_TABLE_PREFIX . 'addressbook_lists');
            if (! empty($adbSchema) && ! empty($adbListsSchema) ) {
                $this->_addressBookInstalled = TRUE;
            }
        } catch (\Exception $zdse) {
            // nothing to do
        }
    }
    
    /**
     * return all groups an account is member of
     *
     * @param mixed $_accountId the account as integer or ModelUser
     * @return array
     */
    public function getGroupMemberships($_accountId)
    {
        $accountId = ModelUser::convertUserIdToInt($_accountId);
        
        $classCacheId = $accountId;
        
        if (isset($this->_classCache[__FUNCTION__][$classCacheId])) {
            return $this->_classCache[__FUNCTION__][$classCacheId];
        }
        
        $cacheId     = Helper::convertCacheId(__FUNCTION__ . $classCacheId);
        $memberships = Core::getCache()->load($cacheId);
        
        if (! $memberships) {
            $select = $this->_db->select()
                ->distinct()
                ->from(array('group_members' => SQL_TABLE_PREFIX . 'group_members'), array('group_id'))
                ->where($this->_db->quoteIdentifier('account_id') . ' = ?', $accountId);
            
            $stmt = $this->_db->query($select);
            
            $memberships = $stmt->fetchAll();
            
            Core::getCache()->save($memberships, $cacheId, [__CLASS__], 300);
        }
        
        $this->_classCache[__FUNCTION__][$classCacheId] = $memberships;
        
        return $memberships;
    }
    
    /**
     * get list of groupmembers
     *
     * @param int $_groupId
     * @return array with account ids
     */
    public function getGroupMembers($_groupId)
    {
        $groupId = ModelGroup::convertGroupIdToInt($_groupId);
        
        $cacheId = Helper::convertCacheId(__FUNCTION__ . $groupId);
        $members = Core::getCache()->load($cacheId);

        if (false === $members) {
            $members = array();

            $select = $this->groupMembersTable->select();
            $select->where($this->_db->quoteIdentifier('group_id') . ' = ?', $groupId);

            $rows = $this->groupMembersTable->fetchAll($select);
            
            foreach($rows as $member) {
                $members[] = $member->account_id;
            }

            Core::getCache()->save($members, $cacheId, [__CLASS__], 300);
        }

        return $members;
    }

    /**
     * replace all current groupmembers with the new groupmembers list
     *
     * @param  mixed $_groupId
     * @param  array $_groupMembers
     */
    public function setGroupMembers($_groupId, $_groupMembers)
    {
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Setting ' . count($_groupMembers) . ' new groupmembers for group ' . $_groupId);
        
        if ($this instanceof Group_Interface_SyncAble) {
            $_groupMembers = $this->setGroupMembersInSyncBackend($_groupId, $_groupMembers);
        }
        
        $this->setGroupMembersInSqlBackend($_groupId, $_groupMembers);
    }
     
    /**
     * replace all current groupmembers with the new groupmembers list
     *
     * @param  mixed  $_groupId
     * @param  array  $_groupMembers
     */
    public function setGroupMembersInSqlBackend($_groupId, $_groupMembers)
    {
        $groupId = ModelGroup::convertGroupIdToInt($_groupId);

        $oldGroupMembers = $this->getGroupMembers($groupId);

        // remove old members
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('group_id') . ' = ?', $groupId);
        $this->groupMembersTable->delete($where);
        
        // check if users have accounts
        $userIdsWithExistingAccounts = Tinebase_User::getInstance()->getMultiple($_groupMembers)->getArrayOfIds();
        
        if (count($_groupMembers) > 0) {
            // add new members
            foreach ($_groupMembers as $accountId) {
                $accountId = ModelUser::convertUserIdToInt($accountId);
                if (in_array($accountId, $userIdsWithExistingAccounts)) {
                    try {
                        $this->_db->insert(SQL_TABLE_PREFIX . 'group_members', array(
                            'group_id' => $groupId,
                            'account_id' => $accountId
                        ));
                    } catch (Zend_Db_Statement_Exception $zdse) {
                        // ignore duplicate exceptions
                        if (! preg_match('/duplicate/i', $zdse->getMessage())) {
                            throw $zdse;
                        } else {
                            if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                                . ' ' . $zdse->getMessage());
                        }
                    }

                } else {
                    if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . ' User with ID ' . $accountId . ' does not have an account!');
                }
                
                $this->_clearCache(array('getGroupMemberships' => $accountId));
            }
        }
        
        $this->_clearCache(array('getGroupMembers' => $groupId));

        $newGroupMembers = $this->getGroupMembers($groupId);

        if (!empty(array_diff($oldGroupMembers, $newGroupMembers)) || !empty(array_diff($newGroupMembers, $oldGroupMembers)))
        {
            $oldGroup = new ModelGroup(array('id' => $groupId, 'members' => $oldGroupMembers), true);
            $newGroup = new ModelGroup(array('id' => $groupId, 'members' => $newGroupMembers), true);
            $this->_writeModLog($newGroup, $oldGroup);
        }
    }
    
    /**
     * invalidate cache by type/id
     * 
     * @param array $cacheIds
     */
    protected function _clearCache($cacheIds = array())
    {
        $cache = Core::getCache();

        if (empty($cacheIds)) {
            $this->resetClassCache();
        } else {
            foreach ($cacheIds as $type => $id) {
                $cacheId = Helper::convertCacheId($type . $id);
                $cache->remove($cacheId);
                $this->resetClassCache($type);
            }
        }
    }

    public function resetClassCache($key = null)
    {
        if (null === $key) {
            Core::getCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, [__CLASS__]);
        }
        return parent::resetClassCache($key);
    }
    
    /**
     * set all groups an account is member of
     *
     * @param  mixed  $_userId    the userid as string or ModelUser
     * @param  mixed  $_groupIds
     * 
     * @return array
     */
    public function setGroupMemberships($_userId, $_groupIds)
    {
        if(count($_groupIds) === 0) {
            throw new InvalidArgument('user must belong to at least one group');
        }
        
        if($this instanceof Group_Interface_SyncAble) {
            $this->setGroupMembershipsInSyncBackend($_userId, $_groupIds);
        }
        
        return $this->setGroupMembershipsInSqlBackend($_userId, $_groupIds);
    }
    
    /**
     * set all groups an user is member of
     *
     * @param  mixed  $_usertId   the account as integer or ModelUser
     * @param  mixed  $_groupIds
     * @return array
     */
    public function setGroupMembershipsInSqlBackend($_userId, $_groupIds)
    {
        if ($_groupIds instanceof RecordSet) {
            $_groupIds = $_groupIds->getArrayOfIds();
        }
        
        if (count($_groupIds) === 0) {
            throw new InvalidArgument('user must belong to at least one group');
        }
        
        $userId = ModelUser::convertUserIdToInt($_userId);
        
        $groupMemberships = $this->getGroupMemberships($userId);
        
        $removeGroupMemberships = array_diff($groupMemberships, $_groupIds);
        $addGroupMemberships    = array_diff($_groupIds, $groupMemberships);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' current groupmemberships: ' . print_r($groupMemberships, true));
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' new groupmemberships: ' . print_r($_groupIds, true));
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' added groupmemberships: ' . print_r($addGroupMemberships, true));
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' removed groupmemberships: ' . print_r($removeGroupMemberships, true));
        
        foreach ($addGroupMemberships as $groupId) {
            $this->addGroupMemberInSqlBackend($groupId, $userId);
        }
        
        foreach ($removeGroupMemberships as $groupId) {
            $this->removeGroupMemberFromSqlBackend($groupId, $userId);
        }

        // useless event, its not used anywhere!
        $event = new Group_Event_SetGroupMemberships(array(
            'user'               => $_userId,
            'addedMemberships'   => $addGroupMemberships,
            'removedMemberships' => $removeGroupMemberships
        ));
        Tinebase_Event::fireEvent($event);
        
        return $this->getGroupMemberships($userId);
    }
    
    /**
     * add a new groupmember to a group
     *
     * @param  string  $_groupId
     * @param  string  $_accountId
     */
    public function addGroupMember($_groupId, $_accountId)
    {
        if ($this instanceof Group_Interface_SyncAble) {
            $this->addGroupMemberInSyncBackend($_groupId, $_accountId);
        }
        
        $this->addGroupMemberInSqlBackend($_groupId, $_accountId);
    }
    
    /**
     * add a new groupmember to a group
     *
     * @param  string  $_groupId
     * @param  string  $_accountId
     */
    public function addGroupMemberInSqlBackend($_groupId, $_accountId)
    {
        $groupId   = ModelGroup::convertGroupIdToInt($_groupId);
        $accountId = ModelUser::convertUserIdToInt($_accountId);

        $memberShips = $this->getGroupMemberships($accountId);
        
        if (!in_array($groupId, $memberShips)) {

            $oldGroupMembers = $this->getGroupMembers($groupId);

            $data = array(
                'group_id'      => $groupId,
                'account_id'    => $accountId
            );
        
            $this->groupMembersTable->insert($data);
            
            $this->_clearCache(array(
                'getGroupMembers'     => $groupId,
                'getGroupMemberships' => $accountId,
            ));

            $newGroupMembers = $this->getGroupMembers($groupId);

            if (!empty(array_diff($oldGroupMembers, $newGroupMembers)) || !empty(array_diff($newGroupMembers, $oldGroupMembers)))
            {
                $oldGroup = new ModelGroup(array('id' => $groupId, 'members' => $oldGroupMembers), true);
                $newGroup = new ModelGroup(array('id' => $groupId, 'members' => $newGroupMembers), true);
                $this->_writeModLog($newGroup, $oldGroup);
            }
        }
        
    }
    
    /**
     * remove one groupmember from the group
     *
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId
     */
    public function removeGroupMember($_groupId, $_accountId)
    {
        if ($this instanceof Group_Interface_SyncAble) {
            $this->removeGroupMemberInSyncBackend($_groupId, $_accountId);
        }
        
        return $this->removeGroupMemberFromSqlBackend($_groupId, $_accountId);
    }
    
    /**
     * remove one groupmember from the group
     *
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId
     */
    public function removeGroupMemberFromSqlBackend($_groupId, $_accountId)
    {
        $groupId   = ModelGroup::convertGroupIdToInt($_groupId);
        $accountId = ModelUser::convertUserIdToInt($_accountId);

        $oldGroupMembers = $this->getGroupMembers($groupId);
        
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier('group_id') . '= ?', $groupId),
            $this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . '= ?', $accountId),
        );
         
        $this->groupMembersTable->delete($where);
        
        $this->_clearCache(array(
            'getGroupMembers'     => $groupId,
            'getGroupMemberships' => $accountId,
        ));

        $newGroupMembers = $this->getGroupMembers($groupId);

        if (!empty(array_diff($oldGroupMembers, $newGroupMembers)) || !empty(array_diff($newGroupMembers, $oldGroupMembers)))
        {
            $oldGroup = new ModelGroup(array('id' => $groupId, 'members' => $oldGroupMembers), true);
            $newGroup = new ModelGroup(array('id' => $groupId, 'members' => $newGroupMembers), true);
            $this->_writeModLog($newGroup, $oldGroup);
        }
    }
    
    /**
     * create a new group
     *
     * @param   ModelGroup  $_group
     * 
     * @return  ModelGroup
     * 
     * @todo do not create group in sql if sync backend is readonly?
     */
    public function addGroup(ModelGroup $_group)
    {
        if ($this instanceof Group_Interface_SyncAble) {
            $groupFromSyncBackend = $this->addGroupInSyncBackend($_group);
            
            if (isset($groupFromSyncBackend->id)) {
                $_group->setId($groupFromSyncBackend->getId());
            }
        }
        
        return $this->addGroupInSqlBackend($_group);
    }
    
    /**
     * alias for addGroup
     * 
     * @param ModelGroup $group
     * @return ModelGroup
     */
    public function create(ModelGroup $group)
    {
        return $this->addGroup($group);
    }
    
    /**
     * create a new group in sql backend
     *
     * @param   ModelGroup  $_group
     * 
     * @return  ModelGroup
     * @throws  Tinebase_Exception_Record_Validation
     */
    public function addGroupInSqlBackend(ModelGroup $_group)
    {
        if(!$_group->isValid()) {
            throw new Tinebase_Exception_Record_Validation('invalid group object');
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
            . ' Creating new group ' . $_group->name 
            //. print_r($_group->toArray(), true)
        );
        
        if(!$_group->getId()) {
            $groupId = $_group->generateUID();
            $_group->setId($groupId);
        }
        
        if (!$_group->list_id) {
            $_group->visibility = 'hidden';
            $_group->list_id    = null;
        }
        
        $data = $_group->toArray();
        
        unset($data['members']);
        unset($data['container_id']);
        
        $this->groupsTable->insert($data);

        $newGroup = clone $_group;
        $newGroup->members = null;
        $newGroup->container_id = null;
        $this->_writeModLog($newGroup, null);
        
        return $_group;
    }
    
    /**
     * update a group
     *
     * @param  ModelGroup  $_group
     * 
     * @return ModelGroup
     */
    public function updateGroup(ModelGroup $_group)
    {
        if ($this instanceof Group_Interface_SyncAble) {
            $this->updateGroupInSyncBackend($_group);
        }
        
        return $this->updateGroupInSqlBackend($_group);
    }
    
    /**
     * create a new group in sync backend
     * 
     * NOTE: sets visibility to HIDDEN if list_id is empty
     *
     * @param  ModelGroup  $_group
     * @return ModelGroup
     */
    public function updateGroupInSqlBackend(ModelGroup $_group)
    {
        $groupId = ModelGroup::convertGroupIdToInt($_group);

        $oldGroup = $this->getGroupById($groupId);

        if (empty($_group->list_id)) {
            $_group->visibility = ModelGroup::VISIBILITY_HIDDEN;
            $_group->list_id    = null;
        }
        
        $data = array(
            'name'          => $_group->name,
            'description'   => $_group->description,
            'visibility'    => $_group->visibility,
            'email'         => $_group->email,
            'list_id'       => $_group->list_id,
            'created_by'            => $_group->created_by,
            'creation_time'         => $_group->creation_time,
            'last_modified_by'      => $_group->last_modified_by,
            'last_modified_time'    => $_group->last_modified_time,
            'is_deleted'            => $_group->is_deleted,
            'deleted_time'          => $_group->deleted_time,
            'deleted_by'            => $_group->deleted_by,
            'seq'                   => $_group->seq,
        );
        
        if (empty($data['seq'])) {
            unset($data['seq']);
        }
        
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $groupId);
        
        $this->groupsTable->update($data, $where);
        
        $updatedGroup = $this->getGroupById($groupId);

        $this->_writeModLog($updatedGroup, $oldGroup);

        return $updatedGroup;
    }
    
    /**
     * delete groups
     *
     * @param   mixed $_groupId

     * @throws  Tinebase_Exception_Backend
     */
    public function deleteGroups($_groupId)
    {
        $groupIds = array();
        
        if (is_array($_groupId) or $_groupId instanceof RecordSet) {
            foreach ($_groupId as $groupId) {
                $groupIds[] = ModelGroup::convertGroupIdToInt($groupId);
            }
            if (count($groupIds) === 0) {
                return;
            }
        } else {
            $groupIds[] = ModelGroup::convertGroupIdToInt($_groupId);
        }
        
        try {
            $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());

            $this->deleteGroupsInSqlBackend($groupIds);
            if ($this instanceof Group_Interface_SyncAble) {
                $this->deleteGroupsInSyncBackend($groupIds);
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);

        } catch (Exception $e) {
            TransactionManager::getInstance()->rollBack();
            Tinebase_Exception::log($e);
            throw new Tinebase_Exception_Backend($e->getMessage());
        }
    }
    
    /**
     * set primary group for accounts with given primary group id
     * 
     * @param array $groupIds
     * @param string $newPrimaryGroupId
     * @throws NotDefined
     */
    protected function _updatePrimaryGroupsOfUsers($groupIds, $newPrimaryGroupId = null)
    {
        if ($newPrimaryGroupId === null) {
            $newPrimaryGroupId = $this->getDefaultGroup()->getId();
        }
        foreach ($groupIds as $groupId) {
            $users = Tinebase_User::getInstance()->getUsersByPrimaryGroup($groupId);
            $users->accountPrimaryGroup = $newPrimaryGroupId;
            foreach ($users as $user) {
                Tinebase_User::getInstance()->updateUser($user);
            }
        }
    }
    
    /**
     * delete groups in sql backend
     * 
     * @param array $groupIds
     */
    public function deleteGroupsInSqlBackend($groupIds)
    {
        $this->_updatePrimaryGroupsOfUsers($groupIds);

        $groups = array();
        foreach($groupIds as $groupId) {
            $group = $this->getGroupById($groupId);
            $group->members = $this->getGroupMembers($groupId);
            $groups[] = $group;
        }

        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('group_id') . ' IN (?)', (array) $groupIds);
        $this->groupMembersTable->delete($where);
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' IN (?)', (array) $groupIds);
        $this->groupsTable->delete($where);

        foreach($groups as $group) {
            $this->_writeModLog(null, $group);
        }
    }
    
    /**
     * Delete all groups returned by {@see getGroups()} using {@see deleteGroups()}
     * @return void
     */
    public function deleteAllGroups()
    {
        $groups = $this->getGroups();
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Deleting ' . count($groups) .' groups');
        
        if(count($groups) > 0) {
            $this->deleteGroups($groups);
        }
    }
    
    /**
     * get list of groups
     *
     * @param string $_filter
     * @param string $_sort
     * @param string $_dir
     * @param int $_start
     * @param int $_limit
     * @return RecordSet with record class ModelGroup
     */
    public function getGroups($_filter = NULL, $_sort = 'name', $_dir = 'ASC', $_start = NULL, $_limit = NULL)
    {
        $select = $this->_getSelect();
        
        if($_filter !== NULL) {
            $select->where($this->_db->quoteIdentifier($this->_tableName. '.name') . ' LIKE ?', '%' . $_filter . '%');
        }
        if($_sort !== NULL) {
            $select->order($this->_tableName . '.' . $_sort . ' ' . $_dir);
        }
        if($_start !== NULL) {
            $select->limit($_limit, $_start);
        }
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetchAll();
        $stmt->closeCursor();
        
        $result = new RecordSet('ModelGroup', $queryResult, TRUE);
        
        return $result;
    }
    
    /**
     * get group by name
     *
     * @param   string $_name
     * @return  ModelGroup
     * @throws  NotDefined
     */
    public function getGroupByName($_name)
    {
        $result = $this->getGroupByPropertyFromSqlBackend('name', $_name);
        
        return $result;
    }
    
    /**
     * get group by property
     *
     * @param   string  $_property      the key to filter
     * @param   string  $_value         the value to search for
     *
     * @return  ModelGroup
     * @throws  NotDefined
     * @throws  InvalidArgument
     */
    public function getGroupByPropertyFromSqlBackend($_property, $_value)
    {
        if (! in_array($_property, array('id', 'name', 'description', 'list_id', 'email'))) {
            throw new InvalidArgument('property not allowed');
        }
        
        $select = $this->_getSelect();
        
        $select->where($this->_db->quoteIdentifier($this->_tableName . '.' . $_property) . ' = ?', $_value);
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();
        
        if (!$queryResult) {
            throw new NotDefined('Group not found.');
        }
        
        $result = new ModelGroup($queryResult, TRUE);
        
        return $result;
    }
    
    
    /**
     * get group by id
     *
     * @param   string $_name
     * @return  ModelGroup
     * @throws  NotDefined
     */
    public function getGroupById($_groupId)
    {
        $groupdId = ModelGroup::convertGroupIdToInt($_groupId);
        
        $result = $this->getGroupByPropertyFromSqlBackend('id', $groupdId);
        
        return $result;
    }
    
    /**
     * Get multiple groups
     *
     * @param string|array $_ids Ids
     * @return RecordSet
     * 
     * @todo this should return the container_id, too
     */
    public function getMultiple($_ids)
    {
        $result = new RecordSet('ModelGroup');
        
        if (! empty($_ids)) {
            $select = $this->groupsTable->select();
            $select->where($this->_db->quoteIdentifier('id') . ' IN (?)', array_unique((array) $_ids));
            
            $rows = $this->groupsTable->fetchAll($select);
            foreach ($rows as $row) {
                $result->addRecord(new ModelGroup($row->toArray(), TRUE));
            }
        }
        
        return $result;
    }
    
    /**
     * get the basic select object to fetch records from the database
     * 
     * NOTE: container_id is joined from addressbook lists table
     *  
     * @param array|string|Zend_Db_Expr $_cols columns to get, * per default
     * @param boolean $_getDeleted get deleted records (if modlog is active)
     * @return Zend_Db_Select
     */
    protected function _getSelect($_cols = '*', $_getDeleted = FALSE)
    {
        $select = $this->_db->select();
        
        $select->from(array($this->_tableName => SQL_TABLE_PREFIX . $this->_tableName), $_cols);
        
        if ($this->_addressBookInstalled === true) {
            $select->joinLeft(
                array('addressbook_lists' => SQL_TABLE_PREFIX . 'addressbook_lists'),
                $this->_db->quoteIdentifier($this->_tableName . '.list_id') . ' = ' . $this->_db->quoteIdentifier('addressbook_lists.id'), 
                array('container_id')
            );
        }
        
        return $select;
    }
    
    /**
     * Method called by {@see Addressbook_Setup_Initialize::_initilaize()}
     * 
     * @param $_options
     * @return mixed
     */
    public function __importGroupMembers($_options = null)
    {
        //nothing to do
        return null;
    }

    /**
     * @param Tinebase_Model_ModificationLog $modification
     */
    public function applyReplicationModificationLog(Tinebase_Model_ModificationLog $modification)
    {
        switch ($modification->change_type) {
            case ModificationLog::CREATED:
                $diff = new Tinebase_Record_Diff(json_decode($modification->new_value, true));
                $record = new ModelGroup($diff->diff);
                Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($record);
                ModificationLog::setRecordMetaData($record, 'create');
                $this->addGroup($record);
                break;

            case ModificationLog::UPDATED:
                $diff = new Tinebase_Record_Diff(json_decode($modification->new_value, true));
                if (isset($diff->diff['members']) && is_array($diff->diff['members'])) {
                    $this->setGroupMembers($modification->record_id, $diff->diff['members']);
                    $record = $this->getGroupById($modification->record_id);
                    $record->members = $this->getGroupMembers($record->getId());
                    Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($record);
                } else {
                    $record = $this->getGroupById($modification->record_id);
                    $currentRecord = clone $record;
                    $record->applyDiff($diff);
                    $record->members = $this->getGroupMembers($record->getId());
                    Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($record);
                    ModificationLog::setRecordMetaData($record, 'update', $currentRecord);
                    $this->updateGroup($record);
                }
                break;

            case ModificationLog::DELETED:
                $record = $this->getGroupById($modification->record_id);
                if (!empty($record->list_id)) {
                    Addressbook_Controller_List::getInstance()->delete($record->list_id);
                }
                $this->deleteGroups($modification->record_id);
                break;

            default:
                throw new Tinebase_Exception('unknown Tinebase_Model_ModificationLog->old_value: ' . $modification->old_value);
        }
    }

    /**
     * @param Tinebase_Model_ModificationLog $modification
     * @param bool $dryRun
     */
    public function undoReplicationModificationLog(Tinebase_Model_ModificationLog $modification, $dryRun)
    {
        if (ModificationLog::CREATED === $modification->change_type) {
            if (!$dryRun) {
                $record = $this->getGroupById($modification->record_id);
                if (!empty($record->list_id)) {
                    Addressbook_Controller_List::getInstance()->delete($record->list_id);
                }
                $this->deleteGroups($modification->record_id);
            }
        } elseif (ModificationLog::DELETED === $modification->change_type) {
            $diff = new Tinebase_Record_Diff(json_decode($modification->new_value, true));
            $model = $modification->record_type;
            /** @var ModelGroup $record */
            $record = new $model($diff->oldData, true);
            if (!$dryRun) {
                Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($record);
                $createdGroup = $this->create($record);
                if (is_array($record->members) && !empty($record->members)) {
                    $this->setGroupMembers($createdGroup->getId(), $record->members);
                }
            }
        } else {
            $record = $this->getGroupById($modification->record_id);
            $diff = new Tinebase_Record_Diff(json_decode($modification->new_value, true));

            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::'
                . __LINE__ . ' Undoing diff ' . print_r($diff->toArray(), true));

            // this undo will (re)load and populate members property if required
            $record->undo($diff);

            if (! $dryRun) {
                if (isset($diff->diff['members']) && is_array($diff->diff['members']) && is_array($record->members)) {
                    $this->setGroupMembers($record->getId(), $record->members);
                } else {
                    $record->members = $this->getGroupMembers($record->getId());
                }
                Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($record);
                $this->updateGroup($record);
            }
        }
    }

    public function sanitizeGroupListSync($dryRun = true)
    {
        // find duplicate list references
        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
        try {
            if ($this->_db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
                $listIds = $this->_db->select()->from([$this->_tableName => SQL_TABLE_PREFIX . $this->_tableName],
                    [
                        'list_id'
                    ])
                    ->where($this->_db->quoteIdentifier($this->_tableName . '.list_id') . ' IS NOT NULL')
                    ->having(new Zend_Db_Expr('count(' . $this->_db->quoteIdentifier('list_id') . ') > 1'))
                    ->group('list_id')->query()->fetchAll(Zend_Db::FETCH_COLUMN, 0);
            } else {
                $listIds = $this->_db->select()->from([$this->_tableName => SQL_TABLE_PREFIX . $this->_tableName],
                    [
                        'list_id',
                        new Zend_Db_Expr('count(' . $this->_db->quoteIdentifier('list_id') . ') AS '
                            . $this->_db->quoteIdentifier('c'))
                    ])
                    ->where($this->_db->quoteIdentifier($this->_tableName . '.list_id') . ' IS NOT NULL')
                    ->having($this->_db->quoteIdentifier('c') . ' > 1')
                    ->group('list_id')->query()->fetchAll(Zend_Db::FETCH_COLUMN, 0);
            }

            if (count($listIds) > 0) {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ . ' found duplicate list references ' . join(', ', $listIds));

                if (!$dryRun) {
                    $this->_db->update(SQL_TABLE_PREFIX . $this->_tableName, ['list_id' => null],
                        $this->_db->quoteInto($this->_db->quoteIdentifier('list_id') . ' = (?)', $listIds));
                }

                echo PHP_EOL . 'found ' . count($listIds) . ' duplicate list references (fixed)' . PHP_EOL;
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        // find groups which are deleted but lists are not
        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
        try {
            $groupIds = $this->_db->select()->from([$this->_tableName => SQL_TABLE_PREFIX . $this->_tableName], ['id'])
                ->join(
                    ['addressbook_lists' => SQL_TABLE_PREFIX . 'addressbook_lists'],
                    $this->_db->quoteIdentifier('addressbook_lists.id') . ' = '
                    . $this->_db->quoteIdentifier($this->_tableName . '.list_id') . ' AND '
                    . $this->_db->quoteIdentifier('addressbook_lists.is_deleted') . ' = 0',
                    []
                )->where($this->_db->quoteIdentifier($this->_tableName . '.is_deleted') . ' = 1')
                ->query()->fetchAll(Zend_Db::FETCH_COLUMN, 0);

            if (count($groupIds) > 0) {
                $msg = 'found ' . count($groupIds) . ' groups which are deleted and linked to undeleted lists: '
                    . join(', ', $groupIds);
                echo PHP_EOL . $msg . PHP_EOL . '(not fixed!)' . PHP_EOL;
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ . $msg);
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        // find groups with deleted lists
        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
        try {
            $groupIds = $this->_db->select()->from([$this->_tableName => SQL_TABLE_PREFIX . $this->_tableName], ['id'])
                ->join(
                    ['addressbook_lists' => SQL_TABLE_PREFIX . 'addressbook_lists'],
                    $this->_db->quoteIdentifier('addressbook_lists.id') . ' = '
                    . $this->_db->quoteIdentifier($this->_tableName . '.list_id') . ' AND '
                    . $this->_db->quoteIdentifier('addressbook_lists.is_deleted') . ' = 1',
                    []
                )->where($this->_db->quoteIdentifier($this->_tableName . '.is_deleted') . ' = 0')
                ->query()->fetchAll(Zend_Db::FETCH_COLUMN, 0);

            if (count($groupIds) > 0) {
                $msg = 'found ' . count($groupIds) . ' groups which are linked to deleted lists: '
                    . join(', ', $groupIds);
                echo PHP_EOL . $msg . PHP_EOL . '(not fixed!)' . PHP_EOL;
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ . $msg);
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }


        // find groups with lists of wrong type
        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
        try {
            $listIds = $this->_db->select()->from([$this->_tableName => SQL_TABLE_PREFIX . $this->_tableName], [])
                ->join(
                    ['addressbook_lists' => SQL_TABLE_PREFIX . 'addressbook_lists'],
                    $this->_db->quoteIdentifier('addressbook_lists.id') . ' = '
                    . $this->_db->quoteIdentifier($this->_tableName . '.list_id'),
                    ['id']
                )->where($this->_db->quoteIdentifier('addressbook_lists.type') . ' <> \'' .
                    Addressbook_Model_List::LISTTYPE_GROUP . '\'')
                ->query()->fetchAll(Zend_Db::FETCH_COLUMN, 0);

            if (count($listIds) > 0) {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ . ' found lists linked to groups of the wrong type ' . join(', ', $listIds));

                if (!$dryRun) {
                    Addressbook_Controller_List::getInstance()->getBackend()->updateMultiple($listIds,
                        ['type' => Addressbook_Model_List::LISTTYPE_GROUP]);
                }

                echo PHP_EOL . 'found ' . count($listIds) . ' lists linked to groups of the wrong type (fixed)'
                    . PHP_EOL;
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        // find groups without lists
        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
        try {
            $groupIds = $this->_db->select()->from([$this->_tableName => SQL_TABLE_PREFIX . $this->_tableName], ['id'])
                ->joinLeft(
                    ['addressbook_lists' => SQL_TABLE_PREFIX . 'addressbook_lists'],
                    $this->_db->quoteIdentifier('addressbook_lists.id') . ' = '
                    . $this->_db->quoteIdentifier($this->_tableName . '.list_id'),
                    []
                )->where($this->_db->quoteIdentifier('addressbook_lists.id') . ' IS NULL')
                ->query()->fetchAll();

            if (count($groupIds) > 0) {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ . ' found groups not having a list ' . join(', ', $groupIds));

                /** @var ModelGroup $group */
                foreach ($this->getMultiple($groupIds) as $group) {
                    if (!empty($group->list_id)) {
                        $group->list_id = null;
                    }
                    $group->members = $this->getGroupMembers($group);
                    if (!$dryRun) {
                        Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($group);
                        $this->updateGroupInSqlBackend($group);
                    }
                }

                echo PHP_EOL . 'found ' . count($groupIds) . ' groups not having a list (fixed)' . PHP_EOL;
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        // addressbook lists of type group without a group
        // make them type list and report them
        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
        try {
            $ids = [];
            $names = [];
            foreach ($this->_db->select()->from(['addressbook_lists' => SQL_TABLE_PREFIX . 'addressbook_lists'],
                ['id', 'name'])
                         ->joinLeft(
                             [$this->_tableName => SQL_TABLE_PREFIX . $this->_tableName],
                             $this->_db->quoteIdentifier('addressbook_lists.id') . ' = '
                             . $this->_db->quoteIdentifier($this->_tableName . '.list_id'),
                             [])
                         ->where($this->_db->quoteIdentifier('addressbook_lists.type') . ' = \''
                             . Addressbook_Model_List::LISTTYPE_GROUP . '\' AND '
                             . $this->_db->quoteIdentifier($this->_tableName . '.id') . ' IS NULL')
                         ->query()->fetchAll() as $row) {
                $ids[] = $row['id'];
                $names[] = $row['name'];
            }

            if (count($ids) > 0) {
                $msg = 'changed the following lists from type group to type list:' . PHP_EOL
                    . join(', ', $names);
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::'
                    . __LINE__ . ' ' . $msg . ' ' . join(', ', $ids));

                if (!$dryRun) {
                    Addressbook_Controller_List::getInstance()->getBackend()->updateMultiple($ids,
                        ['type' => Addressbook_Model_List::LISTTYPE_LIST]);
                }
                
                echo $msg . PHP_EOL;
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        // check members
        foreach ($dryRun ? [] : Group::getInstance()->getGroups() as $group) {
            $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
            try {
                try {
                    $group = Group::getInstance()->getGroupById($group);
                } catch (NotDefined $ternd) {
                        // race condition, just continue
                        TransactionManager::getInstance()->commitTransaction($transactionId);
                        $transactionId = null;
                        continue;
                }
                if (!empty($group->list_id)) {
                    $oldListId = $group->list_id;
                    $group->members = Group::getInstance()->getGroupMembers($group);
                    Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($group);
                    if ($group->list_id !== $oldListId) {
                        if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__
                            . '::' . __LINE__ . ' groups list_id changed in createOrUpdateByGroup unexpectedly: '
                            . $group->getId());
                        Group::getInstance()->updateGroup($group);
                    }
                }

                TransactionManager::getInstance()->commitTransaction($transactionId);
                $transactionId = null;
            } finally {
                if (null !== $transactionId) {
                    TransactionManager::getInstance()->rollBack();
                }
            }
        }
    }
}
