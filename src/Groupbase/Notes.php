<?php
namespace Fgsl\Groupware\Groupbase;

use Fgsl\Groupware\Groupbase\Backend\Sql as BackendSql;
use Fgsl\Groupware\Groupbase\Backend\Sql\SqlInterface;
use Fgsl\Groupware\Groupbase\Backend\Sql\Filter\FilterGroup as SqlFilterFilterGroup;
use Fgsl\Groupware\Groupbase\Db\Table;
use Zend\Db\Adapter\AdapterInterface;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Record\RecordSetDiff;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Model\NoteFilter;
use Fgsl\Groupware\Groupbase\Model\Pagination;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Sabre\DAV\Exception\NotImplemented;
use Zend\Db\Adapter\Adapter;
use Fgsl\Groupware\Groupbase\Exception\AccessDenied;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Model\NoteType;
use Fgsl\Groupware\Model\Note;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Exception\Record\Validation;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\Record\Diff;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Zend\I18n\Translator\TranslatorInterface;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Class for handling notes
 * 
 * @package     Tinebase
 * @subpackage  Notes 
 */
class Notes implements SqlInterface 
{
    /**
     * @var AdapterInterface
     */
    protected $_db;

    /**
     * @var Table
     */
    protected $_notesTable;
    
    /**
     * @var Table
     */
    protected $_noteTypesTable;
    
    /**
     * default record backend
     */
    const DEFAULT_RECORD_BACKEND = 'Sql';
    
    /**
     * number of notes per record for activities panel
     * (NOT the tab panel)
     */
    const NUMBER_RECORD_NOTES = 8;

    /**
     * max length of note text
     * 
     * @var integer
     */
    const MAX_NOTE_LENGTH = 10000;
    
    /**
     * don't clone. Use the singleton.
     */
    private function __clone()
    {
        
    }

    /**
     * holds the instance of the singleton
     *
     * @var Notes
     */
    private static $_instance = NULL;
        
    /**
     * the singleton pattern
     *
     * @return Notes
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Notes;
        }
        
        return self::$_instance;
    }

    /**
     * the private constructor
     *
     */
    private function __construct()
    {

        $this->_db = Core::getDb();
        
        $this->_notesTable = new Table(array(
            'name' => SQL_TABLE_PREFIX . 'notes',
            'primary' => 'id'
        ));
        
        $this->_noteTypesTable = new Table(array(
            'name' => SQL_TABLE_PREFIX . 'note_types',
            'primary' => 'id'
        ));
    }
    
    /************************** sql backend interface ************************/
    
    /**
     * get table name
     *
     * @return string
     */
    public function getTableName()
    {
        return 'notes';
    }
    
    /**
     * get table prefix
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->_db->table_prefix;
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
     * returns the db schema
     * 
     * @return array
     */
    public function getSchema()
    {
        return Table::getTableDescriptionFromCache(SQL_TABLE_PREFIX . 'notes', $this->_db);
    }
    
    /************************** get notes ************************/

    /**
     * search for notes
     *
     * @param NoteFilter $_filter
     * @param Pagination $_pagination
     * @param boolean $ignoreACL
     * @return RecordSet subtype Note
     */
    public function searchNotes(NoteFilter $_filter, Pagination $_pagination = NULL, $ignoreACL = true)
    {
        $select = $this->_db->select()
            ->from(array('notes' => SQL_TABLE_PREFIX . 'notes'))
            ->where($this->_db->quoteIdentifier('is_deleted') . ' = 0');
        
        if (! $ignoreACL) {
            $this->_checkFilterACL($_filter);
        }
        
        SqlFilterFilterGroup::appendFilters($select, $_filter, $this);
        if ($_pagination !== NULL) {
            $_pagination->appendPaginationSql($select);
        }
        
        $stmt = $this->_db->query($select);
        $rows = $stmt->fetchAll(Adapter::FETCH_ASSOC);
        
        $result = new RecordSet('Note', $rows, true);

        return $result;
    }
    
    /**
     * checks acl of filter
     * 
     * @param NoteFilter $noteFilter
     * @throws AccessDenied
     */
    protected function _checkFilterACL(NoteFilter $noteFilter)
    {
        $recordModelFilter = $noteFilter->getFilter('record_model');
        if (empty($recordModelFilter)) {
            throw new AccessDenied('record model filter required');
        }
        
        $recordIdFilter = $noteFilter->getFilter('record_id');
        if (empty($recordIdFilter) || $recordIdFilter->getOperator() !== 'equals') {
            throw new AccessDenied('record id filter required or wrong operator');
        }
        
        $recordModel = $recordModelFilter->getValue();
        if (! is_string($recordModel)) {
            throw new AccessDenied('no explicit record model set in filter');
        }
        
        try {
            Core::getApplicationInstance($recordModel)->get($recordIdFilter->getValue());
        } catch (AccessDenied $tead) {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Do not fetch record notes because user has no read grant for container');
            $recordIdFilter->setValue('');
        }
    }
    
    /**
     * count notes
     *
     * @param NoteFilter $_filter
     * @param boolean $ignoreACL
     * @return int notes count
     */
    public function searchNotesCount(NoteFilter $_filter, $ignoreACL = true)
    {
        $select = $this->_db->select()
            ->from(array('notes' => SQL_TABLE_PREFIX . 'notes'), array('count' => 'COUNT(' . $this->_db->quoteIdentifier('id') . ')'))
            ->where($this->_db->quoteIdentifier('is_deleted') . ' = 0');
        
        if (! $ignoreACL) {
            $this->_checkFilterACL($_filter);
        }
        
        SqlFilterFilterGroup::appendFilters($select, $_filter, $this);
        
        $result = $this->_db->fetchOne($select);
        return $result;
    }
    
    /**
     * get a single note
     *
     * @param   string $_noteId
     * @return  Note
     * @throws  NotFound
     */
    public function getNote($_noteId)
    {
        $row = $this->_notesTable->fetchRow($this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ? AND '
            . $this->_db->quoteIdentifier('is_deleted') . ' = 0', (string) $_noteId));
        
        if (!$row) {
            throw new NotFound('Note not found.');
        }
        
        return new Note($row->toArray());
    }
    
    /**
     * get all notes of a given record (calls searchNotes)
     * 
     * @param  string $_model     model of record
     * @param  string $_id        id of record
     * @param  string $_backend   backend of record
     * @param  boolean $_onlyNonSystemNotes get only non-system notes per default
     * @return RecordSet of Note
     */
    public function getNotesOfRecord($_model, $_id, $_backend = 'Sql', $_onlyNonSystemNotes = TRUE)
    {
        $backend = ucfirst(strtolower($_backend));

        $filter = $this->_getNotesFilter($_id, $_model, $backend, $_onlyNonSystemNotes);
        
        $pagination = new Pagination(array(
            'limit' => Notes::NUMBER_RECORD_NOTES,
            'sort'  => 'creation_time',
            'dir'   => 'DESC'
        ));
        
        $result = $this->searchNotes($filter, $pagination);
            
        return $result;
    }
    
    /**
     * get all notes of all given records (calls searchNotes)
     * 
     * @param  RecordSet  $_records       the recordSet
     * @param  string                     $_notesProperty  the property in the record where the notes are in (defaults: 'notes')
     * @param  string                     $_backend   backend of record
     * @return RecordSet|null
     */
    public function getMultipleNotesOfRecords($_records, $_notesProperty = 'notes', $_backend = 'Sql', $_onlyNonSystemNotes = TRUE)
    {
        if (count($_records) == 0) {
            return null;
        }
        
        $modelName = $_records->getRecordClassName();
        $filter = $this->_getNotesFilter($_records->getArrayOfIds(), $modelName, $_backend, $_onlyNonSystemNotes);
        
        // search and add index
        $notesOfRecords = $this->searchNotes($filter);
        $notesOfRecords->addIndices(array('record_id'));
        
        // add notes to records
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Getting ' . count($notesOfRecords) . ' notes for ' . count($_records) . ' records.');
        foreach($_records as $record) {
            //$record->notes = Notes::getInstance()->getNotesOfRecord($modelName, $record->getId(), $_backend);
            $record->{$_notesProperty} = $notesOfRecords->filter('record_id', $record->getId());
        }

        return $notesOfRecords;
    }
    
    /************************** set / add / delete notes ************************/
    
    /**
     * sets notes of a record
     * 
     * @param RecordInterface  $_record            the record object
     * @param string                    $_backend           backend (default: 'Sql')
     * @param string                    $_notesProperty     the property in the record where the tags are in (default: 'notes')
     * 
     * @todo add update notes ?
     */
    public function setNotesOfRecord($_record, $_backend = 'Sql', $_notesProperty = 'notes')
    {
        $model = get_class($_record);
        $backend = ucfirst(strtolower($_backend));
        
        //if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_record->toArray(), TRUE));
        
        $currentNotes = $this->getNotesOfRecord($model, $_record->getId(), $backend);
        $notes = $_record->$_notesProperty;
        
        if ($notes instanceOf RecordSet) {
            $notesToSet = $notes;
        } else {
            if (count($notes) > 0 && $notes[0] instanceOf RecordInterface) {
                // array of notes records given
                $notesToSet = new RecordSet('Note', $notes);
            } else {
                // array of arrays given
                $notesToSet = new RecordSet('Note');
                foreach($notes as $noteData) {
                    if (!empty($noteData)) {
                        $noteArray = (!is_array($noteData)) ? array('note' => $noteData) : $noteData;
                        if (!isset($noteArray['note_type_id'])) {
                            // get default note type
                            $defaultNote = $this->getNoteTypeByName('note');
                            $noteArray['note_type_id'] = $defaultNote->getId();
                        }
                        try {
                            $note = new Note($noteArray);
                            $notesToSet->addRecord($note);
                            
                        } catch (Validation $terv) {
                            // discard invalid notes here
                            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ 
                                . ' Note is invalid! '
                                . $terv->getMessage()
                                //. print_r($noteArray, TRUE)
                            );
                        }
                    }
                }
            }
            $_record->$_notesProperty = $notesToSet;
        }
        
        //$toAttach = array_diff($notesToSet->getArrayOfIds(), $currentNotesIds);
        $toDetach = array_diff($currentNotes->getArrayOfIds(), $notesToSet->getArrayOfIds());
        $toDelete = new RecordSet('Note');
        foreach($toDetach as $detachee) {
            $toDelete->addRecord($currentNotes->getById($detachee));
        }

        // delete detached/deleted notes
        $this->deleteNotes($toDelete);
        
        // add new notes
        Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Adding ' . count($notesToSet) . ' note(s) to record.');
        foreach ($notesToSet as $note) {
            //if (in_array($note->getId(), $toAttach)) {
            if (!$note->getId()) {
                $note->record_model = $model;
                $note->record_backend = $backend;
                $note->record_id = $_record->getId();
                $this->addNote($note);
            }
        }
    }
    
    /**
     * add new note
     *
     * @param Note $_note
     */
    public function addNote(Note $_note)
    {
        if (!$_note->getId()) {
            $id = $_note->generateUID();
            $_note->setId($id);
        }

        ModificationLog::getInstance()->setRecordMetaData($_note, 'create');
        
        $data = $_note->toArray(FALSE, FALSE);

        if (mb_strlen($data['note']) > 65535) {
            $data['note'] = mb_substr($data['note'], 0, 65535);
        }
        
        $this->_notesTable->insert($data);
    }

    /**
     * add new system note
     *
     * @param RecordInterface|string $_record
     * @param string|ModelUser $_userId
     * @param string $_type (created|changed)
     * @param RecordSet|string $_mods (ModificationLog)
     * @param string $_backend   backend of record
     * @return Note|boolean
     * 
     * @todo get field translations from application?
     * @todo attach modlog record (id) to note instead of saving an ugly string
     */
    public function addSystemNote($_record, $_userId = NULL, $_type = Note::SYSTEM_NOTE_NAME_CREATED, $_mods = NULL, $_backend = 'Sql', $_modelName = NULL)
    {
        if (empty($_mods) && $_type === Note::SYSTEM_NOTE_NAME_CHANGED) {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ .' Nothing changed -> do not add "changed" note.');
            return FALSE;
        }
        
        $id = ($_record instanceof RecordInterface) ? $_record->getId() : $_record;
        $modelName = ($_modelName !== NULL) ? $_modelName : (($_record instanceof RecordInterface) ? get_class($_record) : 'unknown');
        if (($_userId === NULL)) {
            $_userId = Core::getUser();
        }
        $user = ($_userId instanceof ModelUser) ? $_userId : User::getInstance()->getUserById($_userId);
        
        $translate = Translation::getTranslation('Tinebase');
        $noteText = $translate->_($_type) . ' ' . $translate->_('by') . ' ' . $user->accountDisplayName;
        
        if ($_mods !== NULL) {
            if ($_mods instanceof RecordSet && count($_mods) > 0) {
                if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ 
                    .' mods to log: ' . print_r($_mods->toArray(), TRUE));
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ 
                    .' Adding "' . $_type . '" system note note to record (id ' . $id . ')');
                
                $noteText .= ' | ' .$translate->_('Changed fields:');
                foreach ($_mods as $mod) {
                    $modifiedAttribute = $mod->modified_attribute;
                    if (empty($modifiedAttribute)) {
                        $noteText.= ' ' . $this->_getSystemNoteChangeText($mod, $translate);
                    } else {
                        $noteText .= ' ' . $translate->_($mod->modified_attribute) . ' (' . $this->_getSystemNoteChangeText($mod) . ')';
                    }
                }
            } else if (is_string($_mods)) {
                $noteText = $_mods;
            }
        }
        
        $noteType = $this->getNoteTypeByName($_type);
        $note = new Note(array(
            'note_type_id'      => $noteType->getId(),
            'note'              => substr($noteText, 0, self::MAX_NOTE_LENGTH),
            'record_model'      => $modelName,
            'record_backend'    => ucfirst(strtolower($_backend)),
            'record_id'         => $id,
        ));
        
        return $this->addNote($note);
    }
    
    /**
     * get system note change text
     * 
     * @param ModificationLog $modification
     * @param TranslatorInterface $translate
     * @return string
     */
    protected function _getSystemNoteChangeText(ModificationLog $modification, TranslatorInterface $translate = null)
    {
        $recordProperties = [];
        /** @var RecordInterface $model */
        if (($model = $modification->record_type) && ($mc = $model::getConfiguration())) {
            $recordProperties = $mc->recordFields;
        }
        $modifiedAttribute = $modification->modified_attribute;

        // new ModificationLog implementation
        if (empty($modifiedAttribute)) {
            $diff = new Diff(json_decode($modification->new_value, true));
            $return = '';
            foreach ($diff->diff as $attribute => $value) {

                if (is_array($value) && isset($value['model']) && isset($value['added'])) {
                    $diff = new RecordSetDiff($value);

                    if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                        . ' fetching translated text for diff: ' . print_r($diff->toArray(), true));

                    $return .= ' ' . $translate->_($attribute) . ' (' . $diff->getTranslatedDiffText() . ')';
                } else {
                    $oldData = $diff->oldData[$attribute];

                    if (isset($recordProperties[$attribute]) && ($oldData || $value) &&
                            isset($recordProperties[$attribute]['config']['controllerClassName']) && ($controller =
                            $recordProperties[$attribute]['config']['controllerClassName']::getInstance()) &&
                            method_exists($controller, 'get')) {
                        if ($oldData) {
                            try {
                                $oldDataString = $controller->get($oldData, null, false, true)->getTitle();
                            } catch(NotFound $e) {
                                $oldDataString = $oldData;
                            }
                        } else {
                            $oldDataString = '';
                        }
                        if ($value) {
                            try {
                                $valueString = $controller->get($value, null, false, true)->getTitle();
                            } catch(NotFound $e) {
                                $valueString = $value;
                            }
                        } else {
                            $valueString = '';
                        }
                    } else {
                        if (is_array($oldData)) {
                            $oldDataString = '';
                            foreach ($oldData as $key => $val) {
                                if (is_object($val)) {
                                    $val = $val->toArray();
                                }
                                $oldDataString .= ' ' . $key . ': ' . (is_array($val) ? (isset($val['id']) ? $val['id'] : print_r($val,
                                        true)) : $val);
                            }
                        } else {
                            $oldDataString = $oldData;
                        }
                        if (is_array($value)) {
                            $valueString = '';
                            foreach ($value as $key => $val) {
                                if (is_object($val)) {
                                    $val = $val->toArray();
                                }
                                $valueString .= ' ' . $key . ': ' . (is_array($val) ? (isset($val['id']) ? $val['id'] : print_r($val,
                                        true)) : $val);
                            }
                        } else {
                            $valueString = $value;
                        }
                    }

                    if (null !== $oldDataString || (null !== $valueString && '' !== $valueString)) {
                        $return .= ' ' . $translate->_($attribute) . ' (' . $oldDataString . ' -> ' . $valueString . ')';
                    }
                }
            }

            return $return;

        } else {
            if (Helper::is_json($modification->new_value)) {
                $newValueArray = json_decode($modification->new_value);
                if ((isset($newValueArray['model']) || array_key_exists('model', $newValueArray)) && (isset($newValueArray['added']) || array_key_exists('added', $newValueArray))) {
                    $diff = new RecordSetDiff($newValueArray);

                    if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                        . ' fetching translated text for diff: ' . print_r($diff->toArray(), true));

                    return $diff->getTranslatedDiffText();
                }
            }

            return $modification->old_value . ' -> ' . $modification->new_value;
        }
    }
    
    /**
     * add multiple modification system nodes
     * 
     * @param RecordSet $_mods
     * @param string $_userId
     * @param string $modelName
     */
    public function addMultipleModificationSystemNotes($_mods, $_userId, $modelName = null)
    {
        $_mods->addIndices(array('record_id'));
        foreach ($_mods->record_id as $recordId) {
            $modsOfRecord = $_mods->filter('record_id', $recordId);
            $this->addSystemNote($recordId, $_userId, Note::SYSTEM_NOTE_NAME_CHANGED, $modsOfRecord, 'Sql', $modelName);
        }
    }

    /**
     * delete notes
     *
     * @param RecordSet $notes
     */
    public function deleteNotes(RecordSet $notes)
    {
        $sqlBackend = new BackendSql(
            array(
                'tableName' => $this->getTableName(),
                'modelName' => 'Note'
            ),
            $this->getAdapter());

        foreach($notes as $note) {
            ModificationLog::setRecordMetaData($note, 'delete', $note);
            $sqlBackend->update($note);
        }
    }

    /**
     * undelete notes
     *
     * @param array $ids
     */
    public function unDeleteNotes(array $ids)
    {
        $sqlBackend = new BackendSql(
            array(
                'tableName' => $this->getTableName(),
                'modelName' => 'Note'
            ),
            $this->getAdapter());

        $notes = $sqlBackend->getMultiple($ids);
        foreach($notes as $note) {
            ModificationLog::setRecordMetaData($note, 'undelete', $note);
            $sqlBackend->update($note);
        }
    }

    /**
     * delete notes
     *
     * @param  string $_model     model of record
     * @param  string $_backend   backend of record
     * @param  string $_id        id of record
     */
    public function deleteNotesOfRecord($_model, $_backend, $_id)
    {
        $backend = ucfirst(strtolower($_backend));
        
        $notes = $this->getNotesOfRecord($_model, $_id, $backend);

        $this->deleteNotes($notes);
    }
    
    /**
     * get note filter
     * 
     * @param string|array $_id
     * @param string $_model
     * @param string $_backend
     * @param boolean $onlyNonSystemNotes (optional)
     * @return NoteFilter
     */
    protected function _getNotesFilter($_id, $_model, $_backend, $_onlyNonSystemNotes = TRUE)
    {
        $backend = ucfirst(strtolower($_backend));
        
        $filter = new NoteFilter(array(
            array(
                'field' => 'record_model',
                'operator' => 'equals',
                'value' => $_model
            ),
            array(
                'field' => 'record_backend',
                'operator' => 'equals',
                'value' => $backend
            ),
            array(
                'field' => 'record_id',
                'operator' => 'in',
                'value' => (array) $_id
            ),
            array(
                'field' => 'note_type_id',
                'operator' => 'in',
                'value' => $this->getNoteTypes($_onlyNonSystemNotes, true)
            )
        ));
        
        return $filter;
    }
    
    /************************** note types *******************/
    
    /**
     * get all note types
     *
     * @param boolean $onlyNonSystemNotes (optional)
     * @return RecordSet of NoteType
     */
    public function getNoteTypes($onlyNonSystemNotes = false, $onlyIds = false)
    {
        $select = $this->_db->select()
            ->from(array('note_types' => SQL_TABLE_PREFIX . 'note_types'), ($onlyIds ? 'id' : '*'));
        
        if ($onlyNonSystemNotes) {
            $select->where($this->_db->quoteIdentifier('is_user_type') . ' = 1');
        }
        
        $stmt = $this->_db->query($select);
        
        if ($onlyIds) {
            $types = $stmt->fetchAll(Adapter::FETCH_COLUMN);
        } else {
            $rows = $stmt->fetchAll(Adapter::FETCH_ASSOC);
            
            $types = new RecordSet('NoteType', $rows, true);
        }
        
        return $types;
    }

    /**
     * get note type by name
     *
     * @param string $_name
     * @return NoteType
     * @throws  NotFound
     */
    public function getNoteTypeByName($_name)
    {
        $row = $this->_noteTypesTable->fetchRow($this->_db->quoteInto($this->_db->quoteIdentifier('name') . ' = ?', $_name));
        
        if (!$row) {
            throw new NotFound('Note type not found.');
        }
        
        return new NoteType($row->toArray());
    }
    
    /**
     * add new note type
     *
     * @param NoteType $_noteType
     */
    public function addNoteType(NoteType $_noteType)
    {
        if (!$_noteType->getId()) {
            $id = $_noteType->generateUID();
            $_noteType->setId($id);
        }
        
        $data = $_noteType->toArray();

        $this->_noteTypesTable->insert($data);
    }

    /**
     * update note type
     *
     * @param NoteType $_noteType
     */
    public function updateNoteType(NoteType $_noteType)
    {
        $data = $_noteType->toArray();

        $where  = array(
            $this->_noteTypesTable->getAdapter()->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $_noteType->getId()),
        );
        
        $this->_noteTypesTable->update($data, $where);
    }
    
    /**
     * delete note type
     *
     * @param integer $_noteTypeId
     */
    public function deleteNoteType($_noteTypeId)
    {
        $this->_noteTypesTable->delete($this->_db->quoteInto($this->_db->quoteIdentifier('id') . ' = ?', $_noteTypeId));
    }

    /**
     * Search for records matching given filter
     *
     *
     * @param  FilterGroup $_filter
     * @param  Pagination $_pagination
     * @param  array|string|boolean $_cols columns to get, * per default / use self::IDCOL or TRUE to get only ids
     * @return RecordSet
     * @throws NotImplemented
     */
    public function search(FilterGroup $_filter = NULL, Pagination $_pagination = NULL, $_cols = '*')
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Gets total count of search with $_filter
     *
     * @param FilterGroup $_filter
     * @return int
     * @throws NotImplemented
     */
    public function searchCount(FilterGroup $_filter)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Return a single record
     *
     * @param string $_id
     * @param boolean $_getDeleted get deleted records
     * @return RecordInterface
     * @throws NotImplemented
     */
    public function get($_id, $_getDeleted = FALSE)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Returns a set of records identified by their id's
     *
     * @param string|array $_ids Ids
     * @param array $_containerIds all allowed container ids that are added to getMultiple query
     * @return RecordSet of RecordInterface
     * @throws NotImplemented
     */
    public function getMultiple($_ids, $_containerIds = NULL)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Gets all entries
     *
     * @param string $_orderBy Order result by
     * @param string $_orderDirection Order direction - allowed are ASC and DESC
     * @throws InvalidArgument
     * @return RecordSet
     * @throws NotImplemented
     */
    public function getAll($_orderBy = 'id', $_orderDirection = 'ASC')
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Create a new persistent contact
     *
     * @param  RecordInterface $_record
     * @return RecordInterface
     * @throws NotImplemented
     */
    public function create(RecordInterface $_record)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Upates an existing persistent record
     *
     * @param  RecordInterface $_record
     * @return RecordInterface|NULL
     * @throws NotImplemented
     */
    public function update(RecordInterface $_record)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Updates multiple entries
     *
     * @param array $_ids to update
     * @param array $_data
     * @return integer number of affected rows
     * @throws NotImplemented
     */
    public function updateMultiple($_ids, $_data)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * Deletes one or more existing persistent record(s)
     *
     * @param string|array $_identifier
     * @return void
     * @throws NotImplemented
     */
    public function delete($_identifier)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * get backend type
     *
     * @return string
     * @throws NotImplemented
     */
    public function getType()
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * sets modlog active flag
     *
     * @param $_bool
     * @return AbstractSql
     */
    public function setModlogActive($_bool)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * checks if modlog is active or not
     *
     * @return bool
     */
    public function getModlogActive()
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * fetch a single property for all records defined in array of $ids
     *
     * @param array|string $ids
     * @param string $property
     * @return array (key = id, value = property value)
     */
    public function getPropertyByIds($ids, $property)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }

    /**
     * get all Notes, including deleted ones, no ACL check
     *
     * @ param boolean $ignoreACL
     * @ param boolean $getDeleted
     * @return RecordSet subtype Note
     */
    public function getAllNotes($orderBy = null, $limit = null, $offset = null)
    {
        $select = $this->_db->select()
            ->from(array('notes' => SQL_TABLE_PREFIX . 'notes'));
        if (null !== $orderBy) {
            $select->order($orderBy);
        }
        if (null !== $limit) {
            $select->limit($limit, $offset);
        }

        $stmt = $this->_db->query($select);
        $rows = $stmt->fetchAll(Adapter::FETCH_ASSOC);

        $result = new RecordSet('Fgsl\Groupware\Groupbase\Model\Note', $rows, true);

        return $result;
    }

    /**
     * permanently delete notes by id
     *
     * @param array $_ids
     * @return int
     */
    public function purgeNotes(array $_ids)
    {
        return $this->_db->delete(SQL_TABLE_PREFIX . 'notes', $this->_db->quoteInto('id IN (?)', $_ids));
    }

    /**
     * checks if a records with identifiers $_ids exists, returns array of identifiers found
     *
     * @param array $_ids
     * @param bool $_getDeleted
     * @return array
     */
    public function has(array $_ids, $_getDeleted = false)
    {
        throw new NotImplemented(__METHOD__ . ' is not implemented');
    }
}
