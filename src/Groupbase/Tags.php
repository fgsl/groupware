<?php
namespace Fgsl\Groupware\Groupbase;

use Fgsl\Groupware\Admin\Acl\Rights as AdminRights;
use Fgsl\Groupware\Groupbase\Acl\AbstractRights;
use Fgsl\Groupware\Groupbase\Acl\Rights;
use Fgsl\Groupware\Groupbase\Acl\Roles;
use Fgsl\Groupware\Groupbase\Application\Application;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Backend\Sql\Command\Command;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Fgsl\Groupware\Groupbase\Exception\Record\Validation;
use Fgsl\Groupware\Groupbase\Model\Application as ModelApplication;
use Fgsl\Groupware\Groupbase\Model\FullTag;
use Fgsl\Groupware\Groupbase\Model\Pagination;
use Fgsl\Groupware\Groupbase\Model\Tag;
use Fgsl\Groupware\Groupbase\Model\TagFilter;
use Fgsl\Groupware\Groupbase\Model\TagRight;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\User\User;
use Psr\Log\LogLevel;
use Sabre\DAV\Exception\NotFound;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Backend\Sql\Command\CommandInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Class for handling tags and tagging.
 *
 * NOTE: Functions in the 'tagging' chain check acl of the actions,
 *       tag housekeeper functions do their acl in the admin controller
 *       
 * @package     Groupbase
 * @subpackage  Tags
 */
class Tags
{
    /**
     * @var AdapterInterface
     */
    protected $_db;
    
    /**
     * @var CommandInterface
     */
    protected $_dbCommand;
    
    /**
     * don't clone. Use the singleton.
     */
    private function __clone()
    {

    }

    /**
     * holds the instance of the singleton
     *
     * @var Tags
     */
    private static $_instance = NULL;

    /**
     * the singleton pattern
     *
     * @return Tags
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tags;
        }

        return self::$_instance;
    }

    /**
     * the constructor
     *
     */
    private function __construct()
    {
        $this->_db        = Core::getDb();
        $this->_dbCommand = Command::factory($this->_db);
    }

    /**
     * Searches tags according to filter and paging
     * The Current user needs to have the given right, unless $_ignoreAcl is true
     * 
     * @param TagFilter $_filter
     * @param Pagination  $_paging
     * @param boolean $_ignoreAcl
     * @return RecordSet  Set of Tag
     */
    public function searchTags($_filter, $_paging = NULL, $_ignoreAcl = false)
    {
        $select = $_filter->getSelect();

        if (!$_ignoreAcl) {
            TagRight::applyAclSql($select, $_filter->grant);
        }
        
        if (isset($_filter->application)) {
            $app = Application::getInstance()->getApplicationByName($_filter->application);
            $this->_filterSharedOnly($select, $app->getId());
        }
        
        if ($_paging !== NULL) {
            $_paging->appendPaginationSql($select);
        }
        
        AbstractSql::traitGroup($select);
        
        $tags = new RecordSet('Tag', $this->_db->fetchAssoc($select));
        
        return $tags;
    }

    /**
    * Searches tags according to foreign filter
    * -> returns the count of tag occurrences in the result set
    *
    * @param  FilterGroup $_filter
    * @return RecordSet  Set of Tag
    */
    public function searchTagsByForeignFilter($_filter)
    {
        $controller = Core::getApplicationInstance($_filter->getApplicationName(), $_filter->getModelName());
        $recordIds = $controller->search($_filter, NULL, FALSE, TRUE);
        
        if (! empty($recordIds)) {
            $app = Application::getInstance()->getApplicationByName($_filter->getApplicationName());
            
            $select = $this->_getSelect($recordIds, $app->getId());
            TagRight::applyAclSql($select);
            
            AbstractSql::traitGroup($select);
            
            $tags = $this->_db->fetchAll($select);
            $tagData = $this->_getDistinctTagsAndComputeOccurrence($tags);
        } else {
            $tagData = array();
        }
        
        return new RecordSet('Tag', $tagData);
    }
    
    /**
     * get distinct tags from result array and compute occurrence of tag in selection
     * 
     * @param array $_tags
     * @return array
     */
    protected function _getDistinctTagsAndComputeOccurrence(array $_tags)
    {
        $tagData = array();
        
        foreach ($_tags as $tag) {
            if ((isset($tagData[$tag['id']]) || array_key_exists($tag['id'], $tagData))) {
                $tagData[$tag['id']]['selection_occurrence']++;
            } else {
                $tag['selection_occurrence'] = 1;
                $tagData[$tag['id']] = $tag;
            }
        }
        
        return $tagData;
    }
    
    /**
     * Returns tags count of a tag search
     * @todo automate the count query if paging is active!
     *
     * @param TagFilter $_filter
     * @param boolean $_ignoreAcl
     * @return int
     */
    public function getSearchTagsCount($_filter, $_ignoreAcl = false)
    {
        $tags = $this->searchTags($_filter, null, $_ignoreAcl);
        return count($tags);
    }

    /**
     * Return a single record
     *
     * @param string|Tag $_id
     * @param $_getDeleted boolean get deleted records
     * @return FullTag
     *
     * @todo support $_getDeleted
     */
    public function get($_id, $_getDeleted = FALSE)
    {
        $fullTag = $this->getFullTagById($_id);
        return $fullTag;
    }
    
    /**
     * get full tag by id
     * 
     * @param string|Tag $id
     * @param string $ignoreAcl
     * @throws NotFound
     * @return FullTag
     */
    public function getFullTagById($id, $ignoreAcl = false)
    {
        $tagId = ($id instanceof Tag) ? $id->getId() : $id;
        
        $tags = $this->getTagsById($tagId, TagRight::VIEW_RIGHT, $ignoreAcl);
        
        if (count($tags) == 0) {
            throw new NotFound("Tag $id not found or insufficient rights.");
        }
        
        return new FullTag($tags[0]->toArray(), true);
    }
    
    /**
     * Returns (bare) tags identified by its id(s)
     *
     * @param   string|array|RecordSet  $_id
     * @param   string                                  $_right the required right current user must have on the tags
     * @param   bool                                    $_ignoreAcl
     * @return  RecordSet               Set of Tag
     * @throws  InvalidArgument
     *
     * @todo    check context
     */
    public function getTagsById($_id, $_right = TagRight::VIEW_RIGHT, $_ignoreAcl = false)
    {
        $tags = new RecordSet('Tag');
        
        if (is_string($_id)) {
            $ids = array($_id);
        } else if ($_id instanceof RecordSet) {
            $ids = $_id->getArrayOfIds();
        } else if (is_array($_id)) {
            $ids = $_id;
        } else {
            throw new InvalidArgument('Expected string|array|RecordSet of tags');
        }
        
        if (! empty($ids)) {
            $select = $this->_db->select()
                ->from(array('tags' => SQL_TABLE_PREFIX . 'tags'))
                ->where($this->_db->quoteIdentifier('is_deleted') . ' = 0')
                ->where($this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' IN (?)', $ids));
            if ($_ignoreAcl !== true) {
                TagRight::applyAclSql($select, $_right);
            }

            AbstractSql::traitGroup($select);
            
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . $select->__toString());

            foreach ($this->_db->fetchAssoc($select) as $tagArray){
                $tags->addRecord(new Tag($tagArray, true));
            }
            if (count($tags) !== count($ids)) {
                $missingIds = array_diff($ids, $tags->getArrayOfIds());
                Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' Tag(s) not found or insufficient rights: ' . print_r($missingIds, true));
            }
        }
        return $tags;
    }

    /**
     * Returns tags identified by its names
     *
     * @param   string  $_name name of the tag to search for
     * @param   string  $_right the required right current user must have on the tags
     * @param   string  $_application the required right current user must have on the tags
     * @param   bool    $_ignoreAcl
     * @return  Tag
     * @throws  NotFound
     *
     * @todo    check context
     */
    public function getTagByName($_name, $_right = TagRight::VIEW_RIGHT, $_application = NULL, $_ignoreAcl = false)
    {
        $select = $this->_db->select()
            ->from(array('tags' => SQL_TABLE_PREFIX . 'tags'))
            ->where($this->_db->quoteIdentifier('is_deleted') . ' = 0')
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' = (?)', $_name));
        
        if ($_ignoreAcl !== true) {
            TagRight::applyAclSql($select, $_right);
        }

        AbstractSql::traitGroup($select);
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();

        if (!$queryResult) {
            throw new NotFound("Tag with name $_name not found!");
        }

        $result = new Tag($queryResult);

        return $result;
    }

    /**
     * Creates a single tag
     *
     * @param   Tag
     * @param   boolean $_ignoreACL
     * @return  Tag
     * @throws  AccessDenied
     * @throws  UnexpectedValue
     */
    public function createTag(Tag $_tag, $_ignoreACL = FALSE)
    {
        if ($_tag instanceof FullTag) {
            $_tag = new Tag($_tag->toArray(), TRUE);
        }

        if (! is_object(Core::getUser())) {
            throw new NotFound('no valid user object for tag creation');
        }

        $currentAccountId = Core::getUser()->getId();

        $newId = $_tag->generateUID();
        $_tag->setId($newId);
        $_tag->occurrence = 0;
        $_tag->created_by = Core::getUser()->getId();
        $_tag->creation_time = DateTime::now()->get(AbstractRecord::ISO8601LONG);
        if ($_tag->has('rights')) {
            $oldRights = $_tag->rights;
            unset($_tag->rights);
        }
        if ($_tag->has('contexts')) {
            $oldContexts = $_tag->contexts;
            unset($_tag->contexts);
        }

        switch ($_tag->type) {
            case Tag::TYPE_PERSONAL:
                $_tag->owner = $currentAccountId;
                $this->_db->insert(SQL_TABLE_PREFIX . 'tags', $_tag->toArray());
                // for personal tags we set rights and scope temporary here,
                // this needs to be moved into Tinebase Controller later
                $right = new TagRight(array(
                    'tag_id'        => $newId,
                    'account_type'  => Rights::ACCOUNT_TYPE_USER,
                    'account_id'    => $currentAccountId,
                    'view_right'    => true,
                    'use_right'     => true,
                ));
                $this->setRights($right);
                $this->_db->insert(SQL_TABLE_PREFIX . 'tags_context', array(
                    'tag_id'         => $newId,
                    'application_id' => 0
                ));
                break;
            case Tag::TYPE_SHARED:
                if (! $_ignoreACL && ! Core::getUser()->hasRight('Admin', AdminRights::MANAGE_SHARED_TAGS) ) {
                    throw new AccessDenied('Your are not allowed to create this tag');
                }
                $_tag->owner = 0;
                $this->_db->insert(SQL_TABLE_PREFIX . 'tags', $_tag->toArray());
                break;
            default:
                throw new UnexpectedValue('No such tag type.');
                break;
        }
        if ($_tag->has('rights')) {
            $_tag->rights = $oldRights;
        }
        if ($_tag->has('contexts')) {
            $_tag->contexts = $oldContexts;
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Created new tag ' . $_tag->name);

        // any context temporary

        $tags = $this->getTagsById($newId, NULL, true);
        return $tags[0];
    }

    /**
     * Creates new entry
     *
     * @param   RecordInterface $_record
     * @return  RecordInterface
     */
    public function create(RecordInterface $_record)
    {
        return $this->createTag($_record);
    }

    /**
     * updates a single tag
     *
     * @param   Tag
     * @return  Tag
     * @throws  AccessDenied
     */
    public function updateTag(Tag $_tag)
    {
        if ($_tag instanceof FullTag) {
            $_tag = new Tag($_tag->toArray(), TRUE);
        }

        $currentAccountId = Core::getUser()->getId();
        $manageSharedTagsRight = Roles::getInstance()
        ->hasRight('Admin', $currentAccountId, AdminRights::MANAGE_SHARED_TAGS);

        if ( ($_tag->type == Tag::TYPE_PERSONAL && $_tag->owner == $currentAccountId) ||
        ($_tag->type == Tag::TYPE_SHARED && $manageSharedTagsRight) ) {

            $tagId = $_tag->getId();
            if (strlen($tagId) != 40) {
                throw new AccessDenied('Could not update non-existing tag.');
            }

            $this->_db->update(SQL_TABLE_PREFIX . 'tags', array(
                'type'               => $_tag->type,
                'owner'              => $_tag->owner,
                'name'               => $_tag->name,
                'description'        => $_tag->description,
                'color'              => $_tag->color,
                'last_modified_by'   => $currentAccountId,
                'last_modified_time' => DateTime::now()->get(AbstractRecord::ISO8601LONG)
            ), $this->_db->quoteInto($this->_db->quoteIdentifier('id').'= ?', $tagId));

            $tags = $this->getTagsById($tagId);
            return $tags[0];
        } else {
            throw new AccessDenied('Your are not allowed to update this tag.');
        }
    }

    /**
     * Updates existing entry
     *
     * @param RecordInterface $_record
     * @throws Validation|InvalidArgument
     * @return RecordInterface Record|NULL
     */
    public function update(RecordInterface $_record)
    {
        return $this->updateTag($_record);
    }

    /**
     * Deletes (set state "deleted") tags identified by their ids
     *
     * @param  string|array $ids to delete
     * @param  boolean $ignoreAcl
     * @throws  AccessDenied
     */
    public function deleteTags($ids, $ignoreAcl = FALSE)
    {
        $tags = $this->getTagsById($ids, TagRight::VIEW_RIGHT, $ignoreAcl);
        if (count($tags) != count((array)$ids)) {
            throw new AccessDenied('You are not allowed to delete the tag(s).');
        }

        $currentAccountId = (is_object(Core::getUser())) ? Core::getUser()->getId() :
            User::SYSTEM_USER_SETUP;
        
        if (! $ignoreAcl) {
            $manageSharedTagsRight = Roles::getInstance()->hasRight('Admin', $currentAccountId, AdminRights::MANAGE_SHARED_TAGS);
            foreach ($tags as $tag) {
                if ( ($tag->type == Tag::TYPE_PERSONAL && $tag->owner == $currentAccountId) ||
                ($tag->type == Tag::TYPE_SHARED && $manageSharedTagsRight) ) {
                    continue;
                } else {
                    throw new AccessDenied('You are not allowed to delete this tags');
                }
            }
        }
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Deleting ' . count($tags) . ' tags.');
        
        if (count($tags) > 0) {
            $this->_db->update(SQL_TABLE_PREFIX . 'tags', array(
                'is_deleted'   => true,
                'deleted_by'   => $currentAccountId,
                'deleted_time' => DateTime::now()->get(AbstractRecord::ISO8601LONG)
            ), $this->_db->quoteInto($this->_db->quoteIdentifier('id').' IN (?)', $tags->getArrayOfIds()));
        }
    }

    /**
     * Gets tags of a given record where user has the required right to
     * The tags are stored in the records $_tagsProperty.
     *
     * @param RecordInterface $_record        the record object
     * @param string                    $_tagsProperty  the property in the record where the tags are in (defaults: 'tags')
     * @param string                    $_right         the required right current user must have on the tags
     * @return RecordSet tags of record
     */
    public function getTagsOfRecord($_record, $_tagsProperty='tags', $_right=TagRight::VIEW_RIGHT)
    {
        $recordId = $_record->getId();
        $tags = new RecordSet('Tag');
        if (!empty($recordId)) {
            $select = $this->_getSelect($recordId, Application::getInstance()->getApplicationByName($_record->getApplication())->getId());
            TagRight::applyAclSql($select, $_right, $this->_db->quoteIdentifier('tagging.tag_id'));
            
            AbstractSql::traitGroup($select);
            
            foreach ($this->_db->fetchAssoc($select) as $tagArray){
                $tags->addRecord(new Tag($tagArray, true));
            }
        }

        $_record[$_tagsProperty] = $tags;
        return $tags;
    }

    /**
     * Gets tags of a given records where user has the required right to
     * The tags are stored in the records $_tagsProperty.
     *
     * @param RecordSet  $_records       the recordSet
     * @param string                     $_tagsProperty  the property in the record where the tags are in (defaults: 'tags')
     * @param string                     $_right         the required right current user must have on the tags
     * @return RecordSet tags of record
     */
    public function getMultipleTagsOfRecords($_records, $_tagsProperty='tags', $_right=TagRight::VIEW_RIGHT)
    {
        if (count($_records) == 0) {
            // do nothing
            return;
        }

        $recordIds = $_records->getArrayOfIds();
        if (count($recordIds) == 0) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' Can\'t get tags for records without ids');
            // do nothing
            return;
        }

        $appId = $this->_getApplicationForModel($_records->getRecordClassName())->getId();

        $select = $this->_getSelect($recordIds, $appId);
        $select->group(array('tagging.tag_id', 'tagging.record_id'));
        TagRight::applyAclSql($select, $_right, $this->_db->quoteIdentifier('tagging.tag_id'));

        AbstractSql::traitGroup($select);

        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' ' . $select);

        $queryResult = $this->_db->fetchAll($select);

        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
            . ' ' . print_r($queryResult, TRUE));

        // build array with tags (record_id => array of Tag)
        $tagsOfRecords = array();
        foreach ($queryResult as $result) {
            $tagsOfRecords[$result['record_id']][] = new Tag($result, true);
        }

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Getting ' . count($tagsOfRecords) . ' tags for ' . count($_records) . ' records.');

        $result = new RecordSet(Tag::class);
        foreach ($_records as $record) {
            $data = new RecordSet(Tag::class,
                (isset($tagsOfRecords[$record->getId()])) ? $tagsOfRecords[$record->getId()] : array()
            );
            $record->{$_tagsProperty} = $data;
            $result->mergeById($data);
        }

        return $result;
    }

    /**
     * sets (attaches and detaches) tags of a record
     * NOTE: Only touches tags the user has use right for
     * NOTE: Non existing personal tags will be created on the fly
     *
     * @param RecordInterface  $_record        the record object
     * @param string                    $_tagsProperty  the property in the record where the tags are in (defaults: 'tags')
     */
    public function setTagsOfRecord($_record, $_tagsProperty = 'tags')
    {
        $tagsToSet = $this->_createTagsOnTheFly($_record[$_tagsProperty]);
        $currentTags = $this->getTagsOfRecord($_record, 'tags', TagRight::USE_RIGHT);
        
        $appId = $this->_getApplicationForModel(get_class($_record))->getId();
        if (! $this->_userHasPersonalTagRight($appId)) {
            $tagsToSet = $tagsToSet->filter('type', Tag::TYPE_SHARED);
            $currentTags = $currentTags->filter('type', Tag::TYPE_SHARED);
        }

        $tagIdsToSet = $tagsToSet->getArrayOfIds();
        $currentTagIds = $currentTags->getArrayOfIds();

        $toAttach = array_diff($tagIdsToSet, $currentTagIds);
        $toDetach = array_diff($currentTagIds, $tagIdsToSet);

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Attaching tags: ' . print_r($toAttach, true));
        
        $recordId = $_record->getId();
        foreach ($toAttach as $tagId) {
            $this->_db->insert(SQL_TABLE_PREFIX . 'tagging', array(
                'tag_id'         => $tagId,
                'application_id' => $appId,
                'record_id'      => $recordId,
            // backend property not supported by record yet
                'record_backend_id' => ' '
            ));
            $this->_addOccurrence($tagId, 1);
        }
        foreach ($toDetach as $tagId) {
            $this->_db->delete(SQL_TABLE_PREFIX . 'tagging', array(
                $this->_db->quoteInto($this->_db->quoteIdentifier('tag_id'). ' = ?',         $tagId), 
                $this->_db->quoteInto($this->_db->quoteIdentifier('application_id'). ' = ?', $appId), 
                $this->_db->quoteInto($this->_db->quoteIdentifier('record_id'). ' = ?',      $recordId), 
            ));
            $this->_deleteOccurrence($tagId, 1);
        }
    }

    /**
     * @param $modelName
     * @return ModelApplication
     * @throws InvalidArgument
     * @throws NotFound::
     */
    protected function _getApplicationForModel($modelName)
    {
        // FIXME this needs to be resolved - currently tags are saved with Tinebase app id for Filemanager ...
        if (in_array($modelName, array('Filemanager_Model_Node'))) {
            $appName = 'Tinebase';
        } else {
            list($appName, , ) = explode('_', $modelName);
        }

        return Application::getInstance()->getApplicationByName($appName);
    }

    /**
     * attach tag to multiple records identified by a filter
     *
     * @param FilterGroup $_filter
     * @param mixed                             $_tag       string|array|Tag with existing and non-existing tag
     * @return Tag|null
     * @throws AccessDenied
     * @throws \Exception
     * 
     * @todo maybe this could be done in a more generic way (in Tinebase_Controller_Record_Abstract)
     */
    public function attachTagToMultipleRecords($_filter, $_tag)
    {
        // check/create tag on the fly
        $tags = $this->_createTagsOnTheFly(array($_tag));
        if (empty($tags) || count($tags) == 0) {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' No tags created.');
            return null;
        }
        $tag = $tags->getFirstRecord();
        $tagId = $tag->getId();

        $appId = $this->_getApplicationForModel($_filter->getModelName())->getId();
        $controller = Core::getApplicationInstance($_filter->getModelName());

        // only get records user has update rights to
        $controller->checkFilterACL($_filter, 'update');
        $recordIds = $controller->search($_filter, NULL, FALSE, TRUE);

        if (empty($recordIds)) {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' There are no records we could attach the tag to');
            return null;
        }
        
        if ($tag->type === Tag::TYPE_PERSONAL && ! $this->_userHasPersonalTagRight($appId)) {
            throw new AccessDenied('You are not allowed to attach personal tags');
        }
        
        // fetch ids of records already having the tag
        $alreadyAttachedIds = array();
        $select = $this->_db->select()
            ->from(array('tagging' => SQL_TABLE_PREFIX . 'tagging'), 'record_id')
            ->where($this->_db->quoteIdentifier('application_id') . ' = ?', $appId)
            ->where($this->_db->quoteIdentifier('tag_id') . ' = ? ', $tagId);

        AbstractSql::traitGroup($select);
        
        foreach ($this->_db->fetchAssoc($select) as $tagArray) {
            $alreadyAttachedIds[] = $tagArray['record_id'];
        }

        $toAttachIds = array_diff($recordIds, $alreadyAttachedIds);
        
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Attaching 1 Tag to ' . count($toAttachIds) . ' records.');
        
        try {
            $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
            
            foreach ($toAttachIds as $recordId) {
                $this->_db->insert(SQL_TABLE_PREFIX . 'tagging', array(
                    'tag_id'         => $tagId,
                    'application_id' => $appId,
                    'record_id'      => $recordId,
                // backend property not supported by record yet
                    'record_backend_id' => ''
                    )
                );
            }
            
            $controller->concurrencyManagementAndModlogMultiple(
                $toAttachIds, 
                array('tags' => array()), 
                array('tags' => array($tag->toArray()))
            );
            
            $this->_addOccurrence($tagId, count($toAttachIds));
            
            TransactionManager::getInstance()->commitTransaction($transactionId);
        } catch (\Exception $e) {
            TransactionManager::getInstance()->rollBack();
            Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . print_r($e->getMessage(), true));
            throw $e;
        }
        
        return $this->get($tagId);
    }

    /**
     * detach tag from multiple records identified by a filter
     *
     * @param FilterGroup $_filter
     * @param mixed                             $_tag       string|array|Tag with existing and non-existing tag
     * @return void
     * 
     * @todo maybe this could be done in a more generic way (in Tinebase_Controller_Record_Abstract)
     */
    public function detachTagsFromMultipleRecords($_filter, $_tag)
    {
        $app = $this->_getApplicationForModel($_filter->getModelName());
        $appId = $app->getId();
        $controller = Core::getApplicationInstance($app->name, $_filter->getModelName());
        
        // only get records user has update rights to
        $controller->checkFilterACL($_filter, 'update');
        $recordIds = $controller->search($_filter, NULL, FALSE, TRUE);
        
        foreach ((array) $_tag as $dirtyTagId) {
            try {
                $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
                $this->_detachSingleTag($recordIds, $dirtyTagId, $appId, $controller);
                TransactionManager::getInstance()->commitTransaction($transactionId);
            } catch (\Exception $e) {
                TransactionManager::getInstance()->rollBack();
                Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' ' . print_r($e->getMessage(), true));
                throw $e;
            }
        }
    }
    
    /**
     * detach a single tag from records
     * 
     * @param array $recordIds
     * @param string $dirtyTagId
     * @param string $appId
     * @param \Fgsl\Groupware\Groupbase\Controller\AbstractRecord $controller
     */
    protected function _detachSingleTag($recordIds, $dirtyTagId, $appId, $controller)
    {
        $tag = $this->getTagsById($dirtyTagId, TagRight::USE_RIGHT)->getFirstRecord();
        
        if (empty($tag)) {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' No use right for tag, detaching not possible.');
            return;
        }
        $tagId = $tag->getId();
        
        $attachedIds = array();
        $select = $this->_db->select()
            ->from(array('tagging' => SQL_TABLE_PREFIX . 'tagging'), 'record_id')
            ->where($this->_db->quoteIdentifier('application_id') . ' = ?', $appId)
            ->where($this->_db->quoteIdentifier('tag_id') . ' = ? ', $tagId)
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier('record_id').' IN (?)', $recordIds));

        AbstractSql::traitGroup($select);
        
        foreach ($this->_db->fetchAssoc($select) as $tagArray){
            $attachedIds[] = $tagArray['record_id'];
        }
        
        if (empty($attachedIds)) {
            Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' There are no records we could detach the tag(s) from');
            return;
        }
        
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Detaching 1 Tag from ' . count($attachedIds) . ' records.');
        foreach ($attachedIds as $recordId) {
            $this->_db->delete(SQL_TABLE_PREFIX . 'tagging', array(
                $this->_db->quoteIdentifier('tag_id') . ' = ?'         => $tagId,
                $this->_db->quoteIdentifier('record_id') . ' = ?'      => $recordId,
                $this->_db->quoteIdentifier('application_id') . ' = ?' => $appId
            ));
        }
        
        $controller->concurrencyManagementAndModlogMultiple(
            $attachedIds,
            array('tags' => array($tag->toArray())),
            array('tags' => array())
        );
        
        $this->_deleteOccurrence($tagId, count($attachedIds));
    }
    
    /**
     * Creates missing tags on the fly and returns complete list of tags the current
     * user has use rights for.
     * Always respects the current acl of the current user!
     *
     * @param   array|RecordSet set of string|array|Tag with existing and non-existing tags
     * @return  RecordSet       set of all tags
     * @throws  UnexpectedValue
     */
    protected function _createTagsOnTheFly($_mixedTags)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
            . ' Creating tags on the fly: ' . print_r(($_mixedTags instanceof RecordSet ? $_mixedTags->toArray() : $_mixedTags), TRUE));
        
        $tagIds = array();
        foreach ($_mixedTags as $tag) {
            if (is_string($tag)) {
                $tagIds[] = $tag;
                continue;
            } else {
                if (is_array($tag)) {
                    if (! isset($tag['name']) || empty($tag['name'])) {
                        if (Core::isLogLevel(LogLevel::NOTICE)) Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ 
                            . ' Do not create tag without a name.');
                        continue;
                    }
                    $tag = new Tag($tag);
                } elseif (! $tag instanceof Tag) {
                    throw new UnexpectedValue('Tag could not be identified.');
                }
                if (!$tag->getId()) {
                    $tag->type = Tag::TYPE_PERSONAL;
                    $tag = $this->createTag($tag);
                }
                $tagIds[] = $tag->getId();
            }
        }
        return $this->getTagsById($tagIds, TagRight::USE_RIGHT);
    }

    /**
     * adds given number to the persistent occurrence property of a given tag
     *
     * @param  Tag|string $_tag
     * @param  int                             $_toAdd
     * @return void
     */
    protected function _addOccurrence($_tag, $_toAdd)
    {
        $this->_updateOccurrence($_tag, $_toAdd);
    }
    
    /**
     * update tag occurrrence
     * 
     * @param Tag|string $tag
     * @param integer $toAddOrRemove
     */
    protected function _updateOccurrence($tag, $toAddOrRemove)
    {
        if ($toAddOrRemove == 0) {
            return;
        }
        
        $tagId = $tag instanceof Tag ? $tag->getId() : $tag;

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " de/increasing tag occurrence of $tagId by $toAddOrRemove");

        $quotedIdentifier = $this->_db->quoteIdentifier('occurrence');
        
        if ($toAddOrRemove > 0) {
            $toAdd = (int) $toAddOrRemove;
            $data = array(
                'occurrence' => new Expression($quotedIdentifier . ' + ' . $toAdd)
            );
        } else {
            $toRemove = abs((int) $toAddOrRemove);
            $data = array(
                'occurrence' => new Expression('(CASE WHEN (' . $quotedIdentifier . ' - ' . $toRemove . ') > 0 THEN ' . $quotedIdentifier . ' - ' . $toRemove . ' ELSE 0 END)')
            );
        }
        
        $this->_db->update(SQL_TABLE_PREFIX . 'tags', $data, $this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $tagId));
    }

    /**
     * deletes given number from the persistent occurrence property of a given tag
     *
     * @param  Tag|string $_tag
     * @param  int                             $_toDel
     * @return void
     */
    protected function _deleteOccurrence($_tag, $_toDel)
    {
        $this->_updateOccurrence($_tag, - $_toDel);
    }

    /**
     * get all rights of a given tag
     *
     * @param  string                    $_tagId
     * @return RecordSet Set of TagRight
     */
    public function getRights($_tagId)
    {
        $select = $this->_db->select()
            ->from(array('tags_acl' => SQL_TABLE_PREFIX . 'tags_acl'), 
                   array('tag_id', 'account_type', 'account_id', 'account_right' => $this->_dbCommand->getAggregate('account_right'))
            )
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier('tag_id') . ' = ?', $_tagId))
            ->group(array('tag_id', 'account_type', 'account_id'));
        
        AbstractSql::traitGroup($select);
        
        $stmt = $this->_db->query($select);
        $rows = $stmt->fetchAll(Adapter::FETCH_ASSOC);

        $rights = new RecordSet('TagRight', $rows, true);

        return $rights;
    }

    /**
     * purges (removes from tabel) all rights of a given tag
     *
     * @param  string $_tagId
     * @return void
     */
    public function purgeRights($_tagId)
    {
        $this->_db->delete(SQL_TABLE_PREFIX . 'tags_acl', array(
            $this->_db->quoteIdentifier('tag_id') . ' = ?' => $_tagId
        ));
    }

    /**
     * Sets all given tag rights
     *
     * @param RecordSet|TagRight
     * @return void
     * @throws Validation
     */
    public function setRights($_rights)
    {
        $rights = $_rights instanceof TagRight ? array($_rights) : $_rights;

        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Setting ' . count($rights) . ' tag right(s).');

        foreach ($rights as $right) {
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($right->toArray(), TRUE));
            
            if (! ($right instanceof TagRight && $right->isValid())) {
                throw new Validation('The given right is not valid!');
            }
            $this->_db->delete(SQL_TABLE_PREFIX . 'tags_acl', array(
                $this->_db->quoteInto($this->_db->quoteIdentifier('tag_id') . ' = ?', $right->tag_id),
                $this->_db->quoteInto($this->_db->quoteIdentifier('account_type') . ' = ?', $right->account_type),
                $this->_db->quoteInto($this->_db->quoteIdentifier('account_id') . ' = ?', (string) $right->account_id)
            ));
            foreach (array('view', 'use' ) as $availableRight) {
                $rightField = $availableRight . '_right';
                if ($right->$rightField === true) {
                    $this->_db->insert(SQL_TABLE_PREFIX . 'tags_acl', array(
                        'tag_id'        => $right->tag_id,
                        'account_type'  => $right->account_type,
                        'account_id'    => $right->account_id,
                        'account_right' => $availableRight
                    ));
                }
            }
        }
    }

    /**
     * returns all contexts of a given tag
     *
     * @param  string $_tagId
     * @return array  array of application ids
     */
    public function getContexts($_tagId)
    {
        $select = $this->_db->select()
            ->from(array('tags_context' => SQL_TABLE_PREFIX . 'tags_context'), array('application_id' => $this->_dbCommand->getAggregate('application_id')))
            ->where($this->_db->quoteInto($this->_db->quoteIdentifier('tag_id') . ' = ?', $_tagId))
            ->group('tag_id');
        
        AbstractSql::traitGroup($select);
        
        $apps = $this->_db->fetchOne($select);

        if ($apps === '0'){
            $apps = 'any';
        }

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' got tag contexts: ' .$apps);
        return explode(',', $apps);
    }

    /**
     * purges (removes from tabel) all contexts of a given tag
     *
     * @param  string $_tagId
     * @return void
     */
    public function purgeContexts($_tagId)
    {
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' removing contexts for tag ' . $_tagId);

        $this->_db->delete(SQL_TABLE_PREFIX . 'tags_context', array(
            $this->_db->quoteIdentifier('tag_id') . ' = ?' => $_tagId
        ));
    }

    /**
     * sets all given contexts for a given tag
     *
     * @param   array  $_contexts array of application ids (0 or 'any' for all apps)
     * @param   string $_tagId
     * @throws  InvalidArgument
     */
    public function setContexts(array $_contexts, $_tagId)
    {
        if (!$_tagId) {
            throw new InvalidArgument('A $_tagId is mandentory.');
        }

        if (in_array('any', $_contexts, true) || in_array(0, $_contexts, true)) {
            $_contexts = array(0);
        }

        if (Core::isLogLevel(LogLevel::DEBUG)) 
            Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Setting tag contexts: ' . print_r($_contexts, true));

        foreach ($_contexts as $context) {
            $this->_db->insert(SQL_TABLE_PREFIX . 'tags_context', array(
                'tag_id'         => $_tagId instanceof Tag ? $_tagId->getId() : $_tagId,
                'application_id' => $context
            ));
        }
    }

    /**
     * get db adapter
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->_db;
    }

    /**
     * get backend type
     *
     * @return string
     */
    public function getType()
    {
        return 'Sql';
    }

    /**
     * get select for tags query
     *
     * @param string|array $_recordId
     * @param string $_applicationId
     * @param mixed $_cols
     * @return Select
     */
    protected function _getSelect($_recordId, $_applicationId, $_cols = '*')
    {
        $recordIds = (array) $_recordId;
        // stringify record ids (we might have a mix of uuids and old integer ids)
        foreach ($recordIds as $key => $value) {
            $recordIds[$key] = (string) $value;
        }

        $select = $this->_db->select()
            ->from(array('tagging' => SQL_TABLE_PREFIX . 'tagging'), $_cols)
            ->join(array('tags'    => SQL_TABLE_PREFIX . 'tags'), $this->_db->quoteIdentifier('tagging.tag_id') . ' = ' . $this->_db->quoteIdentifier('tags.id'))
            ->where($this->_db->quoteIdentifier('application_id') . ' = ?', $_applicationId)
            ->where($this->_db->quoteIdentifier('record_id') . ' IN (?) ', $recordIds)
            ->where($this->_db->quoteIdentifier('is_deleted') . ' = 0');
        
        $this->_filterSharedOnly($select, $_applicationId);
        
        return $select;
    }
    
    /**
     * apply filter for type shared only
     * 
     * @param Select $select
     * @param string $applicationId
     */
    protected function _filterSharedOnly($select, $applicationId)
    {
        if (! $this->_userHasPersonalTagRight($applicationId)) {
            $select->where($this->_db->quoteIdentifier('type') . ' = ?', Tag::TYPE_SHARED);
        }
    }
    
    /**
     * checks if user is allowed to use personal tags in application
     * 
     * @param string $applicationId
     */
    protected function _userHasPersonalTagRight($applicationId)
    {
        return ! is_object(Core::getUser()) || Core::getUser()->hasRight($applicationId, AbstractRights::USE_PERSONAL_TAGS);
    }

    /**
     * merge duplicate shared tags
     * 
     * @param string $model record model for which tags should be merged
     * @param boolean $deleteObsoleteTags
     * @param boolean $ignoreAcl
     * 
     * @see 0007354: function for merging duplicate tags
     */
    public function mergeDuplicateSharedTags($model, $deleteObsoleteTags = TRUE, $ignoreAcl = FALSE)
    {
        $select = $this->_db->select()
            ->from(array('tags'    => SQL_TABLE_PREFIX . 'tags'), 'name')
            ->where($this->_db->quoteIdentifier('type') . ' = ?', Tag::TYPE_SHARED)
            ->where($this->_db->quoteIdentifier('is_deleted') . ' = 0')
            ->group('name')
            ->having('COUNT(' . $this->_db->quoteIdentifier('name') . ') > 1');
        $queryResult = $this->_db->fetchAll($select);
        
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
            ' Found ' . count($queryResult) . ' duplicate tag names.');
        
        $controller = Core::getApplicationInstance($model);
        if ($ignoreAcl) {
            $containerChecks = $controller->doContainerACLChecks(FALSE);
        }
        $recordFilterModel = $model . 'Filter';
        
        foreach ($queryResult as $duplicateTag) {
            $filter = new TagFilter(array(
                'name' => $duplicateTag['name'],
                'type' => Tag::TYPE_SHARED,
            ));
            $paging = new Pagination(array('sort' => 'creation_time'));
            $tagsWithSameName = $this->searchTags($filter, $paging);
            $targetTag = $tagsWithSameName->getFirstRecord();
            
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                ' Merging tag ' . $duplicateTag['name'] . '. Found ' . count($tagsWithSameName) . ' tags with this name.');
            
            foreach ($tagsWithSameName as $tag) {
                if ($tag->getId() === $targetTag->getId()) {
                    // skip target (oldest) tag
                    continue;
                }

                $recordFilter = new $recordFilterModel(array(
                    array('field' => 'tag', 'operator' => 'in', 'value' => array($tag->getId()))
                ));
                
                $recordIdsWithTagToMerge = $controller->search($recordFilter, NULL, FALSE, TRUE);
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ .
                    ' Found ' . count($recordIdsWithTagToMerge) . ' ' . $model . '(s) with tags to be merged.');
                
                if (!empty($recordIdsWithTagToMerge)) {
                    $recordFilter = new $recordFilterModel(array(
                        array('field' => 'id', 'operator' => 'in', 'value' => $recordIdsWithTagToMerge)
                    ));
                    
                    $this->attachTagToMultipleRecords($recordFilter, $targetTag);
                    $this->detachTagsFromMultipleRecords($recordFilter, $tag->getId());
                }
                
                // check occurrence of the merged tag and remove it if obsolete
                $tag = $this->get($tag);
                if ($deleteObsoleteTags && $tag->occurrence == 0) {
                    $this->deleteTags($tag->getId(), $ignoreAcl);
                }
            }
        }
        
        if ($ignoreAcl) {
            /** @noinspection PhpUndefinedVariableInspection */
            $controller->doContainerACLChecks($containerChecks);
        }
    }
}
