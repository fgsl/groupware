<?php
namespace Fgsl\Groupware\Admin\Controller;

use Fgsl\Groupware\Groupbase\Controller\AbstractController;
use Fgsl\Groupware\Groupbase\Group\Group as GroupbaseGroup;
use Fgsl\Groupware\Groupbase\Model\Group as ModelGroup;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\Record\NotDefined;
use Fgsl\Groupware\Groupbase\TransactionManager;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;
use Fgsl\Groupware\Groupbase\Event\Event;
use Fgsl\Groupware\Admin\Event\CreateGroup;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Addressbook\Controller\ControllerList;
use Fgsl\Groupware\Admin\Event\UpdateGroup;
use Fgsl\Groupware\Admin\Event\AddGroupMember;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Admin\Event\RemoveGroupMember;
use Fgsl\Groupware\Admin\Event\BeforeDeleteGroup;
use Fgsl\Groupware\Addressbook\Backend\BackendList;
use Fgsl\Groupware\Admin\Event\DeleteGroup;
use Fgsl\Groupware\Addressbook\Model\ModelList;

/**
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Group Controller for Admin application
 *
 * @package     Admin
 * @subpackage  Controller
 */
class Group extends AbstractController
{
    /**
     * holds the instance of the singleton
     *
     * @var Group
     */
    private static $_instance = NULL;

    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() 
    {
        $this->_applicationName = 'Admin';
    }

    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }

    /**
     * the singleton pattern
     *
     * @return Group
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Group;
        }
        
        return self::$_instance;
    }
    
    /**
     * get list of groups
     *
     * @param string $filter
     * @param string $sort
     * @param string $dir
     * @param int $start
     * @param int $limit
     * @return RecordSet with record class ModelGroup
     */
    public function search($filter = NULL, $sort = 'name', $dir = 'ASC', $start = NULL, $limit = NULL)
    {
        $this->checkRight('VIEW_ACCOUNTS');
        
        return GroupbaseGroup::getInstance()->getGroups($filter, $sort, $dir, $start, $limit);
    }
   
    /**
     * count groups
     *
     * @param string $_filter string to search groups for
     * @return int total group count
     * 
     * @todo add checkRight again / but first fix Tinebase_Frontend_Json::searchGroups
     */
    public function searchCount($_filter)
    {
        //$this->checkRight('VIEW_ACCOUNTS');
        
        $groups = GroupbaseGroup::getInstance()->getGroups($_filter);
        $result = count($groups);
        
        return $result;
    }

    /**
     * set all groups an user is member of
     *
     * @param  mixed $_userId the account as integer or ModelUser
     * @param  mixed $_groupIds
     * @return array
     * @throws InvalidArgument
     */
    public function setGroupMemberships($_userId, $_groupIds)
    {
        $this->checkRight('MANAGE_ACCOUNTS');
        
        if ($_groupIds instanceof RecordSet) {
            $_groupIds = $_groupIds->getArrayOfIds();
        }
        
        if (count($_groupIds) === 0) {
            throw new InvalidArgument('user must belong to at least one group');
        }
        
        $userId = ModelUser::convertUserIdToInt($_userId);
        
        $groupMemberships = GroupbaseGroup::getInstance()->getGroupMemberships($userId);
        
        $removeGroupMemberships = array_diff($groupMemberships, $_groupIds);
        $addGroupMemberships    = array_diff($_groupIds, $groupMemberships);
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' current groupmemberships: ' . print_r($groupMemberships, true));
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' new groupmemberships: ' . print_r($_groupIds, true));
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' added groupmemberships: ' . print_r($addGroupMemberships, true));
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' removed groupmemberships: ' . print_r($removeGroupMemberships, true));
        
        foreach ($addGroupMemberships as $groupId) {
            $this->addGroupMember($groupId, $userId);
        }
        
        foreach ($removeGroupMemberships as $groupId) {
            try {
                $this->removeGroupMember($groupId, $userId);
            } catch (NotDefined $tern) {
                if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ 
                    . ' Could not remove group member from group ' . $groupId . ': ' . $tern);
            }
        }
        
        return GroupbaseGroup::getInstance()->getGroupMemberships($userId);
    }
    
    /**
     * fetch one group identified by groupid
     *
     * @param int $_groupId
     * @return ModelGroup
     */
    public function get($_groupId)
    {
        $this->checkRight('VIEW_ACCOUNTS');
        
        $group = GroupbaseGroup::getInstance()->getGroupById($_groupId);

        return $group;
    }

    /**
     * add new group
     *
     * @param ModelGroup $_group
     * @return ModelGroup
     * @throws \Exception
     */
    public function create(ModelGroup $_group)
    {
        $this->checkRight('MANAGE_ACCOUNTS');
        
        // avoid forging group id, get's created in backend
        unset($_group->id);

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            if (Application::getInstance()->isInstalled('Addressbook') === true) {
                $this->createOrUpdateList($_group);
            }

            ModificationLog::setRecordMetaData($_group, 'create');

            $group = GroupbaseGroup::getInstance()->addGroup($_group);

            if (!empty($_group['members'])) {
                GroupbaseGroup::getInstance()->setGroupMembers($group->getId(), $_group['members']);
            }

            $event = new CreateGroup();
            $event->group = $group;
            Event::fireEvent($event);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }
        
        return $group;
    }  

   /**
     * update existing group
     *
     * @param ModelGroup $_group
     * @param boolean $_updateList
     * @return ModelGroup
     */
    public function update(ModelGroup $_group, $_updateList = true)
    {
        $this->checkRight('MANAGE_ACCOUNTS');

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            // update default user group if name has changed
            $oldGroup = GroupbaseGroup::getInstance()->getGroupById($_group->getId());

            $defaultGroupName = User::getBackendConfiguration(User::DEFAULT_USER_GROUP_NAME_KEY);
            if ($oldGroup->name == $defaultGroupName && $oldGroup->name != $_group->name) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Updated default group name: ' . $oldGroup->name . ' -> ' . $_group->name
                );
                User::setBackendConfiguration($_group->name, User::DEFAULT_USER_GROUP_NAME_KEY);
                User::saveBackendConfiguration();
            }

            if (true === $_updateList && Application::getInstance()->isInstalled('Addressbook') === true) {
                $_group->list_id = $oldGroup->list_id;
                $this->createOrUpdateList($_group);
            }

            ModificationLog::setRecordMetaData($_group, 'update', $oldGroup);

            $group = GroupbaseGroup::getInstance()->updateGroup($_group);

            GroupbaseGroup::getInstance()->setGroupMembers($group->getId(), $_group->members);

            $event = new UpdateGroup();
            $event->group = $group;
            Event::fireEvent($event);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        return $group;
    }
    
    /**
     * add a new groupmember to a group
     *
     * @param int $_groupId
     * @param int $_userId
     * @param  boolean $_addToList
     * @return void
     */
    public function addGroupMember($_groupId, $_userId, $_addToList = true)
    {
        $this->checkRight('MANAGE_ACCOUNTS');

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            GroupbaseGroup::getInstance()->addGroupMember($_groupId, $_userId);

            if (true === $_addToList && Application::getInstance()->isInstalled('Addressbook') === true) {
                $group = $this->get($_groupId);
                $user  = User::getInstance()->getUserById($_userId);

                if (! empty($user->contact_id) && ! empty($group->list_id)) {
                    if (! ControllerList::getInstance()->exists($group->list_id)) {
                        if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . ' Could not add member to list ' . $group->list_id . ' (it does not exist)');
                    } else {
                        $aclChecking = ControllerList::getInstance()->doContainerACLChecks(FALSE);
                        ControllerList::getInstance()->addListMember($group->list_id, $user->contact_id, false);
                        ControllerList::getInstance()->doContainerACLChecks($aclChecking);
                    }
                }
            }

            $event = new AddGroupMember();
            $event->groupId = $_groupId;
            $event->userId  = $_userId;
            Event::fireEvent($event);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }
    }
    
    /**
     * remove one groupmember from the group
     *
     * @param int $_groupId
     * @param int $_userId
     * @param  boolean $_removeFromList
     * @return void
     */
    public function removeGroupMember($_groupId, $_userId, $_removeFromList = true)
    {
        $this->checkRight('MANAGE_ACCOUNTS');

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            GroupbaseGroup::getInstance()->removeGroupMember($_groupId, $_userId);

            if (true === $_removeFromList && Application::getInstance()->isInstalled('Addressbook') === true) {
                $group = $this->get($_groupId);
                $user  = User::getInstance()->getUserById($_userId);

                if (!empty($user->contact_id) && !empty($group->list_id)) {
                    try {
                        $aclChecking = ControllerList::getInstance()->doContainerACLChecks(FALSE);
                        ControllerList::getInstance()->removeListMember($group->list_id, $user->contact_id, false);
                        ControllerList::getInstance()->doContainerACLChecks($aclChecking);
                    } catch (NotFound $tenf) {
                        if (Core::isLogLevel(LogLevel::WARN))
                            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' catched exception: ' . get_class($tenf));
                        if (Core::isLogLevel(LogLevel::WARN))
                            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . $tenf->getMessage());
                        if (Core::isLogLevel(LogLevel::INFO))
                            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' ' . $tenf->getTraceAsString());
                    }
                }
            }

            $event = new RemoveGroupMember();
            $event->groupId = $_groupId;
            $event->userId  = $_userId;
            Event::fireEvent($event);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }
    }
    
    /**
     * delete multiple groups
     *
     * @param   array $_groupIds
     * @return  void
     */
    public function delete($_groupIds)
    {
        $this->checkRight('MANAGE_ACCOUNTS');

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {

            // check default user group / can't delete this group
            $defaultUserGroup = GroupbaseGroup::getInstance()->getDefaultGroup();

            if (in_array($defaultUserGroup->getId(), $_groupIds)) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Can\'t delete default group: ' . $defaultUserGroup->name
                );
                foreach ($_groupIds as $key => $value) {
                    if ($value == $defaultUserGroup->getId()) {
                        unset($_groupIds[$key]);
                    }
                }
            }

            if (empty($_groupIds)) {
                TransactionManager::getInstance()->commitTransaction($transactionId);
                $transactionId = null;
                return;
            }

            $eventBefore = new BeforeDeleteGroup();
            $eventBefore->groupIds = $_groupIds;
            Event::fireEvent($eventBefore);

            if (Application::getInstance()->isInstalled('Addressbook') === true) {
                $listIds = array();

                foreach ($_groupIds as $groupId) {
                    $group = $this->get($groupId);
                    if (!empty($group->list_id)) {
                        $listIds[] = $group->list_id;
                    }
                }

                if (!empty($listIds)) {
                    $listBackend = new BackendList();
                    $listBackend->delete($listIds);
                }
            }

            GroupbaseGroup::getInstance()->deleteGroups($_groupIds);

            $event = new DeleteGroup();
            $event->groupIds = $_groupIds;
            Event::fireEvent($event);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }
    }
    
    /**
     * get list of groupmembers
     *
     * @param int $_groupId
     * @return array with ModelUser arrays
     */
    public function getGroupMembers($_groupId)
    {
        $result = GroupbaseGroup::getInstance()->getGroupMembers($_groupId);
        
        return $result;
    }
    
    /**
     * return all groups an account is member of
     *
     * @param mixed $_accountId the account as integer or ModelUser
     * @return array
     */
    public function getGroupMemberships($_accountId)
    {
        $this->checkRight('VIEW_ACCOUNTS');
        
        $result = GroupbaseGroup::getInstance()->getGroupMemberships($_accountId);
        
        return $result;
    }
    
    /**
     * create or update list in addressbook sql backend
     * 
     * @param  ModelGroup  $group
     * @return ModelList
     */
    public function createOrUpdateList(ModelGroup $group)
    {
        return ControllerList::getInstance()->createOrUpdateByGroup($group);
    }
}