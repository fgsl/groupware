<?php
namespace Fgsl\Groupware\Groupbase\User;

use Fgsl\Groupware\Groupbase\Controller\ControllerInterface;
use Fgsl\Groupware\Groupbase\User\UserInterface;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Group\Group;
use Fgsl\Groupware\Groupbase\Exception\Exception;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Model\FullUser;
use Fgsl\Groupware\Groupbase\TransactionManager;
use Fgsl\Groupware\Groupbase\Exception\Record\NotDefined;
use Fgsl\Groupware\Groupbase\Application\Application;
use Zend\Ldap\Exception\LdapException;
use Zend\Ldap\Ldap;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * User Class
 *
 * @package     Groupbase
 * @subpackage  User
 */
class User implements ControllerInterface
{
    /**
     * backend constants
     * 
     * @var string
     */
    const ACTIVEDIRECTORY = 'ActiveDirectory';
    const LDAP   = 'Ldap';
    const SQL    = 'Sql';
    const TYPO3  = 'Typo3';
    
    /**
     * user status constants
     * 
     * @var string
     * 
     * @todo use constants from model
     */
    const STATUS_BLOCKED  = 'blocked';
    const STATUS_DISABLED = 'disabled';
    const STATUS_ENABLED  = 'enabled';
    const STATUS_EXPIRED  = 'expired';
    
    /**
     * Key under which the default user group name setting will be stored/retrieved
     *
     */
    const DEFAULT_USER_GROUP_NAME_KEY = 'defaultUserGroupName';
    
    /**
     * Key under which the default admin group name setting will be stored/retrieved
     *
     */
    const DEFAULT_ADMIN_GROUP_NAME_KEY = 'defaultAdminGroupName';

    /**
     * Key under which the default anonymous group name setting will be stored/retrieved
     *
     */
    const DEFAULT_ANONYMOUS_GROUP_NAME_KEY = 'defaultAnonymousGroupName';

    const SYSTEM_USER_CRON = 'cronuser';
    const SYSTEM_USER_REPLICATION = 'replicationuser';
    const SYSTEM_USER_ANONYMOUS = 'anonymoususer';
    const SYSTEM_USER_CALENDARSCHEDULING = 'calendarscheduling';
    const SYSTEM_USER_SETUP = 'setupuser';

    /**
     * Do the user sync with the options as configured in the config.
     * see Tinebase_Config:: TODO put key here
     * for details and default behavior
     */
    const SYNC_WITH_CONFIG_OPTIONS = 'sync_with_config_options';

    /**
     * Key under which the default replication group name setting will be stored/retrieved
     */
    const DEFAULT_REPLICATION_GROUP_NAME_KEY = 'defaultReplicationGroupName';

    protected static $_contact2UserMapping = array(
        'n_family'      => 'accountLastName',
        'n_given'       => 'accountFirstName',
        'n_fn'          => 'accountFullName',
        'n_fileas'      => 'accountDisplayName',
        'email'         => 'accountEmailAddress',
        'container_id'  => 'container_id',
    );
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct() {}
    
    /**
     * don't clone. Use the singleton.
     */
    private function __clone() {}

    /**
     * holds the instance of the singleton
     *
     * @var UserInterface
     */
    private static $_instance = NULL;

    /**
     * Holds the accounts backend type (e.g. Ldap or Sql.
     * Property is lazy loaded on first access via getter {@see getConfiguredBackend()}
     * 
     * @var array|null
     */
    private static $_backendType;
    
    /**
     * Holds the backend configuration options.
     * Property is lazy loaded from {@see Tinebase_Config} on first access via
     * getter {@see getBackendConfiguration()}
     * 
     * @var array|null
     */
    private static $_backendConfiguration;
    
    /**
     * Holds the backend configuration options.
     * Property is lazy loaded from {@see Tinebase_Config} on first access via
     * getter {@see getBackendConfiguration()}
     * 
     * @var array|null
     */
    private static $_backendConfigurationDefaults = array(
        self::SQL => array(
            self::DEFAULT_USER_GROUP_NAME_KEY  => Group::DEFAULT_USER_GROUP,
            self::DEFAULT_ADMIN_GROUP_NAME_KEY => Group::DEFAULT_ADMIN_GROUP,
        ),
        self::LDAP => array(
            'host' => 'localhost',
            'username' => '',
            'password' => '',
            'bindRequiresDn' => true,
            'useStartTls' => false,
            'useSsl' => false,
            'port' => 0,
            'useRfc2307bis' => false,
            'userDn' => '',
            'userFilter' => 'objectclass=posixaccount',
            'userSearchScope' => Ldap::SEARCH_SCOPE_SUB,
            'groupsDn' => '',
            'groupFilter' => 'objectclass=posixgroup',
            'groupSearchScope' => Ldap::SEARCH_SCOPE_SUB,
            'pwEncType' => 'SSHA',
            'minUserId' => '10000',
            'maxUserId' => '29999',
            'minGroupId' => '11000',
            'maxGroupId' => '11099',
            'groupUUIDAttribute' => 'entryUUID',
            'userUUIDAttribute' => 'entryUUID',
            self::DEFAULT_USER_GROUP_NAME_KEY  => Group::DEFAULT_USER_GROUP,
            self::DEFAULT_ADMIN_GROUP_NAME_KEY => Group::DEFAULT_ADMIN_GROUP,
            'readonly' => false,
        ),
        self::ACTIVEDIRECTORY => array(
            'host' => 'localhost',
            'username' => '',
            'password' => '',
            'bindRequiresDn' => true,
            'useStartTls' => false,
            'useRfc2307' => false,
            'userDn' => '',
            'userFilter' => 'objectclass=user',
            'userSearchScope' => Ldap::SEARCH_SCOPE_SUB,
            'groupsDn' => '',
            'groupFilter' => 'objectclass=group',
            'groupSearchScope' => Ldap::SEARCH_SCOPE_SUB,
            'minUserId' => '10000',
            'maxUserId' => '29999',
            'minGroupId' => '11000',
            'maxGroupId' => '11099',
            'groupUUIDAttribute' => 'objectGUID',
            'userUUIDAttribute' => 'objectGUID',
            self::DEFAULT_USER_GROUP_NAME_KEY  => 'Domain Users',
            self::DEFAULT_ADMIN_GROUP_NAME_KEY => 'Domain Admins',
            'readonly' => false,
         )
    );
    
    /**
     * the singleton pattern
     *
     * @return User_Abstract
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            $backendType = self::getConfiguredBackend();
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' accounts backend: ' . $backendType);
            
            self::$_instance = self::factory($backendType);
        }
        
        return self::$_instance;
    }

    public static function destroyInstance()
    {
        self::$_instance = null;
        self::$_backendConfiguration = null;
        self::$_backendType = null;
    }
        
    /**
     * return an instance of the current user backend
     *
     * @param   string $backendType name of the user backend
     * @return  User_Abstract
     * @throws  InvalidArgument
     */
    public static function factory($backendType) 
    {
        $options = self::getBackendConfiguration();
        
        // this is a dangerous TRACE as there might be passwords in here!
        //if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' 
        //    . print_r($options, TRUE));
        
        $options['plugins'] = array(
            Addressbook_Controller_Contact::getInstance(),
        );
        
        // manage email user settings
        if (Tinebase_EmailUser::manages(Tinebase_Config::IMAP)) {
            try {
                $options['plugins'][] = Tinebase_EmailUser::getInstance(Tinebase_Config::IMAP);
            } catch (Exception $e) {
                if (Core::isLogLevel(LogLevel::ERR)) Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                    . ' Could not add IMAP EmailUser plugin: ' . $e);
            }
        }
        if (Tinebase_EmailUser::manages(Tinebase_Config::SMTP)) {
            try {
                $options['plugins'][] = Tinebase_EmailUser::getInstance(Tinebase_Config::SMTP);
                        } catch (Exception $e) {
            if (Core::isLogLevel(LogLevel::ERR)) Core::getLogger()->err(__METHOD__ . '::' . __LINE__
                    . ' Could not add SMTP EmailUser plugin: ' . $e);
            }
        }
        
        switch ($backendType) {
            case self::ACTIVEDIRECTORY:
                $result  = new User_ActiveDirectory($options);
                
                break;
                
            case self::LDAP:
                // manage samba sam?
                if (isset(Core::getConfig()->samba) && Core::getConfig()->samba->get('manageSAM', FALSE) == true) {
                    $options['plugins'][] = new User_Plugin_Samba(Core::getConfig()->samba->toArray());
                }
                
                $result  = new User_Ldap($options);
                
                break;
                
            case self::SQL:
                $result = new User_Sql($options);
                
                break;
            
            case self::TYPO3:
                $result = new User_Typo3($options);
                
                break;
                
            default:
                throw new InvalidArgument("User backend type $backendType not implemented.");
        }

        if ($result instanceof User_Interface_SyncAble) {
            // turn off replicable feature for ModelUser
            ModelUser::setReplicable(false);
        }

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Created user backend of type ' . get_class($result));

        return $result;
    }
    
    /**
     * returns the configured backend
     * 
     * @return string
     */
    public static function getConfiguredBackend()
    {
        if (! isset(self::$_backendType)) {
            if (Application::getInstance()->isInstalled('Tinebase')) {
                self::setBackendType(Tinebase_Config::getInstance()->get(Tinebase_Config::USERBACKENDTYPE, self::SQL));
            } else {
                self::setBackendType(self::SQL);
            }
        }
        
        return self::$_backendType;
    }

    /**
     * setter for {@see $_backendType}
     *
     * @todo persist in db
     *
     * @param string $backendType
     * @throws InvalidArgument
     */
    public static function setBackendType($backendType)
    {
        if (empty($backendType)) {
            throw new InvalidArgument('Backend type can not be empty!');
        }
        
        $newBackendType = ucfirst($backendType);
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Setting backend type to ' . $newBackendType);
        
        self::$_backendType = $newBackendType;
    }

    /**
     * Setter for {@see $_backendConfiguration}
     *
     * NOTE:
     * Setting will not be written to Database or Filesystem.
     * To persist the change call {@see saveBackendConfiguration()}
     *
     * @param mixed $_value
     * @param string $_key
     * @param boolean $_applyDefaults
     * @throws InvalidArgument
     * @todo generalize this (see Tinebase_Auth::setBackendConfiguration)
     */
    public static function setBackendConfiguration($_value, $_key = null, $_applyDefaults = false)
    {
        $defaultValues = self::$_backendConfigurationDefaults[self::getConfiguredBackend()];
        
        if (is_null($_key) && !is_array($_value)) {
            throw new InvalidArgument('To set backend configuration either a key and value '
                . 'parameter are required or the value parameter should be a hash');
        } elseif (is_null($_key) && is_array($_value)) {
            $configToSet = $_applyDefaults ? array_merge($defaultValues, $_value) : $_value;
            foreach ($configToSet as $key => $value) {
                self::setBackendConfiguration($value, $key);
            }
        } else {
            if ( ! (isset($defaultValues[$_key]) || array_key_exists($_key, $defaultValues))) {
                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .
                    " Cannot set backend configuration option '$_key' for accounts storage " . self::getConfiguredBackend());
                return;
            }
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                ' Setting backend key ' . $_key . ' to ' . (preg_match('/password|pwd|pass|passwd/i', $_key) ? '********' : $_value));
            
            self::$_backendConfiguration[$_key] = $_value;
        }
    }
    
    /**
     * Delete the given config setting or all config settings if {@param $_key} is not specified
     * 
     * @param string|null $_key
     * @return void
     */
    public static function deleteBackendConfiguration($_key = null)
    {
        if (is_null($_key)) {
            self::$_backendConfiguration = array();
        } elseif ((isset(self::$_backendConfiguration[$_key]) || array_key_exists($_key, self::$_backendConfiguration))) {
            unset(self::$_backendConfiguration[$_key]);
        } else {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' configuration option does not exist: ' . $_key);
        }
    }
    
    /**
     * Write backend configuration setting {@see $_backendConfigurationSettings} and {@see $_backendType} to
     * db config table.
     * 
     * @return void
     */
    public static function saveBackendConfiguration()
    {
        Tinebase_Config::getInstance()->set(Tinebase_Config::USERBACKEND, self::getBackendConfiguration());
        Tinebase_Config::getInstance()->set(Tinebase_Config::USERBACKENDTYPE, self::getConfiguredBackend());
    }
    
    /**
     * Getter for {@see $_backendConfiguration}
     * 
     * @param string|null $_key
     * @param string|null $_default
     * @return mixed [If {@param $_key} is set then only the specified option is returned, otherwise the whole options hash]
     */
    public static function getBackendConfiguration($_key = null, $_default = null)
    {
        //lazy loading for $_backendConfiguration
        if (!isset(self::$_backendConfiguration)) {
            if (Application::getInstance()->isInstalled('Tinebase')) {
                $rawBackendConfiguration = Tinebase_Config::getInstance()->get(Tinebase_Config::USERBACKEND, new Tinebase_Config_Struct())->toArray();
            } else {
                $rawBackendConfiguration = array();
            }
            self::$_backendConfiguration = is_array($rawBackendConfiguration) ? $rawBackendConfiguration : Zend_Json::decode($rawBackendConfiguration);
        }

        if (isset($_key)) {
            return (isset(self::$_backendConfiguration[$_key]) || array_key_exists($_key, self::$_backendConfiguration)) ? self::$_backendConfiguration[$_key] : $_default;
        } else {
            return self::$_backendConfiguration;
        }
    }
    
    /**
     * Returns default configuration for all supported backends 
     * and overrides the defaults with concrete values stored in this configuration 
     * 
     * @param boolean $_getConfiguredBackend
     * @return mixed [If {@param $_key} is set then only the specified option is returned, otherwise the whole options hash]
     */
    public static function getBackendConfigurationWithDefaults($_getConfiguredBackend = TRUE)
    {
        $config = array();
        $defaultConfig = self::getBackendConfigurationDefaults();
        foreach ($defaultConfig as $backendType => $backendConfig) {
            $config[$backendType] = ($_getConfiguredBackend && $backendType == self::getConfiguredBackend() ? self::getBackendConfiguration() : array());
            if (is_array($config[$backendType])) {
                foreach ($backendConfig as $key => $value) {
                    if (! (isset($config[$backendType][$key]) || array_key_exists($key, $config[$backendType]))) {
                        $config[$backendType][$key] = $value;
                    }
                }
            } else {
                $config[$backendType] = $backendConfig;
            }
        }
        return $config;
    }
    
    /**
     * Getter for {@see $_backendConfigurationDefaults}
     * @param string|null $_backendType
     * @return array
     */
    public static function getBackendConfigurationDefaults($_backendType = null) {
        if ($_backendType) {
            if (!(isset(self::$_backendConfigurationDefaults[$_backendType]) || array_key_exists($_backendType, self::$_backendConfigurationDefaults))) {
                throw new InvalidArgument("Unknown backend type '$_backendType'");
            }
            return self::$_backendConfigurationDefaults[$_backendType];
        } else {
            return self::$_backendConfigurationDefaults;
        }
    }
    
    /**
     * synchronize user from syncbackend to local sql backend
     * 
     * @param  mixed  $username  the login id of the user to synchronize
     * @param  array $options
     * @return FullUser|null
     * @throws Exception
     * 
     * @todo make use of dbmail plugin configurable (should be false by default)
     * @todo switch to new primary group if it could not be found
     */
    public static function syncUser($username, $options = array())
    {
        if ($username instanceof FullUser) {
            $username = $username->accountLoginName;
        }
        
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . "  sync user data for: " . $username);

        if (! Core::getUser() instanceof ModelUser) {
            $setupUser = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly();
            Core::set(Core::USER, $setupUser);
        }

        /** @var User_Ldap $userBackend */
        $userBackend  = User::getInstance();
        if (isset($options['ldapplugins']) && is_array($options['ldapplugins'])) {
            foreach ($options['ldapplugins'] as $plugin) {
                $userBackend->registerLdapPlugin($plugin);
            }
        }
        
        $user = $userBackend->getUserByPropertyFromSyncBackend('accountLoginName', $username, 'FullUser');
        $user->accountPrimaryGroup = Group::getInstance()->resolveGIdNumberToUUId($user->accountPrimaryGroup);
        
        $userProperties = method_exists($userBackend, 'getLastUserProperties') ? $userBackend->getLastUserProperties() : array();


        $hookResult = self::_syncUserHook($user, $userProperties);
        if (! $hookResult) {
            return null;
        }


        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' 
            . print_r($user->toArray(), TRUE));

        $oldContainerAcl = Addressbook_Controller_Contact::getInstance()->doContainerACLChecks(false);
        $oldRequestContext = Addressbook_Controller_Contact::getInstance()->getRequestContext();

        $requestContext = array();
        if (!isset($options['syncContactPhoto']) || !$options['syncContactPhoto']) {
            $requestContext[Addressbook_Controller_Contact::CONTEXT_NO_SYNC_PHOTO] = true;
        }
        if (!isset($options['syncContactData']) || !$options['syncContactData']) {
            $requestContext[Addressbook_Controller_Contact::CONTEXT_NO_SYNC_CONTACT_DATA] = true;
        }
        Addressbook_Controller_Contact::getInstance()->setRequestContext($requestContext);

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {

            self::getPrimaryGroupForUser($user);

            try {

                // this will User::getInstance()->updatePluginUser
                // the addressbook is registered as a plugin
                $syncedUser = self::_syncDataAndUpdateUser($user, $options);

            } catch (Exception_NotFound $ten) {
                try {
                    $invalidUser = $userBackend->getUserByPropertyFromSqlBackend('accountLoginName', $username, 'FullUser');
                    if (isset($options['deleteUsers']) && $options['deleteUsers']) {
                        // handle removed users differently with "sync deleted users" config
                        if (Core::isLogLevel(LogLevel::WARN)) Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                            . " Skipping user: " . $username . '. Do not remove as it might be the same user as before with different ID.');
                        TransactionManager::getInstance()->commitTransaction($transactionId);
                        $transactionId = null;
                        return null;
                    }

                    if (Core::isLogLevel(LogLevel::CRIT)) Core::getLogger()->crit(__METHOD__ . '::' . __LINE__
                        . " Remove invalid user: " . $username);
                    // this will fire a delete event
                    $userBackend->deleteUserInSqlBackend($invalidUser);
                } catch (Exception_NotFound $ten) {
                    // do nothing
                }

                $visibility = $user->visibility;
                if ($visibility === null) {
                    $visibility = FullUser::VISIBILITY_DISPLAYED;
                }

                ModificationLog::setRecordMetaData($user, 'create');
                $syncedUser = $userBackend->addUserInSqlBackend($user);

                // fire event to make sure all user data is created in the apps
                // TODO convert to Tinebase event?
                $event = new Admin_Event_AddAccount(array(
                    'account' => $syncedUser
                ));
                Tinebase_Event::fireEvent($event);

                // the addressbook is registered as a plugin and will take care of the create
                // see \Addressbook_Controller_Contact::inspectUpdateUser
                $userBackend->addPluginUser($syncedUser, $user);

                $contactId = $syncedUser->contact_id;
                if (!empty($contactId) && $visibility != $syncedUser->visibility) {
                    $syncedUser->visibility = $visibility;
                    $syncedUser = User::getInstance()->updateUserInSqlBackend($syncedUser);
                    User::getInstance()->updatePluginUser($syncedUser, $user);
                }
            }

            Group::syncMemberships($syncedUser);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        Addressbook_Controller_Contact::getInstance()->setRequestContext($oldRequestContext === null ? array() : $oldRequestContext);
        Addressbook_Controller_Contact::getInstance()->doContainerACLChecks($oldContainerAcl);

        return $syncedUser;
    }

    /**
     * sync account data and update
     *
     * @param FullUser $user
     * @param array $options
     * @return mixed
     * @throws InvalidArgument
     */
    protected static function _syncDataAndUpdateUser($user, $options)
    {
        $currentUser = User::getInstance()->getUserByProperty('accountId', $user, 'FullUser');

        $fieldsToSync = array('accountLoginName', 'accountLastPasswordChange', 'accountExpires', 'accountPrimaryGroup',
            'accountDisplayName', 'accountLastName', 'accountFirstName', 'accountFullName', 'accountEmailAddress',
            'accountHomeDirectory', 'accountLoginShell');
        if (isset($options['syncAccountStatus']) && $options['syncAccountStatus']) {
            $fieldsToSync[] = 'accountStatus';
        }
        $recordNeedsUpdate = false;
        foreach ($fieldsToSync as $field) {
            if ($currentUser->{$field} !== $user->{$field}) {
                $currentUser->{$field} = $user->{$field};
                $recordNeedsUpdate = true;
            }
        }

        if ($recordNeedsUpdate) {
            ModificationLog::setRecordMetaData($currentUser, 'update');
            $syncedUser = User::getInstance()->updateUserInSqlBackend($currentUser);
        } else {
            $syncedUser = $currentUser;
        }
        if (! empty($user->container_id)) {
            $syncedUser->container_id = $user->container_id;
        }

        // Addressbook is registered as plugin and will take care of the update
        User::getInstance()->updatePluginUser($syncedUser, $user);

        return $syncedUser;
    }
    
    /**
     * get primary group for user and make sure that group exists
     * 
     * @param FullUser $user
     * @throws Exception
     * @return ModelGroup
     */
    protected static function getPrimaryGroupForUser($user)
    {
        $groupBackend = Group::getInstance();
        
        try {
            $group = $groupBackend->getGroupById($user->accountPrimaryGroup);
        } catch (NotDefined $tern) {
            if ($groupBackend->isDisabledBackend()) {
                // groups are sql only
                $group = $groupBackend->getDefaultGroup();
                $user->accountPrimaryGroup = $group->getId();
            } else {
                try {
                    $group = $groupBackend->getGroupByIdFromSyncBackend($user->accountPrimaryGroup);
                } catch (NotDefined $ternd) {
                    throw new Exception('Primary group ' . $user->accountPrimaryGroup . ' not found in sync backend.');
                }
                try {
                    $groupBackend->getGroupByName($group->name);
                    throw new Exception('Group already exists but it has a different ID: ' . $group->name);
        
                } catch (NotDefined $tern) {
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                        . " Adding group " . $group->name);

                    $transactionId = TransactionManager::getInstance()
                        ->startTransaction(Core::getDb());
                    try {
                        if (Application::getInstance()->isInstalled('Addressbook')) {
                            // here it should be ok to create the list without members
                            Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($group);
                        }
                        $group = $groupBackend->addGroupInSqlBackend($group);

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
        
        return $group;
    }
    
    /**
     * call configured hooks for adjusting synced user data
     * 
     * @param FullUser $user
     * @param array $userProperties
     * @return boolean if false, user is skipped
     */
    protected static function _syncUserHook(FullUser $user, $userProperties)
    {
        $result = true;
        $hookClass = Tinebase_Config::getInstance()->get(Tinebase_Config::SYNC_USER_HOOK_CLASS);
        if ($hookClass) {
            if (! class_exists($hookClass)) {
                @include($hookClass . '.php');
            }

            if (class_exists($hookClass)) {
                $hook = new $hookClass();
                if (method_exists($hook, 'syncUser')) {
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' Calling ' . $hookClass . '::syncUser() ...');

                    try {
                        $result = call_user_func_array(array($hook, 'syncUser'), array($user, $userProperties));
                    } catch (Exception $te) {
                        Exception::log($te);
                        return false;
                    }
                }
            }
        }
        
        return $result;
    }

    /**
     * sync user data to contact
     * 
     * @param FullUser $user
     * @param Addressbook_Model_Contact $contact
     * @return Addressbook_Model_Contact
     */
    public static function user2Contact($user, $contact = null)
    {
        if ($contact === null) {
            $contact = new Addressbook_Model_Contact(array(), true);
        }
        
        $contact->type = Addressbook_Model_Contact::CONTACTTYPE_USER;
        
        foreach (self::$_contact2UserMapping as $contactKey => $userKey) {
            if (! empty($contact->{$contactKey}) && $contact->{$contactKey} == $user->{$userKey}) {
                continue;
            }
            
            switch ($contactKey) {
                case 'container_id':
                    $contact->container_id = (! empty($user->container_id)) ? $user->container_id : Admin_Controller_User::getInstance()->getDefaultInternalAddressbook();
                    break;
                default:
                    $contact->{$contactKey} = $user->{$userKey};
            }
        }

        if ($contact->n_fn !== $user->accountFullName) {
            // use accountFullName overwrites contact n_fn
            $contact->n_fn = $user->accountFullName;
        }

        $contact->account_id = $user->getId();

        return $contact;
    }
    
    /**
     * import users from sync backend
     * 
     * @param array $options
     * @return bool
     */
    public static function syncUsers($options = array())
    {
        if (isset($options[self::SYNC_WITH_CONFIG_OPTIONS]) && $options[self::SYNC_WITH_CONFIG_OPTIONS]) {
            $syncOptions = Tinebase_Config::getInstance()->get(Tinebase_Config::USERBACKEND)->{Tinebase_Config::SYNCOPTIONS};
            if (!isset($options['deleteUsers'])) {
                $options['deleteUsers'] = $syncOptions->{Tinebase_Config::SYNC_DELETED_USER};
            }
            if (!isset($options['syncContactPhoto'])) {
                $options['syncContactPhoto'] = $syncOptions->{Tinebase_Config::SYNC_USER_CONTACT_PHOTO};
            }
            if (!isset($options['syncContactData'])) {
                $options['syncContactData'] = $syncOptions->{Tinebase_Config::SYNC_USER_CONTACT_DATA};
            }
        }

        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Start synchronizing users with options ' . print_r($options, true));

        if (! User::getInstance() instanceof User_Interface_SyncAble) {
            if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' User backend is not instanceof User_Ldap, nothing to sync');
            return true;
        }
        
        $users = User::getInstance()->getUsersFromSyncBackend(NULL, NULL, 'ASC', NULL, NULL, 'FullUser');
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' About to sync ' . count($users) . ' users from sync backend ...');

        $result = true;
        foreach ($users as $user) {
            try {
                self::syncUser($user, $options);

                Tinebase_Lock::keepLocksAlive();
            } catch (Exception $e) {
                $result = false;
                Exception::log($e, null, $user->toArray());
            }
        }

        if (isset($options['deleteUsers']) && $options['deleteUsers']) {
            self::_syncDeletedUsers($users);
        }

        Group::getInstance()->resetClassCache();
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Finished synchronizing users.');

        return $result;
    }

    /**
     * deletes user in tine20 db that no longer exist in sync backend
     *
     * @param RecordSet $usersInSyncBackend
     */
    protected static function _syncDeletedUsers(RecordSet $usersInSyncBackend)
    {
        $oldContainerAcl = Addressbook_Controller_Contact::getInstance()->doContainerACLChecks(false);

        $userIdsInSqlBackend = User::getInstance()->getAllUserIdsFromSqlBackend();
        $deletedInSyncBackend = array_diff($userIdsInSqlBackend, $usersInSyncBackend->getArrayOfIds());

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' About to delete / expire ' . count($deletedInSyncBackend) . ' users in SQL backend...');

        foreach ($deletedInSyncBackend as $userToDelete) {
            $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
            try {
                $user = User::getInstance()->getUserByPropertyFromSqlBackend('accountId', $userToDelete, 'FullUser');

                if (in_array($user->accountLoginName, self::getSystemUsernames())) {
                    TransactionManager::getInstance()->commitTransaction($transactionId);
                    $transactionId = null;
                    return;
                }

                // at first, we expire+deactivate the user
                $now = Tinebase_DateTime::now();
                if (! $user->accountExpires || $user->accountStatus !== ModelUser::ACCOUNT_STATUS_DISABLED) {
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' Disable user and set expiry date of ' . $user->accountLoginName . ' to ' . $now);
                    $user->accountExpires = $now;
                    $user->accountStatus = ModelUser::ACCOUNT_STATUS_DISABLED;
                    User::getInstance()->updateUserInSqlBackend($user);
                } else {
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' User already expired ' . print_r($user->toArray(), true));

                    $deleteAfterMonths = Tinebase_Config::getInstance()->get(Tinebase_Config::SYNC_USER_DELETE_AFTER);
                    if ($user->accountExpires->isEarlier($now->subMonth($deleteAfterMonths))) {
                        // if he or she is already expired longer than configured expiry, we remove them!
                        // this will trigger the plugin Addressbook which will make a soft delete and especially runs the addressbook sync backends if any configured
                        User::getInstance()->deleteUser($userToDelete);

                        // now we make the addressbook hard delete, which is ok, because we went through the addressbook_controller_contact::delete already
                        if (Application::getInstance()->isInstalled('Addressbook') === true && ! empty($user->contact_id)) {
                            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                                . ' Deleting user contact of ' . $user->accountLoginName);

                            $contactsBackend = Addressbook_Backend_Factory::factory(Addressbook_Backend_Factory::SQL);
                            $contactsBackend->delete($user->contact_id);
                        }
                    } else {
                        // keep user in expiry state
                    }
                }

                TransactionManager::getInstance()->commitTransaction($transactionId);
                $transactionId = null;
            } finally {
                if (null !== $transactionId) {
                    TransactionManager::getInstance()->rollBack();
                }
            }

            Tinebase_Lock::keepLocksAlive();
        }

        Addressbook_Controller_Contact::getInstance()->doContainerACLChecks($oldContainerAcl);
    }

    /**
     * returns login_names of system users
     *
     * @return array
     */
    public static function getSystemUsernames()
    {
        return [self::SYSTEM_USER_CRON, self::SYSTEM_USER_CALENDARSCHEDULING, self::SYSTEM_USER_SETUP,
            self::SYSTEM_USER_REPLICATION, self::SYSTEM_USER_ANONYMOUS];
    }

    /**
     * get all user passwords from ldap
     * - set pw for user (in sql and sql plugins)
     * - do not encrypt the pw again as it is encrypted in LDAP
     * 
     * @throws Exception_Backend
     */
    public static function syncLdapPasswords()
    {
        $userBackend = User::getInstance();
        if (! $userBackend instanceof User_Ldap) {
            throw new Exception_Backend('Needs LDAP accounts backend');
        }
        
        $result = $userBackend->getUserAttributes(array('entryUUID', 'userPassword'));
        
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' About to sync ' . count($result) . ' user passwords from LDAP to Tine 2.0.');
        
        $sqlBackend = User::factory(self::SQL);
        foreach ($result as $user) {
            try {
                $sqlBackend->setPassword($user['entryUUID'], $user['userPassword'], FALSE);
            } catch (Exception_NotFound $tenf) {
                if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                    . ' Could not find user with id ' . $user['entryUUID'] . ' in SQL backend.');
            }
        }
    }
    
    /**
     * create initial admin account
     * 
     * Method is called during Setup Initialization
     *
     * $_options may contain the following keys:
     * <code>
     * $options = array(
     *  'adminLoginName'    => 'admin',
     *  'adminPassword'     => 'lars',
     *  'adminFirstName'    => 'Tine 2.0',
     *  'adminLastName'     => 'Admin Account',
     *  'adminEmailAddress' => 'admin@tine20domain.org',
     *  'expires'            => Tinebase_DateTime object
     * );
     * </code>
     *
     * @param array $_options [hash that may contain override values for admin user name and password]
     * @return void
     * @throws InvalidArgument
     */
    public static function createInitialAccounts($_options)
    {
        if (! isset($_options['adminPassword']) || ! isset($_options['adminLoginName'])) {
            throw new InvalidArgument('Admin password and login name have to be set when creating initial account.', 503);
        }

        $addressBookController = Addressbook_Controller_Contact::getInstance();

        // make sure we have a setup user:
        $setupUser = Setup_Update_Abstract::getSetupFromConfigOrCreateOnTheFly();
        if (! Core::getUser() instanceof ModelUser) {
            Core::set(Core::USER, $setupUser);
        }

        // create the replication user
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Creating new replication user.');

        $replicationUser = static::createSystemUser(User::SYSTEM_USER_REPLICATION,
            Group::getInstance()->getDefaultReplicationGroup());
        if (null !== $replicationUser) {
            $replicationMasterConf = Tinebase_Config::getInstance()->get(Tinebase_Config::REPLICATION_MASTER);
            if (empty(($password = $replicationMasterConf->{Tinebase_Config::REPLICATION_USER_PASSWORD}))) {
                $password = Tinebase_Record_Abstract::generateUID(12);
            }
            User::getInstance()->setPassword($replicationUser, $password);
        }

        // create the anonymous user
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Creating new anonymous user.');

        static::createSystemUser(User::SYSTEM_USER_ANONYMOUS,
            Group::getInstance()->getDefaultAnonymousGroup());

        $oldAcl = $addressBookController->doContainerACLChecks(false);
        $oldRequestContext = $addressBookController->getRequestContext();
        $requestContext = array(
            Addressbook_Controller_Contact::CONTEXT_ALLOW_CREATE_USER => true,
            Addressbook_Controller_Contact::CONTEXT_NO_ACCOUNT_UPDATE => true,
        );
        $addressBookController->setRequestContext($requestContext);


        $adminLoginName     = $_options['adminLoginName'];
        $adminPassword      = $_options['adminPassword'];
        $adminFirstName     = isset($_options['adminFirstName'])    ? $_options['adminFirstName'] : 'Tine 2.0';
        $adminLastName      = isset($_options['adminLastName'])     ? $_options['adminLastName']  : 'Admin Account';
        $adminEmailAddress  = ((isset($_options['adminEmailAddress']) || array_key_exists('adminEmailAddress', $_options))) ? $_options['adminEmailAddress'] : NULL;

        $userBackend   = User::getInstance();
        $groupsBackend = Group::getInstance();

        // get admin & user groupss
        $adminGroup = $groupsBackend->getDefaultAdminGroup();
        $userGroup  = $groupsBackend->getDefaultGroup();
        
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Creating initial admin user (login: ' . $adminLoginName . ' / email: ' . $adminEmailAddress . ')');

        $user = new FullUser(array(
            'accountLoginName'      => $adminLoginName,
            'accountStatus'         => ModelUser::ACCOUNT_STATUS_ENABLED,
            'accountPrimaryGroup'   => $userGroup->getId(),
            'accountLastName'       => $adminLastName,
            'accountDisplayName'    => $adminLastName . ', ' . $adminFirstName,
            'accountFirstName'      => $adminFirstName,
            'accountExpires'        => (isset($_options['expires'])) ? $_options['expires'] : NULL,
            'accountEmailAddress'   => $adminEmailAddress
        ));
        
        if ($adminEmailAddress !== NULL) {
            $user->imapUser = new Tinebase_Model_EmailUser(array(
                'emailPassword' => $adminPassword
            ));
            $user->smtpUser = new Tinebase_Model_EmailUser(array(
                'emailPassword' => $adminPassword
            ));
        }

        // update or create user in local sql backend
        try {
            $existingUser = $userBackend->getUserByProperty('accountLoginName', $adminLoginName);
            $user->setId($existingUser->getId());
            $user->contact_id = $existingUser->contact_id;
            ModificationLog::setRecordMetaData($user, 'update');
            $user = $userBackend->updateUserInSqlBackend($user);
            // Addressbook is registered as plugin and will take care of the update
            $userBackend->updatePluginUser($user, $user);
        } catch (Exception_NotFound $ten) {
            // call addUser here to make sure, sql user plugins (email, ...) are triggered
            ModificationLog::setRecordMetaData($user, 'create');
            $user = $userBackend->addUser($user);
        }
        
        // set the password for the account
        // empty password triggers password change dialogue during first login
        if (!empty($adminPassword)) {
            User::getInstance()->setPassword($user, $adminPassword);
        }

        // add the admin account to all groups
        Group::getInstance()->addGroupMember($adminGroup, $user);
        Group::getInstance()->addGroupMember($userGroup, $user);

        $addressBookController->doContainerACLChecks($oldAcl);
        $addressBookController->setRequestContext($oldRequestContext === null ? array() : $oldRequestContext);
    }

    /**
     * create new system user
     *
     * @param string $accountLoginName
     * @param Group $defaultGroup
     * @return FullUser|null
     */
    static public function createSystemUser($accountLoginName, $defaultGroup = null)
    {
        try {
            $systemUser = User::getInstance()->getFullUserByLoginName($accountLoginName);
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                ' Use existing system user ' . $accountLoginName);
            return $systemUser;
        } catch (Exception_NotFound $tenf) {
            // continue
        }

        // disable modlog stuff
        $oldGroupValue = Group::getInstance()->modlogActive(false);
        $oldUserValue = User::getInstance()->modlogActive(false);
        $plugin = User::getInstance()->removePlugin(Addressbook_Controller_Contact::getInstance());

        if (null === $defaultGroup) {
            $defaultGroup = Group::getInstance()->getDefaultAdminGroup();
        }
        $systemUser = new FullUser(array(
            'accountLoginName' => $accountLoginName,
            'accountStatus' => ModelUser::ACCOUNT_STATUS_DISABLED,
            'visibility' => FullUser::VISIBILITY_HIDDEN,
            'accountPrimaryGroup' => $defaultGroup->getId(),
            'accountLastName' => $accountLoginName,
            'accountDisplayName' => $accountLoginName,
            'accountExpires' => NULL,
        ));

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Creating new system user ' . print_r($systemUser->toArray(), true));

        if (Application::getInstance()->isInstalled('Addressbook') === true) {
            $contact = Admin_Controller_User::getInstance()->createOrUpdateContact($systemUser, /* setModlog */ false);
            $systemUser->contact_id = $contact->getId();
        }

        try {
            $systemUser = User::getInstance()->addUser($systemUser);
            Group::getInstance()->addGroupMember($systemUser->accountPrimaryGroup, $systemUser->getId());
        } catch(LdapException $zle) {
            Exception::log($zle);
            if (stripos($zle->getMessage(), 'Already exists') !== false) {
                try {
                    $user = User::getInstance()->getUserByPropertyFromSyncBackend(
                        'accountLoginName',
                        $accountLoginName,
                        'Fgsl\Groupware\Groupbase\Model\User\FullUser'
                    );
                    ModificationLog::setRecordMetaData($user, 'create');
                    $systemUser->merge($user);
                    if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                        ' Creating new (sql) system user ' . print_r($systemUser->toArray(), true));
                    $systemUser = User::getInstance()->addUserInSqlBackend($systemUser);
                    Group::getInstance()->addGroupMember($systemUser->accountPrimaryGroup, $systemUser->getId());
                } catch(Exception $e) {
                    if (Core::isLogLevel(LogLevel::ERR)) Core::getLogger()->err(__METHOD__ . '::' . __LINE__ .
                        ' no system user could be created');
                    // TODO we should try to fetch an admin user here (see Sales_Setup_Update_Release8::_updateContractsFields)
                    Exception::log($e);
                    $systemUser = null;
                }
            } else {
                try {
                    $systemUser = User::getInstance()->addUserInSqlBackend($systemUser);
                    Group::getInstance()->addGroupMember($systemUser->accountPrimaryGroup, $systemUser->getId());
                } catch(Exception $e) {
                    if (Core::isLogLevel(LogLevel::ERR)) Core::getLogger()->err(__METHOD__ . '::' . __LINE__ .
                        ' no system user could be created');
                    // TODO we should try to fetch an admin user here (see Sales_Setup_Update_Release8::_updateContractsFields)
                    Exception::log($e);
                    $systemUser = null;
                }
            }

        } catch (Exception $e) {
            if (Core::isLogLevel(LogLevel::ERR)) Core::getLogger()->err(__METHOD__ . '::' . __LINE__ .
                ' no system user could be created');
            // TODO we should try to fetch an admin user here (see Sales_Setup_Update_Release8::_updateContractsFields)
            Exception::log($e);
            try {
                $systemUser = User::getInstance()->addUserInSqlBackend($systemUser);
                Group::getInstance()->addGroupMember($systemUser->accountPrimaryGroup, $systemUser->getId());
            } catch(Exception $e) {
                if (Core::isLogLevel(LogLevel::ERR)) Core::getLogger()->err(__METHOD__ . '::' . __LINE__ .
                    ' no system user could be created');
                // TODO we should try to fetch an admin user here (see Sales_Setup_Update_Release8::_updateContractsFields)
                Exception::log($e);
                $systemUser = null;
            }
        }

        // re-enable modlog stuff
        Group::getInstance()->modlogActive($oldGroupValue);
        User::getInstance()->modlogActive($oldUserValue);
        if (null !== $plugin) {
            User::getInstance()->registerPlugin($plugin);
        }

        return $systemUser;
    }
}
