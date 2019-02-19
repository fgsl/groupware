<?php
namespace Groupware\Groupbase\Tree;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Controller\Record\ModlogTrait;
use Fgsl\Groupware\Groupbase\Exception\Backend\Database;
use Fgsl\Groupware\Groupbase\Tree\FileObject;
use Fgsl\Groupware\Groupbase\Tree\Tree;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Config\Config;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * sql backend class for tree nodes
 *
 * @package     Tinebase
 *
 * TODO refactor to Tree_Backend_Node
 */
class Node extends AbstractSql
{
    use ModlogTrait;

    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'tree_nodes';
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'Node';

    /**
     * if modlog is active, we add 'is_deleted = 0' to select object in _getSelect()
     * we don't use modlog here because the name is unique. If the only do a soft delete, it is not possible to create
     * the same node again!
     *
     * @var boolean
     */
    protected $_modlogActive = false;

    protected $_notificationActive = false;

    protected $_revision = null;

    /**
     * NOTE: returns fake tree controller
     *       needed by Tinebase_Core::getApplicationInstance('Node')
     *
     * @return Tree
     */
    public static function getInstance()
    {
        return Tree::getInstance();
    }

    /**
     * the constructor
     *
     * allowed options:
     *  - modelName
     *  - tableName
     *  - tablePrefix
     *  - modlogActive
     *
     * @param AdapterInterface $_dbAdapter (optional)
     * @param array $_options (optional)
     * @throws Database
     */
    public function __construct($_dbAdapter = NULL, $_options = array())
    {
        if (isset($_options[Config::FILESYSTEM_ENABLE_NOTIFICATIONS]) && true === $_options[Config::FILESYSTEM_ENABLE_NOTIFICATIONS]) {
            $this->_notificationActive = $_options[Config::FILESYSTEM_ENABLE_NOTIFICATIONS];
        }

        if (isset($_options[Config::FILESYSTEM_MODLOGACTIVE]) && true === $_options[Config::FILESYSTEM_MODLOGACTIVE]) {
            $this->_modlogActive = $_options[Config::FILESYSTEM_MODLOGACTIVE];
        } else {
            $this->_omitModLog = true;
        }


        parent::__construct($_dbAdapter, $_options);
    }

    /**
     * if set to an integer value, only revisions of that number will be selected
     * if set to null value, regular revision will be selected
     *
     * @param int|null $_revision
     */
    public function setRevision($_revision)
    {
        $this->_revision = null !== $_revision ? (int)$_revision : null;
    }

    /**
     * get the basic select object to fetch records from the database
     *  
     * @param array|string|Zend_Db_Expr $_cols columns to get, * per default
     * @param boolean $_getDeleted get deleted records (if modlog is active)
     * @return Zend_Db_Select
     */
    protected function _getSelect($_cols = '*', $_getDeleted = FALSE)
    {
        $select = parent::_getSelect($_cols, $_getDeleted);
        
        $select
            ->joinLeft(
                /* table  */ array('tree_fileobjects' => $this->_tablePrefix . 'tree_fileobjects'), 
                /* on     */ $this->_db->quoteIdentifier($this->_tableName . '.object_id') . ' = ' . $this->_db->quoteIdentifier('tree_fileobjects.id'),
                /* select */ array('type', 'created_by', 'creation_time', 'last_modified_by', 'last_modified_time', 'revision', 'contenttype', 'revision_size', 'indexed_hash', 'description')
            )
            ->joinLeft(
                /* table  */ array('tree_filerevisions' => $this->_tablePrefix . 'tree_filerevisions'), 
                /* on     */ $this->_db->quoteIdentifier('tree_fileobjects.id') . ' = ' . $this->_db->quoteIdentifier('tree_filerevisions.id') . ' AND ' .
                $this->_db->quoteIdentifier('tree_filerevisions.revision') . ' = ' . (null !== $this->_revision ? (int)$this->_revision : $this->_db->quoteIdentifier('tree_fileobjects.revision')),
                /* select */ array('hash', 'size', 'preview_count', 'lastavscan_time', 'is_quarantined')
            )->joinLeft(
            /* table  */ array('tree_filerevisions2' => $this->_tablePrefix . 'tree_filerevisions'),
                /* on     */ $this->_db->quoteIdentifier('tree_fileobjects.id') . ' = ' . $this->_db->quoteIdentifier('tree_filerevisions2.id'),
                /* select */ array('available_revisions' => Tinebase_Backend_Sql_Command::factory($select->getAdapter())->getAggregate('tree_filerevisions2.revision'))
            )->group($this->_tableName . '.object_id'
        );

        // NOTE: we need to do it here if $this->_modlogActive is false
        if (false === $this->_modlogActive && !$_getDeleted) {
            // don't fetch deleted objects
            $select->where($this->_db->quoteIdentifier($this->_tableName . '.is_deleted') . ' = 0');
        }

        if ($this->_db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
            $select->columns(new Zend_Db_Expr('CAST(MIN(' . $this->_db->quoteIdentifier('tree_fileobjects.indexed_hash') . ') = MIN(' . $this->_db->quoteIdentifier('tree_filerevisions.hash') . ') AS int) AS ' . $this->_db->quoteIdentifier('isIndexed')));
        } else {
            $select->columns(new Zend_Db_Expr('IF (' . $this->_db->quoteIdentifier('tree_fileobjects.indexed_hash') . ' = ' . $this->_db->quoteIdentifier('tree_filerevisions.hash') . ', TRUE, FALSE) AS ' . $this->_db->quoteIdentifier('isIndexed')));
        }
            
        return $select;
    }

    /**
     * do something after creation of record
     *
     * @param Tinebase_Record_Interface $_newRecord
     * @param Tinebase_Record_Interface $_recordToCreate
     * @return void
     */
    protected function _inspectAfterCreate(Tinebase_Record_Interface $_newRecord, Tinebase_Record_Interface $_recordToCreate)
    {
        $this->_writeModLog($_newRecord, null);
        Tinebase_Notes::getInstance()->addSystemNote($_newRecord, Tinebase_Core::getUser(), Tinebase_Model_Note::SYSTEM_NOTE_NAME_CREATED);

        /** @var Node $_newRecord */
        $this->_inspectForPreviewCreation($_newRecord);

        if (true === $this->_notificationActive && Tinebase_Model_Tree_FileObject::TYPE_FILE === $_newRecord->type) {
            Tinebase_ActionQueue::getInstance()->queueAction('Tinebase_FOO_FileSystem.checkForCRUDNotifications', $_newRecord->getId(), 'created');
        }
    }

    /**
     * Updates existing entry
     *
     * @param Tinebase_Record_Interface $_record
     * @param boolean                   $_doModLog
     * @throws Tinebase_Exception_Record_Validation|Tinebase_Exception_InvalidArgument
     * @return Node
     */
    public function update(Tinebase_Record_Interface $_record, $_doModLog = true)
    {
        $oldRecord = $this->get($_record->getId(), true);
        $newRecord = parent::update($_record);

        if (true === $_doModLog) {
            $currentMods = $this->_writeModLog($newRecord, $oldRecord);
            if (null !== $currentMods && $currentMods->count() > 0) {
                Tinebase_Notes::getInstance()->addSystemNote($newRecord, Tinebase_Core::getUser(), Tinebase_Model_Note::SYSTEM_NOTE_NAME_CHANGED, $currentMods);
            }
        }

        /** @var Node $newRecord */
        /** @var Node $oldRecord */
        $this->_inspectForPreviewCreation($newRecord, $newRecord);

        return $newRecord;
    }

    /**
     * writes mod log and system notes
     *
     * @param Tinebase_Record_Interface $_newRecord
     * @param Tinebase_Record_Interface $_oldRecord
     */
    public function updated(Tinebase_Record_Interface $_newRecord, Tinebase_Record_Interface $_oldRecord)
    {
        $currentMods = $this->_writeModLog($_newRecord, $_oldRecord);
        if (null !== $currentMods && $currentMods->count() > 0) {
            Tinebase_Notes::getInstance()->addSystemNote($_newRecord, Tinebase_Core::getUser(), Tinebase_Model_Note::SYSTEM_NOTE_NAME_CHANGED, $currentMods);

            if (true === $this->_notificationActive && Tinebase_Model_Tree_FileObject::TYPE_FILE === $_newRecord->type) {
                Tinebase_ActionQueue::getInstance()->queueAction('Tinebase_FOO_FileSystem.checkForCRUDNotifications', $_newRecord->getId(), 'updated');
            }
        }

        /** @var Node $_newRecord */
        /** @var Node $_oldRecord */
        $this->_inspectForPreviewCreation($_newRecord, $_oldRecord);
    }

    /**
     * Updates multiple entries
     *
     * @param array $_ids to update
     * @param array $_data
     * @return integer number of affected rows
     * @throws Tinebase_Exception_Record_Validation|Tinebase_Exception_InvalidArgument
     */
    public function updateMultiple($_ids, $_data)
    {
        $oldRecords = null;
        if ($this->_omitModLog === true) {
            $oldRecords = $this->getMultiple($_ids);
        }

        $result = parent::updateMultiple($_ids, $_data);

        if (null !== $oldRecords) {
            foreach ($oldRecords as $oldRecord) {
                $newRecord = $this->get($oldRecord->getId());
                $currentMods = $this->_writeModLog($newRecord, $oldRecord);
                if (null !== $currentMods && $currentMods->count() > 0) {
                    Tinebase_Notes::getInstance()->addSystemNote($newRecord, Tinebase_Core::getUser(),
                        Tinebase_Model_Note::SYSTEM_NOTE_NAME_CHANGED, $currentMods);

                }
            }
        }

        return $result;
    }

    /**
     * @param array $_ids
     */
    protected function _inspectBeforeSoftDelete(array $_ids)
    {
        if (!empty($_ids)) {
            foreach($this->getMultiple($_ids) as $node) {
                $this->_writeModLog(null, $node);
            }
        }
    }

    /**
     * @param Node $_newRecord
     * @param Node|null $_oldRecord
     */
    protected function _inspectForPreviewCreation(Node $_newRecord, Node $_oldRecord = null)
    {
        if (true !== Config::getInstance()->{Config::FILESYSTEM}->{Config::FILESYSTEM_CREATE_PREVIEWS}
                || ($_oldRecord !== null && $_oldRecord->hash === $_newRecord->hash) || (int)$_newRecord->revision < 1 ||
                empty($_newRecord->hash) || Tinebase_Model_Tree_FileObject::TYPE_FILE !== $_newRecord->type) {
            return;
        }

        if (false === Tinebase_FileSystem_Previews::getInstance()->canNodeHavePreviews($_newRecord)) {
            return;
        }

        Tinebase_ActionQueue::getInstance()->queueAction('Tinebase_FOO_FileSystem_Previews.createPreviews', $_newRecord->getId(), $_newRecord->revision);
    }

    /**
     * returns columns to fetch in first query and if an id/value pair is requested 
     * 
     * @param array|string $_cols
     * @param Tinebase_Model_Filter_FilterGroup $_filter
     * @param Tinebase_Model_Pagination $_pagination
     * @return array
     */
    protected function _getColumnsToFetch($_cols, Tinebase_Model_Filter_FilterGroup $_filter = NULL, Tinebase_Model_Pagination $_pagination = NULL)
    {
        $result = parent::_getColumnsToFetch($_cols, $_filter, $_pagination);
        
        // sanitize sorting fields
        $foreignTableSortFields = array(
            'size'               =>  'tree_filerevisions',
            'creation_time'      =>  'tree_fileobjects',
            'created_by'         =>  'tree_fileobjects',
            'last_modified_time' =>  'tree_fileobjects',
            'last_modified_by'   =>  'tree_fileobjects',
            'type'               =>  'tree_fileobjects',
            'contenttype'        =>  'tree_fileobjects',
            'revision'           =>  'tree_fileobjects',
        );
        
        foreach ($foreignTableSortFields as $field => $table) {
            if (isset($result[0][$field])) {
                $result[0][$field] = $table . '.' . $field;
            }
        }
        
        return $result;
    }
    
    /**
     * return child identified by name
     * 
     * @param  string|Node  $parentId   the id of the parent node
     * @param  string|Node  $childName  the name of the child node
     * @param  boolean                          $getDeleted = false
     * @param  boolean                          $throw = true
     * @throws Tinebase_Exception_NotFound
     * @return Node|null
     */
    public function getChild($parentId, $childName, $getDeleted = false, $throw = true)
    {
        $parentId  = $parentId  instanceof Node ? $parentId->getId() : $parentId;
        $childName = $childName instanceof Node ? $childName->name   : $childName;
        
        $searchFilter = new Node_Filter(array(
            array(
                'field'     => 'parent_id',
                'operator'  => $parentId ? 'equals' : 'isnull',
                'value'     => $parentId
            ),
            array(
                'field'     => 'name',
                'operator'  => 'equals',
                'value'     => $childName
            )
        ), Tinebase_Model_Filter_FilterGroup::CONDITION_AND, array('ignoreAcl' => true));
        if (true === $getDeleted) {
            $searchFilter->addFilter(new Tinebase_Model_Filter_Bool('is_deleted', 'equals',
                Tinebase_Model_Filter_Bool::VALUE_NOTSET));
        }
        $child = $this->search($searchFilter)->getFirstRecord();
        
        if (!$child) {
            if (true === $throw) {
                throw new Tinebase_Exception_NotFound('child: ' . $childName . ' not found!');
            }
            return null;
        }
        
        return $child;
    }
    
    /**
     * return direct children of tree node
     * 
     * @param  string|Node  $nodeId  the id of the node
     * @param  bool $ignoreAcl default is true
     * @return Tinebase_Record_RecordSet
     */
    public function getChildren($nodeId, $ignoreAcl = true)
    {
        $nodeId = $nodeId instanceof Node ? $nodeId->getId() : $nodeId;

        $options = [];
        if ($ignoreAcl) {
            $options['ignoreAcl'] = true;
        }
        $searchFilter = new Node_Filter(array(
            array(
                'field'     => 'parent_id',
                'operator'  => 'equals',
                'value'     => $nodeId
            )
        ), Tinebase_Model_Filter_FilterGroup::CONDITION_AND, $options);
        $children = $this->search($searchFilter);
        
        return $children;
    }

    /**
     * returns all directory nodes up to the root(s), ignores ACL!
     *
     * @param Tinebase_Record_RecordSet $_nodes
     * @param Tinebase_Record_RecordSet $_result
     * @return Tinebase_Record_RecordSet
     */
    public function getAllFolderNodes(Tinebase_Record_RecordSet $_nodes, Tinebase_Record_RecordSet $_result = null)
    {
        if (null === $_result) {
            $_result = new Tinebase_Record_RecordSet('Node');
        }

        $ids = array();
        /** @var Node $node */
        foreach($_nodes as $node) {
            if (Tinebase_Model_Tree_FileObject::TYPE_FOLDER === $node->type) {
                $_result->addRecord($node);
            }
            if (!empty($node->parent_id)) {
                $ids[] = $node->parent_id;
            }
        }

        if (!empty($ids)) {
            $searchFilter = new Node_Filter(array(
                array(
                    'field'     => 'id',
                    'operator'  => 'in',
                    'value'     => $ids
                )
            ), Tinebase_Model_Filter_FilterGroup::CONDITION_AND, array('ignoreAcl' => true));
            $parents = $this->search($searchFilter);
            $this->getAllFolderNodes($parents, $_result);
        }

        return $_result;
    }

    /**
     * @param  string  $path
     * @return Node
     */
    public function getLastPathNode($path)
    {
        $fullPath = $this->getPathNodes($path);
        
        return $fullPath[$fullPath->count()-1];
    }
    
    /**
     * get object count
     * 
     * @param string $_objectId
     * @return integer
     */
    public function getObjectCount($_objectId)
    {
        return $this->getObjectUsage($_objectId)->count();
    }

    /**
     * get object usage
     *
     * @param string $_objectId
     * @return Tinebase_Record_RecordSet
     */
    public function getObjectUsage($_objectId)
    {
        $searchFilter = new Node_Filter(array(
            array(
                'field'     => 'object_id',
                'operator'  => 'equals',
                'value'     => $_objectId
            )
        ), Tinebase_Model_Filter_FilterGroup::CONDITION_AND, array('ignoreAcl' => true));
        return $this->search($searchFilter);
    }
    
    /**
     * getPathNodes
     * 
     * @param string $_path
     * @return Tinebase_Record_RecordSet
     * @throws Tinebase_Exception_InvalidArgument
     * @throws Tinebase_Exception_NotFound
     */
    public function getPathNodes($_path)
    {
        $pathParts = $this->splitPath($_path);
        
        if (empty($pathParts)) {
            throw new Tinebase_Exception_InvalidArgument('empty path provided');
        }
        
        $parentId  = null;
        $pathNodes = new Tinebase_Record_RecordSet($this->_modelName);
        
        foreach ($pathParts as $pathPart) {
            $searchFilter = new Node_Filter(array(
                array(
                    'field'     => 'parent_id',
                    'operator'  => $parentId ? 'equals' : 'isnull',
                    'value'     => $parentId
                ),
                array(
                    'field'     => 'name',
                    'operator'  => 'equals',
                    'value'     => $pathPart
                )
            ), Tinebase_Model_Filter_FilterGroup::CONDITION_AND, array('ignoreAcl' => true));
            $node = $this->search($searchFilter)->getFirstRecord();
            
            if (!$node) {
                throw new Tinebase_Exception_NotFound('path: ' . $_path . ' not found!');
            }
            
            $pathNodes->addRecord($node);
            
            $parentId = $node->getId();
        }
        
        return $pathNodes;
    }
    
    /**
     * pathExists
     * 
     * @param  string  $_path
     * @return bool
     */
    public function pathExists($_path)
    {
        try {
            $this->getLastPathNode($_path);
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                . ' Found path: ' . $_path);
        } catch (Tinebase_Exception_InvalidArgument $teia) {
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                . ' ' . $teia);
            return false;
        } catch (Tinebase_Exception_NotFound $tenf) {
            if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                . ' ' . $tenf);
            return false;
        }
        
        return true;
    }
    
    public function sanitizePath($path)
    {
        return trim($path, '/');
    }
    
    /**
     * @param  string  $_path
     * @return array
     */
    public function splitPath($_path)
    {
        return explode('/', $this->sanitizePath($_path));
    }

    /**
     * recalculates all folder sizes
     *
     * on error it still continues and tries to calculate as many folder sizes as possible, but returns false
     *
     * @param Tree_FileObject $_fileObjectBackend
     * @return bool
     */
    public function recalculateFolderSize(Tree_FileObject $_fileObjectBackend)
    {
        // no transactions yet
        // get root node ids
        $searchFilter = Node_Filter::getFolderParentIdFilterIgnoringAcl(null);
        return $this->_recalculateFolderSize($_fileObjectBackend, $this->_getIdsOfDeepestFolders($this->search($searchFilter, null, true)));
    }

    /**
     * @param FileObject $_fileObjectBackend
     * @param array $_folderIds
     * @return bool
     */
    protected function _recalculateFolderSize(FileObject $_fileObjectBackend, array $_folderIds)
    {
        $success = true;
        $parentIds = array();
        $transactionManager = Tinebase_TransactionManager::getInstance();

        foreach($_folderIds as $id) {
            $transactionId = $transactionManager->startTransaction($this->_db);

            try {
                try {
                    /** @var Node $record */
                    $record = $this->get($id);
                } catch (Tinebase_Exception_NotFound $tenf) {
                    $transactionManager->commitTransaction($transactionId);
                    continue;
                }

                if (!empty($record->parent_id) && !isset($parentIds[$record->parent_id])) {
                    $parentIds[$record->parent_id] = $record->parent_id;
                }

                $childrenNodes = $this->getChildren($id);
                $size = 0;
                $revision_size = 0;

                /** @var Node $child */
                foreach($childrenNodes as $child) {
                    $size += ((int)$child->size);
                    $revision_size += ((int)$child->revision_size);
                }

                if ($size !== ((int)$record->size) || $revision_size !== ((int)$record->revision_size)) {
                    /** @var Tinebase_Model_Tree_FileObject $fileObject */
                    $fileObject = $_fileObjectBackend->get($record->object_id);
                    $fileObject->size = $size;
                    $fileObject->revision_size = $revision_size;
                    $_fileObjectBackend->update($fileObject);
                }

                $transactionManager->commitTransaction($transactionId);

            // this shouldn't happen
            } catch (Exception $e) {
                $transactionManager->rollBack();
                Tinebase_Exception::log($e);
                $success = false;
            }

            Tinebase_Lock::keepLocksAlive();
        }

        if (!empty($parentIds)) {
            $success = $this->_recalculateFolderSize($_fileObjectBackend, $parentIds) && $success;
        }

        return $success;
    }

    /**
     * returns ids of folders that do not have any sub folders
     *
     * @param array $_folderIds
     * @return array
     */
    protected function _getIdsOfDeepestFolders(array $_folderIds)
    {
        $result = array();
        $subFolderIds = array();
        foreach($_folderIds as $folderId) {
            // children folders
            $searchFilter = filter::getFolderParentIdFilterIgnoringAcl($folderId);
            $nodeIds = $this->search($searchFilter, null, true);
            if (empty($nodeIds)) {
                // no children, this is a result
                $result[] = $folderId;
            } else {
                $subFolderIds = array_merge($subFolderIds, $nodeIds);
            }
        }

        if (!empty($subFolderIds)) {
            $result = array_merge($result, $this->_getIdsOfDeepestFolders($subFolderIds));
        }

        return $result;
    }
}
