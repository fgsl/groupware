<?php
namespace Fgsl\Groupware\Groupbase\Group;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * primary class to handle groups
 *
 * @package     Groupbase
 * @subpackage  Group
 */
class Group
{
    /**
     * backend constants
     * 
     * @var string
     */
    const ACTIVEDIRECTORY = 'ActiveDirectory';
    const LDAP            = 'Ldap';
    const SQL             = 'Sql';
    const TYPO3           = 'Typo3';
    
    
    /**
     * default admin group name
     * 
     * @var string
     */
    const DEFAULT_ADMIN_GROUP = 'Administrators';
    
    /**
     * default user group name
     * 
     * @var string
     */
    const DEFAULT_USER_GROUP = 'Users';

    /**
     * default anonymous group name
     *
     * @var string
     */
    const DEFAULT_ANONYMOUS_GROUP = 'Anonymous';
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() {}
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}

    /**
     * holds the instance of the singleton
     *
     * @var Group
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return Group_Abstract
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            $backendType = Tinebase_User::getConfiguredBackend();
            
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' groups backend: ' . $backendType);

            self::$_instance = self::factory($backendType);
        }
        
        return self::$_instance;
    }
    
    /**
     * return an instance of the current groups backend
     *
     * @param   string $_backendType name of the groups backend
     * @return  Group_Abstract
     * @throws  InvalidArgument
     */
    public static function factory($_backendType) 
    {
        switch($_backendType) {
            case self::ACTIVEDIRECTORY:
                $options = Tinebase_User::getBackendConfiguration();
                
                $result = new Group_ActiveDirectory($options);
                
                break;
                
            case self::LDAP:
                $options = Tinebase_User::getBackendConfiguration();
                
                $options['plugins'] = array();
                
                // manage samba sam?
                if (isset(Core::getConfig()->samba) && Core::getConfig()->samba->get('manageSAM', FALSE) == true) {
                    $options['plugins'][] = Group_Ldap::PLUGIN_SAMBA;
                    $options[Group_Ldap::PLUGIN_SAMBA] = Core::getConfig()->samba->toArray();
                }
                
                $result = new Group_Ldap($options);
                
                break;
                
            case self::SQL:
                $result = new Group_Sql();
                break;
            
            case self::TYPO3:
                $result = new Group_Typo3();
                break;
                
            default:
                throw new InvalidArgument("Groups backend type $_backendType not implemented.");
        }

        if ($result instanceof Group_Interface_SyncAble) {
            // turn off replicable feature for ModelGroup
            ModelGroup::setReplicable(false);
        }

        return $result;
    }
    
    /**
     * syncronize groupmemberships for given $_username from syncbackend to local sql backend
     * 
     * @todo sync secondary group memberships
     * @param  mixed  $_username  the login id of the user to synchronize
     */
    public static function syncMemberships($_username)
    {
        if ($_username instanceof Tinebase_Model_FullUser) {
            $username = $_username->accountLoginName;
        } else {
            $username = $_username;
        }
        
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Sync group memberships for: " . $username);
        
        $userBackend  = Tinebase_User::getInstance();
        $groupBackend = Group::getInstance();
        $adbInstalled = Application::getInstance()->isInstalled('Addressbook');

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
        
            $user = $userBackend->getUserByProperty('accountLoginName', $username, 'Tinebase_Model_FullUser');

            $membershipsSyncBackend = $groupBackend->getGroupMembershipsFromSyncBackend($user);
            if (! in_array($user->accountPrimaryGroup, $membershipsSyncBackend)) {
                $membershipsSyncBackend[] = $user->accountPrimaryGroup;
            }

            $membershipsSqlBackend = $groupBackend->getGroupMemberships($user);

            sort($membershipsSqlBackend);
            sort($membershipsSyncBackend);
            if ($membershipsSqlBackend == $membershipsSyncBackend) {
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Group memberships are already in sync.');

                TransactionManager::getInstance()->commitTransaction($transactionId);
                $transactionId = null;
                return;
            }

            $newGroupMemberships = array_diff($membershipsSyncBackend, $membershipsSqlBackend);
            foreach ($newGroupMemberships as $groupId) {
                Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " Add user to groupId " . $groupId);
                // make sure new groups exist in sql backend / create empty group if needed
                try {
                    $groupBackend->getGroupById($groupId);
                } catch (NotDefined $tern) {
                    try {
                        $group = $groupBackend->getGroupByIdFromSyncBackend($groupId);
                        // TODO use exact exception class Ldap something?
                    } catch (Exception $e) {
                        // we dont get the group? ok, just ignore it, maybe we don't have rights to view it.
                        continue;
                    }

                    if ($adbInstalled) {
                        // in this case its okto create the list without members, they will be added later
                        // in self::syncListsOfUserContact
                        Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($group);
                    }
                    ModificationLog::setRecordMetaData($group, 'create');
                    $groupBackend->addGroupInSqlBackend($group);
                }
            }

            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                .' Set new group memberships: ' . print_r($membershipsSyncBackend, TRUE));

            $groupIds = $groupBackend->setGroupMembershipsInSqlBackend($user, $membershipsSyncBackend);
            self::syncListsOfUserContact($groupIds, $user->contact_id);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }
    }
    
    /**
     * creates or updates addressbook lists for an array of group ids
     *
     * @param array $groupIds
     * @param string $contactId
     */
    public static function syncListsOfUserContact($groupIds, $contactId)
    {
        // check addressbook and empty contact id (for example cronuser)
        if (! Application::getInstance()->isInstalled('Addressbook') || empty($contactId)) {
            return;
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
            .' Syncing ' . count($groupIds) . ' group -> lists / memberships');

        $listController = Addressbook_Controller_List::getInstance();
        $oldAcl = $listController->doContainerACLChecks(false);

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            foreach ($groupIds as $groupId) {
                // get single groups to make sure that container id is joined
                try {
                    $group = Group::getInstance()->getGroupById($groupId);
                } catch (Tinebase_Exception_NotFound $tenf) {
                    continue;
                }

                $group->members = Group::getInstance()->getGroupMembers($groupId);
                $oldListId = $group->list_id;
                $list = $listController->createOrUpdateByGroup($group);

                if ($oldListId !== $list->getId()) {
                    // list id changed / is new -> update group
                    ModificationLog::setRecordMetaData($group, 'update');
                    Group::getInstance()->updateGroup($group);
                }
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            $listController->doContainerACLChecks($oldAcl);
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }
    }
    
    /**
     * import and sync groups from sync backend
     *
     * @return bool
     */
    public static function syncGroups()
    {
        $groupBackend = Group::getInstance();
        $adbInstalled = Application::getInstance()->isInstalled('Addressbook');

        if (! $groupBackend instanceof Group_Interface_SyncAble) {
            if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ .
                ' No syncable group backend found - skipping syncGroups.');
            return true;
        }
        
        if (!$groupBackend->isDisabledBackend()) {
            $groups = $groupBackend->getGroupsFromSyncBackend(NULL, NULL, 'ASC', NULL, NULL);
        } else {
            // fake groups by reading all gidnumber's of the accounts
            $accountProperties = Tinebase_User::getInstance()->getUserAttributes(array('gidnumber'));
            
            $groupIds = array();
            foreach ($accountProperties as $accountProperty) {
                $groupIds[$accountProperty['gidnumber']] = $accountProperty['gidnumber'];
            }
            
            $groups = new RecordSet('ModelGroup');
            foreach ($groupIds as $groupId) {
                $groups->addRecord(new ModelGroup(array(
                    'id'            => $groupId,
                    'name'          => 'Group ' . $groupId
                ), TRUE));
            }
        }

        foreach ($groups as $group) {
            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                ' Sync group: ' . $group->name . ' - update or create group in local sql backend');

            $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
            try {
                $sqlGroup = $groupBackend->getGroupById($group);
                
                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                    ' Merge missing properties and update group.');
                $groupBackend->mergeMissingProperties($group, $sqlGroup);

                if ($adbInstalled) {
                    $group->members = $groupBackend->getGroupMembers($group);
                    Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($group);
                }

                ModificationLog::setRecordMetaData($group, 'update');
                $groupBackend->updateGroupInSqlBackend($group);

                TransactionManager::getInstance()->commitTransaction($transactionId);
                $transactionId = null;
                
            } catch (NotDefined $tern) {
                // try to find group by name
                try {
                    $sqlGroup = $groupBackend->getGroupByName($group->name);
                    
                    if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                        ' Delete current sql group as it has the wrong id. Merge missing properties and create new group.');
                    $groupBackend->deleteGroupsInSqlBackend(array($sqlGroup->getId()));
                    $groupBackend->mergeMissingProperties($group, $sqlGroup);

                } catch (NotDefined $tern2) {
                    if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                        ' Group not found by ID and name, adding new group.');
                }

                if ($adbInstalled) {
                    // in this case it is ok to create list without members
                    Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($group);
                }

                ModificationLog::setRecordMetaData($group, 'create');
                $groupBackend->addGroupInSqlBackend($group);

                TransactionManager::getInstance()->commitTransaction($transactionId);
                $transactionId = null;
            } finally {
                if (null !== $transactionId) {
                    TransactionManager::getInstance()->rollBack();
                }
            }

            Tinebase_Lock::keepLocksAlive();
        }

        return true;
    }
    
    /**
     * create initial groups
     * 
     * Method is called during Setup Initialization
     *
     * @throws  InvalidArgument
     */
    public static function createInitialGroups()
    {
        $defaultAdminGroupName = (Tinebase_User::getBackendConfiguration(Tinebase_User::DEFAULT_ADMIN_GROUP_NAME_KEY)) 
            ? Tinebase_User::getBackendConfiguration(Tinebase_User::DEFAULT_ADMIN_GROUP_NAME_KEY)
            : self::DEFAULT_ADMIN_GROUP;
        $adminGroup = new ModelGroup(array(
            'name'          => $defaultAdminGroupName,
            'description'   => 'Group of administrative accounts'
        ));
        ModificationLog::setRecordMetaData($adminGroup, 'create');
        Group::getInstance()->addGroup($adminGroup);

        $defaultUserGroupName = (Tinebase_User::getBackendConfiguration(Tinebase_User::DEFAULT_USER_GROUP_NAME_KEY))
            ? Tinebase_User::getBackendConfiguration(Tinebase_User::DEFAULT_USER_GROUP_NAME_KEY)
            : self::DEFAULT_USER_GROUP;
        $userGroup = new ModelGroup(array(
            'name'          => $defaultUserGroupName,
            'description'   => 'Group of user accounts'
        ));
        ModificationLog::setRecordMetaData($userGroup, 'create');
        Group::getInstance()->addGroup($userGroup);

        $defaultAnonymousGroupName =
            Tinebase_User::getBackendConfiguration(Tinebase_User::DEFAULT_ANONYMOUS_GROUP_NAME_KEY)
            ? Tinebase_User::getBackendConfiguration(Tinebase_User::DEFAULT_ANONYMOUS_GROUP_NAME_KEY)
            : self::DEFAULT_ANONYMOUS_GROUP;
        $anonymousGroup = new ModelGroup(array(
            'name'          => $defaultAnonymousGroupName,
            'description'   => 'Group of anonymous user accounts',
            'visibility'    => ModelGroup::VISIBILITY_HIDDEN
        ));
        ModificationLog::setRecordMetaData($anonymousGroup, 'create');
        Group::getInstance()->addGroup($anonymousGroup);
    }

    public static function unsetInstance()
    {
        self::$_instance = null;
    }
}
