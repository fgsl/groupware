<?php
namespace Fgsl\Groupware\Groupbase\Group;

use Fgsl\Groupware\Groupbase\Model\Group as ModelGroup;
use Fgsl\Groupware\Groupbase\Exception\Record\NotDefined;
use Fgsl\Groupware\Groupbase\Translation;
use Fgsl\Groupware\Groupbase\TransactionManager;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\User;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Application\Application;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * abstract class for all group backends
 *
 * @package     Groupbase
 * @subpackage  Group
 */
 
abstract class AbstractGroup
{
    /**
     * in class cache 
     * 
     * @var array
     */
    protected $_classCache = array ();
    
    /**
     * return all groups an account is member of
     *
     * @param mixed $_accountId the account as integer or ModelUser
     * @return array
     */
    abstract public function getGroupMemberships($_accountId);
    
    /**
     * get list of groupmembers
     *
     * @param int $_groupId
     * @return array
     */
    abstract public function getGroupMembers($_groupId);
    
    /**
     * replace all current groupmembers with the new groupmembers list
     *
     * @param int $_groupId
     * @param array $_groupMembers
     * @return mixed
     */
    abstract public function setGroupMembers($_groupId, $_groupMembers);

    /**
     * add a new groupmember to the group
     *
     * @param int $_groupId
     * @param int $_accountId
     * @return mixed
     */
    abstract public function addGroupMember($_groupId, $_accountId);

    /**
     * remove one groupmember from the group
     *
     * @param int $_groupId
     * @param int $_accountId
     * @return mixed
     */
    abstract public function removeGroupMember($_groupId, $_accountId);
    
    /**
     * reset class cache
     * 
     * @param string $key
     * @return Sql
     */
    public function resetClassCache($key = null)
    {
        foreach ($this->_classCache as $cacheKey => $cacheValue) {
            if ($key === null || $key === $cacheKey) {
                $this->_classCache[$cacheKey] = array();
            }
        }
        
        return $this;
    }
    
    /**
     * create a new group
     *
     * @param string $_groupName
     * @return ModelGroup
     */
    abstract public function addGroup(ModelGroup $_group);
    
    /**
     * updates an existing group
     *
     * @param ModelGroup $_account
     * @return ModelGroup
     */
    abstract public function updateGroup(ModelGroup $_group);

    /**
     * remove groups
     *
     * @param mixed $_groupId
     * 
     */
    abstract public function deleteGroups($_groupId);
    
    /**
     * get group by id
     *
     * @param int $_groupId
     * @return ModelGroup
     * @throws  NotDefined
     */
    abstract public function getGroupById($_groupId);
    
    /**
     * get group by name
     *
     * @param string $_groupName
     * @return ModelGroup
     * @throws  NotDefined
     */
    abstract public function getGroupByName($_groupName);

    /**
     * get default group
     *
     * @return ModelGroup
     */
    public function getDefaultGroup()
    {
        return $this->_getDefaultGroup('Users');
    }
    
    /**
     * get default admin group
     *
     * @return ModelGroup
     */
    public function getDefaultAdminGroup()
    {
        return $this->_getDefaultGroup('Administrators');
    }

    /**
     * get default replication group
     *
     * @return ModelGroup
     */
    public function getDefaultReplicationGroup()
    {
        return $this->_getDefaultGroup('Replicators');
    }

    /**
     * get default replication group
     *
     * @return ModelGroup
     */
    public function getDefaultAnonymousGroup()
    {
        return $this->_getDefaultGroup('Anonymous');
    }
    
    /**
     * Get multiple groups
     *
     * @param string|array $_ids Ids
     * @return RecordSet
     */
    abstract public function getMultiple($_ids);
    
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
    abstract public function getGroups($_filter = NULL, $_sort = 'name', $_dir = 'ASC', $_start = NULL, $_limit = NULL);
    
    /**
     * get default group for users/admins
     * 
     * @param string $_name group name (Users|Administrators)
     * @return ModelGroup
     * @throws InvalidArgument
     */
    protected function _getDefaultGroup($_name = 'Users')
    {
        if (! in_array($_name, array('Users', 'Administrators', 'Replicators', 'Anonymous'))) {
            throw new InvalidArgument('Wrong group name: ' . $_name);
        }

        if ('Users' === $_name) {
            $configKey = User::DEFAULT_USER_GROUP_NAME_KEY;
        } elseif ('Administrators' === $_name) {
            $configKey = User::DEFAULT_ADMIN_GROUP_NAME_KEY;
        } elseif ('Anonymous' === $_name) {
            $configKey = User::DEFAULT_ANONYMOUS_GROUP_NAME_KEY;
        } else {
            $configKey = User::DEFAULT_REPLICATION_GROUP_NAME_KEY;
        }
        $defaultGroupName = User::getBackendConfiguration($configKey);
        if (empty($defaultGroupName)) {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' ' . $configKey
                . ' not found. Using ' . $_name);
            $defaultGroupName = $_name;
        }

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            $result = $this->getGroupByName($defaultGroupName);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } catch (NotDefined $tenf) {
            // create group on the fly
            $group = new ModelGroup(array(
                'name'    => $defaultGroupName,
            ));
            if (Application::getInstance()->isInstalled('Addressbook')) {
                // in this case it is ok to create the list without members
                Addressbook_Controller_List::getInstance()->createOrUpdateByGroup($group);
            }
            $result = $this->addGroup($group);

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }
        
        return $result;
    }
    
    /**
    * get dummy group record
    *
    * @param integer $_id [optional]
    * @return ModelGroup
    */
    public function getNonExistentGroup($_id = NULL)
    {
        $translate = Translation::getTranslation('Tinebase');
    
        $result = new ModelGroup(array(
                'id'        => $_id,
                'name'      => $translate->_('unknown'),
        ), TRUE);
    
        return $result;
    }

    public function sanitizeGroupListSync()
    {
        throw new Tinebase_Exception_NotImplemented();
    }
}
