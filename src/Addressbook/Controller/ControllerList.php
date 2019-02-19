<?php
namespace Fgsl\Groupware\Addressbook\Controller;
use Fgsl\Groupware\Groupbase\Controller\AbstractRecord;
use Fgsl\Groupware\Groupbase\Backend\Sql;
use Fgsl\Groupware\Groupbase\Config\Config;
use Fgsl\Groupware\Addressbook\Controller\Contact as ControllerContact;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Addressbook\Backend\BackendList;
use Fgsl\Groupware\Addressbook\Model\ModelList;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;
use Fgsl\Groupware\Groupbase\Record\Diff;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Addressbook\Model\ContactFilter;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Pagination;
use Fgsl\Groupware\Groupbase\Record\Expander\Expander;
use Fgsl\Groupware\Groupbase\TransactionManager;
use Fgsl\Groupware\Admin\Controller\Group as ControllerGroup;
use Fgsl\Groupware\Admin\Acl\Rights;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Event\Event;
use Fgsl\Groupware\Groupbase\Event\ChangeList;
use Fgsl\Groupware\Addressbook\Event\DeleteList;
use Fgsl\Groupware\Groupbase\Model\Group as ModelGroup;
use Fgsl\Groupware\Groupbase\Exception\NotFound;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Addressbook\Model\ListMemberRole;
use Fgsl\Groupware\Model\Note;

/**
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * contact controller for Addressbook
 *
 * @package     Addressbook
 * @subpackage  Controller
 */
class ControllerList extends AbstractRecord
{
    /**
     * @var null|Sql
     */
    protected $_memberRolesBackend = null;

    /**
     * the constructor
     *
     * don't use the constructor. use the singleton
     */
    private function __construct()
    {
        $this->_resolveCustomFields = true;
        $this->_backend = new BackendList();
        if (true === Config::getInstance()->featureEnabled(Config::FEATURE_SEARCH_PATH)) {
            $this->_useRecordPaths = true;
        }
        $this->_modelName = ModelList::class;
        $this->_applicationName = 'Addressbook';
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
     * @var ControllerList
     */
    private static $_instance = NULL;

    public function getMemberRolesBackend()
    {
        if ($this->_memberRolesBackend === null) {
            $this->_memberRolesBackend = new Sql(array(
                'tableName' => 'adb_list_m_role',
                'modelName' => 'ModelListMemberRole',
            ));
        }

        return $this->_memberRolesBackend;
    }

    /**
     * the singleton pattern
     *
     * @return ControllerList
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new ControllerList();
        }

        return self::$_instance;
    }

    public static function destroyInstance()
    {
        self::$_instance = null;
    }

    /**
     * get by id
     *
     * @param string $_id
     * @param int $_containerId
     * @param bool         $_getRelatedData
     * @param bool $_getDeleted
     * @return ModelList
     * @throws AccessDenied
     */
    public function get($_id, $_containerId = NULL, $_getRelatedData = TRUE, $_getDeleted = FALSE)
    {
        $result = new RecordSet('ModelList',
            array(parent::get($_id, $_containerId, $_getRelatedData, $_getDeleted)));
        $this->_removeHiddenListMembers($result);
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $result->getFirstRecord();
    }

    /**
     * use contact search to remove hidden list members
     *
     * @param RecordSet $lists
     */
    protected function _removeHiddenListMembers($lists)
    {
        if (count($lists) === 0) {
            return;
        }

        $allMemberIds = array();
        foreach ($lists as $list) {
            if (is_array($list->members)) {
                $allMemberIds = array_merge($list->members, $allMemberIds);
            }
        }
        $allMemberIds = array_unique($allMemberIds);

        if (empty($allMemberIds)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' No members found.');
            return;
        }

        $allVisibleMemberIds = ControllerContact::getInstance()->search(new ContactFilter(array(array(
            'field' => 'id',
            'operator' => 'in',
            'value' => $allMemberIds
        ))), NULL, FALSE, TRUE);

        $hiddenMemberids = array_diff($allMemberIds, $allVisibleMemberIds);

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Found ' . count($hiddenMemberids) . ' hidden members, removing them');
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . print_r($hiddenMemberids, TRUE));

        foreach ($lists as $list) {
            // use array_values to make sure we have numeric index starting with 0 again
            $list->members = array_values(array_diff($list->members, $hiddenMemberids));
        }
    }

    /**
     * @see AbstractRecord::search()
     *
     * @param FilterGroup $_filter
     * @param Pagination $_pagination
     * @param bool $_getRelations
     * @param bool $_onlyIds
     * @param string $_action
     * @return array|RecordSet
     */
    public function search(FilterGroup $_filter = NULL, Pagination $_pagination = NULL, $_getRelations = FALSE, $_onlyIds = FALSE, $_action = 'get')
    {
        $result = parent::search($_filter, $_pagination, $_getRelations, $_onlyIds, $_action);

        if ($_onlyIds !== true) {
            $this->_removeHiddenListMembers($result);
        }

        return $result;
    }

    /**
     * @see \Fgsl\Groupware\Groupbase\Record\AbstractRecord::getMultiple()
     *
     * @param array $_ids
     * @param bool $_ignoreACL
     * @return RecordSet
     */
    public function getMultiple($_ids, $_ignoreACL = FALSE, Expander $_expander = null)
    {
        $result = parent::getMultiple($_ids, $_ignoreACL, $_expander);
        if (true !== $_ignoreACL) {
            $this->_removeHiddenListMembers($result);
        }
        return $result;
    }

    /**
     * add new members to list
     *
     * @param  mixed $_listId
     * @param  mixed $_newMembers
     * @param  boolean $_addToGroup
     * @return ModelList
     */
    public function addListMember($_listId, $_newMembers, $_addToGroup = true)
    {
        try {
            $list = $this->get($_listId);
        } catch (AccessDenied $tead) {
            $this->_fixEmptyContainerId($_listId);
            $list = $this->get($_listId);
        }

        $this->_checkGrant($list, 'update', TRUE, 'No permission to add list member.');
        $this->_checkGroupGrant($list, TRUE, 'No permission to add list member.');

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            $list = $this->_backend->addListMember($_listId, $_newMembers);

            if (true === $_addToGroup && ! empty($list->group_id)) {
                foreach (RecordSet::getIdsFromMixed($_newMembers) as $userId) {
                    ControllerGroup::getInstance()->addGroupMember($list->group_id, $userId, false);
                }
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        return $this->get($list->getId());
    }

    protected function _checkGroupGrant($_list, $_throw = false, $_msg = '')
    {
        if (! empty($_list->group_id)) {
            if (!Core::getUser()->hasRight('Admin', Rights::MANAGE_ACCOUNTS)) {
                if ($_throw) {
                    throw new AccessDenied($_msg);
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * fixes empty container ids / perhaps this can be removed later as all lists should have a container id!
     *
     * @param  mixed $_listId
     * @return ModelList
     */
    protected function _fixEmptyContainerId($_listId)
    {
        /** @var ModelList $list */
        $list = $this->_backend->get($_listId);

        if (empty($list->container_id)) {
            $list->container_id = Controller::getDefaultInternalAddressbook();
            $list = $this->_backend->update($list);
        }

        return $list;
    }

    /**
     * remove members from list
     *
     * @param  mixed $_listId
     * @param  mixed $_removeMembers
     * @param  boolean $_removeFromGroup
     * @return ModelList
     */
    public function removeListMember($_listId, $_removeMembers, $_removeFromGroup = true)
    {
        $list = $this->get($_listId);

        $this->_checkGrant($list, 'update', TRUE, 'No permission to remove list member.');
        $this->_checkGroupGrant($list, TRUE, 'No permission to remove list member.');

        $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
        try {
            $list = $this->_backend->removeListMember($_listId, $_removeMembers);

            if (true === $_removeFromGroup && ! empty($list->group_id)) {
                foreach (RecordSet::getIdsFromMixed($_removeMembers) as $userId) {
                    ControllerGroup::getInstance()->removeGroupMember($list->group_id, $userId, false);
                }
            }

            TransactionManager::getInstance()->commitTransaction($transactionId);
            $transactionId = null;
        } finally {
            if (null !== $transactionId) {
                TransactionManager::getInstance()->rollBack();
            }
        }

        return $this->get($list->getId());
    }

    /**
     * inspect creation of one record
     *
     * @param  RecordInterface $_record
     * @throws AccessDenied
     */
    protected function _inspectBeforeCreate(RecordInterface $_record)
    {
        if (isset($_record->type) && $_record->type == ModelList::LISTTYPE_GROUP) {
            if (empty($_record->group_id)) {
                throw new UnexpectedValue('group_id is empty, must not happen for list type group');
            }

            // check rights
            $this->_checkGroupGrant($_record, TRUE, 'can not add list of type ' . ModelList::LISTTYPE_GROUP);

            // check if group is there, if not => not found exception
            ControllerGroup::getInstance()->get($_record->group_id);
        }
    }

    /**
     * inspect creation of one record (after create)
     *
     * @param   RecordInterface $_createdRecord
     * @param   RecordInterface $_record
     * @return  void
     */
    protected function _inspectAfterCreate($_createdRecord, RecordInterface $_record)
    {
        /** @var ModelList $_createdRecord */
        $this->_fireChangeListeEvent($_createdRecord);
    }

    /**
     * inspect update of one record
     *
     * @param   RecordInterface $_record the update record
     * @param   RecordInterface $_oldRecord the current persistent record
     * @return  void
     */
    protected function _inspectBeforeUpdate($_record, $_oldRecord)
    {
        if (! empty($_record->group_id)) {

            // first check if something changed that requires special rights
            $changeGroup = false;
            foreach (ModelList::getManageAccountFields() as $field) {
                if ($_record->{$field} != $_oldRecord->{$field}) {
                    $changeGroup = true;
                    break;
                }
            }

            // then do the update, the group controller will check manage accounts right
            if ($changeGroup) {
                $groupController = ControllerGroup::getInstance();
                $group = $groupController->get($_record->group_id);

                foreach (ModelList::getManageAccountFields() as $field) {
                    $group->{$field} = $_record->{$field};
                }

                $groupController->update($group, false);
            }
        }
    }

    /**
     * inspect update of one record (after update)
     *
     * @param   ModelList $updatedRecord   the just updated record
     * @param   ModelList $record          the update record
     * @param   ModelList $currentRecord   the current record (before update)
     * @return  void
     */
    protected function _inspectAfterUpdate($updatedRecord, $record, $currentRecord)
    {
        $this->_fireChangeListeEvent($updatedRecord);
    }

    /**
     * fireChangeListeEvent
     *
     * @param ModelList $list
     */
    protected function _fireChangeListeEvent(ModelList $list)
    {
        $event = new ChangeList();
        $event->list = $list;
        Event::fireEvent($event);
    }

    /**
     * inspects delete action
     *
     * @param array $_ids
     * @return array of ids to actually delete
     */
    protected function _inspectDelete(array $_ids)
    {
        $lists = $this->getMultiple($_ids);
        foreach ($lists as $list) {
            $event = new DeleteList();
            $event->list = $list;
            Event::fireEvent($event);
        }

        return $_ids;
    }

    /**
     * create or update list in addressbook sql backend
     *
     * @param  ModelGroup $group
     * @return ModelList
     */
    public function createOrUpdateByGroup(ModelGroup $group)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($group->toArray(), TRUE));

        try {
            if (empty($group->list_id)) {
                $list = $this->_backend->getByGroupName($group->name, $group->container_id);
                if (!$list) {
                    // jump to catch block => no list_id provided and no existing list for group found
                    throw new NotFound('list_id is empty');
                }
                $group->list_id = $list->getId();
            } else {
                try {
                    $list = $this->_backend->get($group->list_id);
                } catch (NotFound $tenf) {
                    $list = $this->_backend->getByGroupName($group->name, $group->container_id);
                    if (!$list) {
                        // jump to catch block => bad list_id provided and no existing list for group found
                        throw new NotFound('list_id is empty');
                    }
                }
            }

            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Update list ' . $group->name);

            $list->name = $group->name;
            $list->description = $group->description;
            $list->email = $group->email;
            $list->type = ModelList::LISTTYPE_GROUP;
            $list->container_id = (empty($group->container_id)) ?
                Controller::getDefaultInternalAddressbook() : $group->container_id;
            $list->members = (isset($group->members)) ? $this->_getContactIds($group->members) : array();

            // add modlog info
            ModificationLog::setRecordMetaData($list, 'update', $list);

            $list = $this->_backend->update($list);
            $list = $this->get($list->getId());

        } catch (NotFound $tenf) {
            $list = $this->createByGroup($group);
            $group->list_id = $list->getId();
        }

        return $list;
    }

    /**
     * create new list by group
     *
     * @param ModelGroup $group
     * @return ModelList
     */
    protected function createByGroup($group)
    {
        $list = new ModelList(array(
            'name' => $group->name,
            'description' => $group->description,
            'email' => $group->email,
            'type' => ModelList::LISTTYPE_GROUP,
            'container_id' => (empty($group->container_id)) ? Controller::getDefaultInternalAddressbook()
                : $group->container_id,
            'members' => (isset($group->members)) ? $this->_getContactIds($group->members) : array(),
        ));

        // add modlog info
        ModificationLog::setRecordMetaData($list, 'create');

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Add new list ' . $group->name);
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' ' . print_r($list->toArray(), TRUE));

        /** @var ModelList $list */
        $list = $this->_backend->create($list);

        $group->list_id = $list->getId();

        return $list;
    }

    /**
     * get contact_ids of users
     *
     * @param  array $_userIds
     * @return array
     */
    protected function _getContactIds($_userIds)
    {
        $contactIds = array();

        if (empty($_userIds)) {
            return $contactIds;
        }

        foreach ($_userIds as $userId) {
            try {
                $user = User::getInstance()->getUserByPropertyFromBackend('accountId', $userId);
                if (!empty($user->contact_id)) {
                    $contactIds[] = $user->contact_id;
                }
            } catch (NotFound $tenf) {}
        }

        return $contactIds;
    }

    /**
     * you can define default filters here
     *
     * @param FilterGroup $_filter
     */
    protected function _addDefaultFilter(FilterGroup $_filter = NULL)
    {
        if (!$_filter->isFilterSet('showHidden')) {
            $hiddenFilter = $_filter->createFilter('showHidden', 'equals', FALSE);
            /** @noinspection PhpDeprecationInspection */
            $hiddenFilter->setIsImplicit(TRUE);
            $_filter->addFilter($hiddenFilter);
        }
    }

    /**
     * set relations / tags / alarms
     *
     * @param   RecordInterface $updatedRecord the just updated record
     * @param   RecordInterface $record the update record
     * @param   RecordInterface $currentRecord   the original record if one exists
     * @param   boolean                   $returnUpdatedRelatedData
     * @param   boolean $isCreate
     * @return  RecordInterface
     */
    protected function _setRelatedData(RecordInterface $updatedRecord, RecordInterface $record, RecordInterface $currentRecord = null, $returnUpdatedRelatedData = false, $isCreate = false)
    {
        /** @var ModelList $record */
        if (isset($record->memberroles)) {
            // get migration
            // TODO add generic helper fn for this?
            $memberrolesToSet = (!$record->memberroles instanceof RecordSet)
                ? new RecordSet(
                    'ModelListMemberRole',
                    $record->memberroles,
                    /* $_bypassFilters */ true
                ) : $record->memberroles;

            foreach ($memberrolesToSet as $memberrole) {
                foreach (array('contact_id', 'list_role_id', 'list_id') as $field) {
                    if (isset($memberrole[$field]['id'])) {
                        $memberrole[$field] = $memberrole[$field]['id'];
                    }
                }
            }

            $currentMemberroles = $this->_getMemberRoles($record);
            $diff = $currentMemberroles->diff($memberrolesToSet);
            if (count($diff['added']) > 0) {
                $diff['added']->list_id = $updatedRecord->getId();
                foreach ($diff['added'] as $memberrole) {
                    $this->getMemberRolesBackend()->create($memberrole);
                }
            }
            if (count($diff['removed']) > 0) {
                $this->getMemberRolesBackend()->delete($diff['removed']->getArrayOfIds());
            }
        }

        $result = parent::_setRelatedData($updatedRecord, $record, $currentRecord, $returnUpdatedRelatedData, $isCreate);

        return $result;
    }

    /**
     * add related data to record
     *
     * @param ModelList $record
     */
    protected function _getRelatedData($record)
    {
        $memberRoles = $this->_getMemberRoles($record);
        if (count($memberRoles) > 0) {
            $record->memberroles = $memberRoles;
        }
        parent::_getRelatedData($record);
    }

    /**
     * @param ModelList $record
     * @return RecordSet|ListMemberRole
     */
    protected function _getMemberRoles($record)
    {
        $result = $this->getMemberRolesBackend()->getMultipleByProperty($record->getId(), 'list_id');
        return $result;
    }

    /**
     * get all lists given contact is member of
     *
     * @param $contact
     * @return array
     */
    public function getMemberships($contact)
    {
        return $this->_backend->getMemberships($contact);
    }

    /**
     * set system notes
     *
     * @param   RecordInterface $_updatedRecord   the just updated record
     * @param   string $_systemNoteType
     * @param   RecordSet $_currentMods
     */
    protected function _setSystemNotes($_updatedRecord, $_systemNoteType = Note::SYSTEM_NOTE_NAME_CREATED, $_currentMods = NULL)
    {
        $resolvedMods = $_currentMods;
        if (null !== $_currentMods && Note::SYSTEM_NOTE_NAME_CHANGED === $_systemNoteType) {
            $resolvedMods = new RecordSet(ModificationLog::class, array());
            /** @var ModificationLog $mod */
            foreach ($_currentMods as $mod) {
                $diff = new Diff(json_decode($mod->new_value, true));
                foreach ($diff->xprops('diff') as $attribute => &$value) {
                    if ('members' === $attribute) {
                        $this->_resolveMembersForNotes($value, $diff->xprops('oldData')['members']);
                    }
                }
                $newMod = clone $mod;
                $newMod->new_value = json_encode($diff->toArray());
                $resolvedMods->addRecord($newMod);
            }
        }
        parent::_setSystemNotes($_updatedRecord, $_systemNoteType, $resolvedMods);
    }

    protected function _resolveMembersForNotes(&$currentMembers, &$oldMembers)
    {
        $contactIds = array();
        if (!empty($currentMembers)) {
            $contactIds = array_merge($contactIds, $currentMembers);
        }
        if (!empty($oldMembers)) {
            $contactIds = array_merge($contactIds, $oldMembers);
        }
        $contactIds = array_unique($contactIds);
        $contacts = ControllerContact::getInstance()->getMultiple($contactIds);

        if (is_array($currentMembers)) {
            foreach ($currentMembers as &$val) {
                /** @var Addressbook_Model_Contact $contact */
                if (false !== ($contact = $contacts->getById($val))) {
                    $val = $contact->getTitle();
                }
            }
        }

        if (is_array($oldMembers)) {
            foreach ($oldMembers as &$val) {
                /** @var Addressbook_Model_Contact $contact */
                if (false !== ($contact = $contacts->getById($val))) {
                    $val = $contact->getTitle();
                }
            }
        }
    }
}
