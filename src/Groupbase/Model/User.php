<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Zend\Filter\StringTrim;
use Zend\InputFilter\Input;
use Zend\Filter\StringToLower;
use Fgsl\Groupware\Groupbase\Acl\Roles;
use Fgsl\Groupware\Groupbase\Group\Group;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\Record\NotDefined;
use Fgsl\Groupware\Groupbase\User\User as GroupbaseUser;
use Fgsl\Groupware\Groupbase\Controller\Controller;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Container;
use Fgsl\Groupware\Groupbase\FileSystem\FileSystem;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Model\Grants as ModelGrants;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */


/**
 * defines the datatype for simple user object
 * 
 * this user object contains only public informations
 * its primary usecase are user selection interfaces
 * 
 * @package     Groupbase
 * @subpackage  User
 * 
 * @property    string  accountId
 * @property    string  contact_id
 * @property    string  accountEmailAddress  email address of user
 * @property    string  accountDisplayName
 * @property    string  accountLastName
 * @property    string  accountFirstName
 */
class User extends AbstractRecord
{
    /**
     * const to describe current account accountId independent
     * 
     * @var string
     */
    const CURRENTACCOUNT = 'currentAccount';
    
    /**
     * hidden from addressbook
     * 
     * @var string
     */
    const VISIBILITY_HIDDEN    = 'hidden';
    
    /**
     * visible in addressbook
     * 
     * @var string
     */
    const VISIBILITY_DISPLAYED = 'displayed';
    
    /**
     * account is enabled
     * 
     * @var string
     */
    const ACCOUNT_STATUS_ENABLED = 'enabled';
    
    /**
     * account is disabled
     * 
     * @var string
     */
    const ACCOUNT_STATUS_DISABLED = 'disabled';
    
    /**
     * account is expired
     * 
     * @var string
     */
    const ACCOUNT_STATUS_EXPIRED = 'expired';
    
    /**
     * account is blocked
     * 
     * @var string
     */
    const ACCOUNT_STATUS_BLOCKED  = 'blocked';

    /**
     * key in $_validators/$_properties array for the filed which
     * represents the identifier
     *
     * @var string
     */
    protected $_identifier = 'accountId';

    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var ModelConfiguration
     */
    protected static $_configurationObject = NULL;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = [
        'recordName'        => 'User',
        'recordsName'       => 'Users', // ngettext('User', 'Users', n)
        'hasRelations'      => false,
        'hasCustomFields'   => false,
        'hasNotes'          => false,
        'hasTags'           => false,
        'hasXProps'         => true,
        'modlogActive'      => true,
        'hasAttachments'    => false,
        'createModule'      => false,
        'exposeHttpApi'     => false,
        'exposeJsonApi'     => false,

        'titleProperty'     => 'accountDisplayName',
        'appName'           => 'Tinebase',
        'modelName'         => 'User',
        'idProperty'        => 'accountId',

        'filterModel'       => [],

        'fields'            => [
            'accountDisplayName'            => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'accountLastName'               => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'accountFirstName'              => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'accountEmailAddress'           => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
                'inputFilters'                  => [
                    StringTrim::class => null,
                    StringToLower::class => null,
                ],
            ],
            'accountFullName'               => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'contact_id'                    => [
                //'type'                          => 'record',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
        ],
    ];

    /**
     * if foreign Id fields should be resolved on search and get from json
     * should have this format:
     *     array('Calendar_Model_Contact' => 'contact_id', ...)
     * or for more fields:
     *     array('Calendar_Model_Contact' => array('contact_id', 'customer_id), ...)
     * (e.g. resolves contact_id with the corresponding Model)
     *
     * @var array
     */
    protected static $_resolveForeignIdFields = array(
        'ModelUser'        => array('created_by', 'last_modified_by')
    );

    protected static $_replicable = true;

    protected static $_forceSuperUser = false;
    
    /**
     * (non-PHPdoc)
     * @see Tinebase/Record/Tinebase_Record_Abstract#setFromArray($_data)
     * 
     * @todo need to discuss if this is the right place to do this. perhaps the client should send the fullname (and displayname), too.
     */
    public function setFromArray(array &$_data)
    {
        // always update accountDisplayName and accountFullName
        if (isset($_data['accountLastName'])) {
            $_data['accountDisplayName'] = $_data['accountLastName'];
            if (!empty($_data['accountFirstName'])) {
                $_data['accountDisplayName'] .= ', ' . $_data['accountFirstName'];
            }
            
            if (! (isset($_data['accountFullName']) || array_key_exists('accountFullName', $_data))) {
                $_data['accountFullName'] = $_data['accountLastName'];
                if (!empty($_data['accountFirstName'])) {
                    $_data['accountFullName'] = $_data['accountFirstName'] . ' ' . $_data['accountLastName'];
                }
            }
        }
        
        parent::setFromArray($_data);
    }
    

    
    /**
     * check if current user has a given right for a given application
     *
     * @param string|ModelApplication $_application the application (one of: app name, id or record)
     * @param int $_right the right to check for
     * @return bool
     */
    public function hasRight($_application, $_right)
    {
        if (true === static::$_forceSuperUser) {
            return true;
        }

        $roles = Roles::getInstance();
        
        $result = $roles->hasRight($_application, $this->accountId, $_right);
        
        return $result;
    }
    
    /**
     * returns a bitmask of rights for current user and given application
     *
     * @param string $_application the name of the application
     * @return int bitmask of rights
     */
    public function getRights($_application)
    {
        $roles = Roles::getInstance();
        
        $result = $roles->getApplicationRights($_application, $this->accountId);
        
        return $result;
    }
    
    /**
     * return the group ids current user is member of
     *
     * @return array list of group ids
     */
    public function getGroupMemberships()
    {
        $backend = Group::getInstance();
        
        $result = $backend->getGroupMemberships($this->accountId);
        
        return $result;
    }
    
    /**
     * update the lastlogin time of current user
     *
     * @param string $_ipAddress
     * @return void
     * @todo write test for that
    */
    public function setLoginTime($_ipAddress)
    {
        $backend = GroupbaseUser::getInstance();
        
        $result = $backend->setLoginTime($this->accountId, $_ipAddress);
        
        return $result;
    }
    
    /**
     * set the password for current user
     *
     * @param string $_password
     * @return void
     */
    public function setPassword($_password)
    {
        $backend = GroupbaseUser::getInstance();
        $backend->setPassword($this->accountId, $_password);
    }
    
    /**
     * returns list of applications the current user is able to use
     *
     * this function takes group memberships into user. Applications the user is able to use
     * must have the 'run' right set 
     * 
     * @param boolean $_anyRight is any right enough to geht app?
     * @return array list of enabled applications for this user
     */
    public function getApplications($_anyRight = FALSE)
    {
        $roles = Roles::getInstance();
        
        $result = $roles->getApplications($this->accountId, $_anyRight);
        
        if (Controller::getInstance()->userAccountChanged()) {
            // TODO this information should be saved in application table
            $disabledAppsForChangedUserAccounts = array('Felamimail');
            foreach ($result as $key => $app) {
                if (in_array($app, $disabledAppsForChangedUserAccounts)) {
                    if (Core::isLogLevel(LogLevel::DEBUG)) {
                        Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                        . ' Skipping ' . $app . ' because app is disabled for changed user accounts');
                    }
                    unset($result[$key]);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * return all container, which the user has the requested right for
     *
     * used to get a list of all containers accesssible by the current user
     * 
     * @param string $_application the application name
     * @param int $_right the required right
     * @param   bool   $_onlyIds return only ids
     * @return RecordSet|array
     * @todo write test for that
     */
    public function getContainerByACL($_application, $_right, $_onlyIds = FALSE)
    {
        $container = Container::getInstance();
        
        $result = $container->getContainerByACL($this->accountId, $_application, $_right, $_onlyIds);
        
        return $result;
    }

    /**
     * return all personal container of the current user
     *
     * used to get a list of all personal containers accesssible by the current user
     * 
     * @param string $_application the application name
     * @return RecordSet
     * @todo write test for that
     */
    public function getPersonalContainer($_application, $_owner, $_grant)
    {
        $container = Container::getInstance();
        
        $result = $container->getPersonalContainer($this, $_application, $_owner, $_grant);
        
        return $result;
    }
    
    /**
     * get shared containers
     * 
     * @param string|ModelApplication $_application
     * @param array|string $_grant
     * @return RecordSet set of Tinebase_Model_Container
     */
    public function getSharedContainer($_application, $_grant)
    {
        $container = Container::getInstance();
        
        $result = $container->getSharedContainer($this, $_application, $_grant);
        
        return $result;
    }
    
    /**
     * get containers of other users
     * 
     * @param string|ModelApplication $_application
     * @param array|string $_grant
     * @return  RecordSet set of Tinebase_Model_Container
     */
    public function getOtherUsersContainer($_application, $_grant)
    {
        $container = Container::getInstance();
        
        $result = $container->getOtherUsersContainer($this, $_application, $_grant);
        
        return $result;
    }
    
    /**
     * check if the current user has a given grant
     *
     * @param mixed $_containerId
     * @param string $_grant
     * @param string $_aclModel
     * @return boolean
     * @throws InvalidArgument
     *
     * TODO improve handling of different acl models
     */
    public function hasGrant($_containerId, $_grant, $_aclModel = 'Tinebase_Model_Container')
    {
        if (true === static::$_forceSuperUser) {
            return true;
        }

        if ($_containerId instanceof RecordInterface) {
            $aclModel = get_class($_containerId);
            if (! in_array($aclModel, array('Tinebase_Model_Container', 'Tinebase_Model_Tree_Node'))) {
                // fall back to param
                $aclModel = $_aclModel;
            }
        } else {
            $aclModel = $_aclModel;
        }

        switch ($aclModel) {
            case 'Tinebase_Model_Container':
                $result = Container::getInstance()->hasGrant($this->accountId, $_containerId, $_grant);
                break;
            case 'Tinebase_Model_Tree_Node':
                $result = FileSystem::getInstance()->hasGrant($this->accountId, $_containerId, $_grant);
                break;
            default:
                throw new InvalidArgument('ACL model not supported ');
        }

        if (!$result && ModelGrants::GRANT_ADMIN !== $_grant) {
            return $this->hasGrant($_containerId, ModelGrants::GRANT_ADMIN, $_aclModel);
        }

        return $result;
    }
    
    /**
     * converts a int, string or ModelUser to an accountid
     *
     * @param int|string|User $_accountId the accountid to convert
     * @return string
     * @throws InvalidArgument
     * 
     * TODO completely replace with TRA::convertId
     */
    static public function convertUserIdToInt($_accountId)
    {
        return (string) self::convertId($_accountId, 'Fgsl\Groupware\Groupbase\Model\User');
    }
    
    /**
     * sanitizes account primary group and returns primary group id
     * 
     * @return string
     */
    public function sanitizeAccountPrimaryGroup()
    {
        try {
            Group::getInstance()->getGroupById($this->accountPrimaryGroup);
        } catch (NotDefined $e) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .' Could not resolve accountPrimaryGroupgroup (' . $this->accountPrimaryGroup . '): ' . $e->getMessage() . ' => set default user group id as accountPrimaryGroup for account ' . $this->getId());
            $this->accountPrimaryGroup = Group::getInstance()->getDefaultGroup()->getId();
        }
        
        return $this->accountPrimaryGroup;
    }

    /**
     * returns true if this record should be replicated
     *
     * @return boolean
     */
    public function isReplicable()
    {
        return static::$_replicable;
    }

    /**
     * @param boolean $isReplicable
     */
    public static function setReplicable($isReplicable)
    {
        static::$_replicable = (bool)$isReplicable;
    }

    /**
     * @param bool $bool
     */
    public static function forceSuperUser($bool = true)
    {
        static::$_forceSuperUser = (bool)$bool;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->accountDisplayName;
    }
}
