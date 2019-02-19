<?php
namespace Fgsl\Groupware\Groupbase\Backend\Sql;

use Fgsl\Groupware\Groupbase\Backend\Sql\SqlInterface;
use Fgsl\Groupware\Groupbase\Backend\Sql\Command\CommandInterface;
use Fgsl\Groupware\Groupbase\Exception\Backend\Database;
use Zend\Db\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Backend\Sql\Command\Command;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Backend\AbstractBackend;
use Fgsl\Groupware\Groupbase\Db\Table;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Cache\PerRequest;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Record\RecordSetFast;
use Zend\Db\Sql\Select;
use Fgsl\Groupware\Groupbase\Record\RecordInterface;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Pagination;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Abstract class for a sql backend
 * 
 * @package     Groupbase
 * @subpackage  Backend
 */
abstract class AbstractSql extends AbstractBackend implements SqlInterface
{
    /**
     * placeholder for id column for search()/_getSelect()
     */
    const IDCOL             = '_id_';

    /**
     * placeholder for all columns for search()/_getSelect()
     */
    const ALLCOL            = '*';
    
    /**
     * fetch single column with db query
     */
    const FETCH_MODE_SINGLE = 'fetch_single';

    /**
     * fetch two columns (id + X) with db query
     */
    const FETCH_MODE_PAIR   = 'fetch_pair';
    
    /**
     * fetch all columns with db query
     */
    const FETCH_ALL         = 'fetch_all';

    /**
     * backend type
     *
     * @var string
     */
    protected $_type = 'Sql';
    
    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = NULL;
    
    /**
     * Table prefix
     *
     * @var string
     */
    protected $_tablePrefix = NULL;
    
    /**
     * if modlog is active, we add 'is_deleted = 0' to select object in _getSelect()
     *
     * @var boolean
     */
    protected $_modlogActive = FALSE;
    
    /**
     * Identifier
     *
     * @var string
     */
    protected $_identifier = 'id';
    
    /**
     * @var AdapterInterface
     */
    protected $_db;
    
    /**
     * @var CommandInterface
     */
    protected $_dbCommand;
    
    /**
     * schema of the table
     *
     * @var array
     */
    protected $_schema = NULL;
    
    /**
     * foreign tables 
     * name => array(table, joinOn, field)
     *
     * @var array
     */
    protected $_foreignTables = array();
    
    /**
     * additional search count columns
     * 
     * @var array
     */
    protected $_additionalSearchCountCols = array();
    
    /**
     * default secondary sort criteria
     * 
     * @var string
     */
    protected $_defaultSecondarySort = NULL;
    
    /**
     * default column(s) for count
     * 
     * @var string
     */
    protected $_defaultCountCol = self::ALLCOL;
    
    /**
     * Additional columns _getSelect()
     */
    protected $_additionalColumns = array();

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
     * @throws 
     */
    public function __construct($_dbAdapter = NULL, $_options = array())
    {
        $this->_db        = ($_dbAdapter instanceof AdapterInterface) ? $_dbAdapter : Core::getDb();
        $this->_dbCommand = Command::factory($this->_db);
        
        $this->_modelName            = (isset($_options['modelName']) || array_key_exists('modelName', $_options))            ? $_options['modelName']    : $this->_modelName;
        $this->_tableName            = (isset($_options['tableName']) || array_key_exists('tableName', $_options))            ? $_options['tableName']    : $this->_tableName;
        /** @noinspection PhpUndefinedFieldInspection */
        $this->_tablePrefix          = (isset($_options['tablePrefix']) || array_key_exists('tablePrefix', $_options))          ? $_options['tablePrefix']  : $this->_db->table_prefix;
        $this->_modlogActive         = (isset($_options['modlogActive']) || array_key_exists('modlogActive', $_options))         ? $_options['modlogActive'] : $this->_modlogActive;
        
        foreach ($this->_additionalColumns as $name => $query) {
            $this->_additionalColumns[$name] = str_replace("{prefix}", $this->_tablePrefix, $query);
        }

        if (! ($this->_tableName && $this->_modelName)) {
            throw new Database('modelName and tableName must be configured or given.');
        }
        if (! $this->_db) {
            throw new Database('Database adapter must be configured or given.');
        }
    }
    
    /*************************** getters and setters *********************************/
    
    /**
     * sets modlog active flag
     * 
     * @param $_bool
     * @return AbstractSql
     */
    public function setModlogActive($_bool)
    {
        $this->_modlogActive = (bool) $_bool;
        return $this;
    }
    
    /**
     * checks if modlog is active or not
     * 
     * @return bool
     */
    public function getModlogActive()
    {
        return $this->_modlogActive;
    }

    /**
     * returns the db schema
     * @return array
     * @throws Database
     */
    public function getSchema()
    {
        if (!$this->_schema) {
            try {
                $this->_schema = Table::getTableDescriptionFromCache($this->_tablePrefix . $this->_tableName, $this->_db);
                if (!is_array($this->_schema)) {
                    throw new Database('could not get table description for :' . $this->_tablePrefix . $this->_tableName);
                }
            } catch (\Exception $zdae) {
                throw new Database('Connection failed: ' . $zdae->getMessage());
            }
        }
        
        return $this->_schema;
    }
    
    /*************************** get/search funcs ************************************/

    /**
     * Gets one entry (by id)
     *
     * @param string|RecordInterface $_id
     * @param boolean $_getDeleted get deleted records
     * @return RecordInterface
     * @throws InvalidArgument
     */
    public function get($_id, $_getDeleted = FALSE) 
    {
        if (empty($_id)) {
            throw new InvalidArgument('$_id can not be empty');
        }

        $id = AbstractRecord::convertId($_id, $this->_modelName);
        
        return $this->getByProperty($id, $this->_identifier, $_getDeleted);
    }

    /**
     * splits identifier if table name is given (i.e. for joined tables)
     *
     * @return string identifier name
     */
    protected function _getRecordIdentifier()
    {
        if (preg_match("/\./", $this->_identifier)) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($table, $identifier) = explode('.', $this->_identifier);
        } else {
            $identifier = $this->_identifier;
        }
        
        return $identifier;
    }

    /**
     * Gets one entry (by property)
     *
     * @param  mixed  $value
     * @param  string $property
     * @param  bool   $getDeleted
     * @return RecordInterface
     * @throws NotFound
     */
    public function getByProperty($value, $property = 'name', $getDeleted = FALSE) 
    {
        $select = $this->_getSelect(self::ALLCOL, $getDeleted)
            ->limit(1);
        
        if ($value !== NULL) {
            $select->where($this->_db->quoteIdentifier($this->_tableName . '.' . $property) . ' = ?', $value);
        } else {
            $select->where($this->_db->quoteIdentifier($this->_tableName . '.' . $property) . ' IS NULL');
        }
        
        AbstractSql::traitGroup($select);
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();
        
        if (!$queryResult) {
            $messageValue = ($value !== NULL) ? $value : 'NULL';
            throw new NotFound($this->_modelName . " record with $property = $messageValue not found!");
        }
        
        $result = $this->_rawDataToRecord($queryResult);
        
        return $result;
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
        $select = $this->_getSelect(array($property, $this->_identifier));
        $select->where($this->_db->quoteIdentifier($this->_tableName . '.' . $this->_identifier) . ' IN (?)', (array) $ids);
        AbstractSql::traitGroup($select);
        
        $this->_checkTracing($select);
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetchAll();
        $stmt->closeCursor();
        
        $result = array();
        foreach($queryResult as $row) {
            $result[$row[$this->_identifier]] = $row[$property];
        }
        return $result;
    }
    
    /**
     * converts raw data from adapter into a single record
     *
     * @param  array $_rawData
     * @return RecordInterface
     */
    protected function _rawDataToRecord(array &$_rawData)
    {
        $this->_explodeForeignValues($_rawData);

        /** @var RecordInterface $result */
        $result = new $this->_modelName(null, true);
        $result->hydrateFromBackend($_rawData);

        return $result;
    }

    /**
     * explode foreign values
     *
     * @param array $_data
     */
    protected function _explodeForeignValues(array &$_data)
    {
        foreach ($this->_foreignTables as $field => $table) {
            $isSingleValue = isset($table['singleValue']) && $table['singleValue'];
            if (! $isSingleValue) {
                $_data[$field] = empty($_data[$field]) ? [] : explode(',', $_data[$field]);
            }
        }
    }
    
    /**
     * gets multiple entries (by property)
     *
     * @param  mixed  $_value
     * @param  string $_property
     * @param  bool   $_getDeleted
     * @param  string $_orderBy        defaults to $_property
     * @param  string $_orderDirection defaults to 'ASC'
     * @return RecordSet
     */
    public function getMultipleByProperty($_value, $_property='name', $_getDeleted = FALSE, $_orderBy = NULL, $_orderDirection = 'ASC')
    {
        $columnName = $this->_db->quoteIdentifier($this->_tableName . '.' . $_property);
        if (! empty($_value)) {
            $value = (array)$_value;
            $orderBy = $this->_tableName . '.' . ($_orderBy ? $_orderBy : $_property);

            $select = $this->_getSelect(self::ALLCOL, $_getDeleted)
                ->where($columnName . 'IN (?)', $value)
                ->order($orderBy . ' ' . $_orderDirection);

                AbstractSql::traitGroup($select);
        } else {
                $select = $this->_getSelect(self::ALLCOL, $_getDeleted)->where('1=0');
        }
        
        $this->_checkTracing($select);
        
        $rawData = $this->_db->query($select)->fetchAll();
        
        $resultSet = $this->_rawDataToRecordSet($rawData);
        $resultSet->addIndices(array($_property));
        
        return $resultSet;
    }
    
    /**
     * converts raw data from adapter into a set of records
     *
     * @param  array $_rawDatas of arrays
     * @return RecordSet
     */
    protected function _rawDataToRecordSet(array &$_rawDatas)
    {
        if (! empty($this->_foreignTables)) {
            foreach ($this->_foreignTables as $field => $table) {
                $isSingleValue = isset($table['singleValue']) && $table['singleValue'];
                if (!$isSingleValue) {
                    foreach ($_rawDatas as &$data) {
                        $this->_explodeForeignValues($data);
                    }
                    break;
                }
            }
        }
        $result = new RecordSetFast($this->_modelName, $_rawDatas);

        return $result;
    }
    
    /**
     * Get multiple entries
     *
     * @param string|array $_id Ids
     * @param array $_containerIds all allowed container ids that are added to getMultiple query
     * @return RecordSet
     */
    public function getMultiple($_id, $_containerIds = NULL) 
    {
        // filter out any emtpy values
        $ids = array_filter((array) $_id, function($value) {
            return !empty($value);
        });
        
        if (empty($ids)) {
            return new RecordSet($this->_modelName);
        }

        // replace objects with their id's
        foreach ($ids as &$id) {
            if ($id instanceof RecordInterface) {
                $id = $id->getId();
            }
        }
        
        $select = $this->_getSelect();
        $select->where($this->_db->quoteIdentifier($this->_tableName . '.' . $this->_identifier) . ' IN (?)', $ids);
        
        $schema = $this->getSchema();
        
        if ($_containerIds !== NULL && isset($schema['container_id'])) {
            if (empty($_containerIds)) {
                $select->where('1=0 /* insufficient grants */');
            } else {
                $select->where($this->_db->quoteIdentifier($this->_tableName . '.container_id') . ' IN (?) /* add acl in getMultiple */', (array) $_containerIds);
            }
        }
        
        AbstractSql::traitGroup($select);
        
        $this->_checkTracing($select);
        
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetchAll();
        
        $result = $this->_rawDataToRecordSet($queryResult);
        
        return $result;
    }
    
    /**
     * Gets all entries
     *
     * @param string $_orderBy Order result by
     * @param string $_orderDirection Order direction - allowed are ASC and DESC
     * @throws InvalidArgument
     * @return RecordSet
     */
    public function getAll($_orderBy = NULL, $_orderDirection = 'ASC') 
    {
        $orderBy = $_orderBy ? $_orderBy : $this->_tableName . '.' . $this->_identifier;
        
        if(!in_array($_orderDirection, array('ASC', 'DESC'))) {
            throw new InvalidArgument('$_orderDirection is invalid');
        }
        
        $select = $this->_getSelect();
        $select->order($orderBy . ' ' . $_orderDirection);
        
        AbstractSql::traitGroup($select);
        
        //if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $select->__toString());
            
        $stmt = $this->_db->query($select);
        $queryResult = $stmt->fetchAll();
        
        //if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($queryResult, true));
        
        $result = $this->_rawDataToRecordSet($queryResult);
        
        return $result;
    }
    
    /**
     * Search for records matching given filter
     *
     * @param  FilterGroup    $_filter
     * @param  Pagination            $_pagination
     * @param  array|string|boolean                 $_cols columns to get, * per default / use self::IDCOL or TRUE to get only ids
     * @return RecordSet|array
     */
    public function search(FilterGroup $_filter = NULL, Pagination $_pagination = NULL, $_cols = self::ALLCOL)
    {
        $getDeleted = !!$_filter && $_filter->getFilter('is_deleted');
        
        if ($_pagination === NULL) {
            $pagination = new Pagination(NULL, TRUE);
        } else {
            // clone pagination to prevent accidental change of original object
            $pagination = clone($_pagination);
        }
        
        // legacy: $_cols param was $_onlyIds (boolean) ...
        if ($_cols === TRUE) {
            $_cols = self::IDCOL;
        } elseif ($_cols === FALSE) {
            $_cols = self::ALLCOL;
        }
        
        // (1) eventually get only ids or id/value pair
        list($colsToFetch, $getIdValuePair) = $this->_getColumnsToFetch($_cols, $_filter, $pagination);

        // check if we should do one or two queries
        $doSecondQuery = true;
        if (!$getIdValuePair && $_cols !== self::IDCOL)
        {
            if ($this->_compareRequiredJoins($_cols, $colsToFetch)) {
                $doSecondQuery = false;
            }
        }
        if ($doSecondQuery) {
            $select = $this->_getSelect($colsToFetch, $getDeleted);
        } else {
            $select = $this->_getSelect($_cols, $getDeleted);
        }
        
        if ($_filter !== NULL) {
            $this->_addFilter($select, $_filter);
        }
        
        $this->_addSecondarySort($pagination);
        $this->_appendForeignSort($pagination, $select);
        $pagination->appendPaginationSql($select);
        
        AbstractSql::traitGroup($select);
        
        if ($getIdValuePair) {
            return $this->_fetch($select, self::FETCH_MODE_PAIR);
        } elseif($_cols === self::IDCOL) {
            return $this->_fetch($select);
        }
        
        if (!$doSecondQuery) {
            $rows = $this->_fetch($select, self::FETCH_ALL);
            if (empty($rows)) {
                return new RecordSet($this->_modelName);
            } else {
                return $this->_rawDataToRecordSet($rows);
            }
        }
        
        // (2) get other columns and do joins
        $ids = $this->_fetch($select);
        if (empty($ids)) {
            return new RecordSet($this->_modelName);
        }
        
        $select = $this->_getSelect($_cols, $getDeleted);
        $this->_addWhereIdIn($select, $ids);
        if (null !== $_pagination) {
            // clone pagination to prevent accidental change of original object
            $pagination = clone($_pagination);
        }
        $pagination->appendModelConfig($select);
        $pagination->appendSort($select);
        
        $rows = $this->_fetch($select, self::FETCH_ALL);
        
        return $this->_rawDataToRecordSet($rows);
    }

    /**
     * @return array
     */
    protected function _getIgnoreSortColumns()
    {
        return [];
    }

    /**
     * @param Pagination $pagination
     * @param Select $select
     */
    protected function _appendForeignSort(Pagination $pagination, Select $select)
    {
    }

    /**
     * add the fields to search for to the query
     *
     * @param  Select                       $_select current where filter
     * @param  FilterGroup    $_filter the string to search for
     * @return void
     */
    protected function _addFilter(Select $_select, /*FilterGroup */$_filter)
    {
        Tinebase_Backend_Sql_Filter_FilterGroup::appendFilters($_select, $_filter, $this);
    }
    
    /**
     * Gets total count of search with $_filter
     * 
     * @param FilterGroup $_filter
     * @return int|array
     */
    public function searchCount(FilterGroup $_filter)
    {
        $getDeleted = !!$_filter && $_filter->getFilter('is_deleted');

        $defaultCountCol = $this->_defaultCountCol == self::ALLCOL ?  self::ALLCOL : $this->_db->
            quoteIdentifier($this->_defaultCountCol);
        
        $searchCountCols = array('count' => 'COUNT(' . $defaultCountCol . ')');
        foreach ($this->_additionalSearchCountCols as $column => $select) {
            $searchCountCols['sum_' . $column] = new Zend_Db_Expr('SUM(' . $this->_db->quoteIdentifier($column) . ')');
        }
        
        list($subSelectColumns/*, $getIdValuePair*/) = $this->_getColumnsToFetch(self::IDCOL, $_filter);
        if (!empty($this->_additionalSearchCountCols)) {
            $subSelectColumns = array_merge($subSelectColumns, $this->_additionalSearchCountCols);
        }
        
        $subSelect = $this->_getSelect($subSelectColumns, $getDeleted);
        $this->_addFilter($subSelect, $_filter);
        
        AbstractSql::traitGroup($subSelect);
        
        $countSelect = $this->_db->select()->from($subSelect, $searchCountCols);
        
        if (!empty($this->_additionalSearchCountCols)) {
            $result = $this->_db->fetchRow($countSelect);
        } else {
            $result = $this->_db->fetchOne($countSelect);
        }
        
        return $result;
    }
    
    /**
     * returns columns to fetch in first query and if an id/value pair is requested 
     * 
     * @param array|string $_cols
     * @param FilterGroup $_filter
     * @param Pagination $_pagination
     * @return array
     */
    protected function _getColumnsToFetch($_cols, FilterGroup $_filter = NULL, Pagination $_pagination = NULL)
    {
        $getIdValuePair = FALSE;

        if ($_cols === self::ALLCOL) {
            $colsToFetch = array('id' => self::IDCOL);
        } else {
            $colsToFetch = (array) $_cols;
            
            if (in_array(self::IDCOL, $colsToFetch) && count($colsToFetch) == 2) {
                // id/value pair requested
                $getIdValuePair = TRUE;
            } else if (! in_array(self::IDCOL, $colsToFetch) && count($colsToFetch) == 1) {
                // only one non-id column was requested -> add id and treat it like id/value pair
                array_push($colsToFetch, self::IDCOL);
                $getIdValuePair = TRUE;
            } else {
                $colsToFetch = array('id' => self::IDCOL);
            }
        }
        
        if ($_filter !== NULL) {
            $colsToFetch = $this->_addFilterColumns($colsToFetch, $_filter);
        }
        
        if ($_pagination instanceof Pagination) {
            $ignoreColumns = $this->_getIgnoreSortColumns();
            foreach($_pagination->getSortColumns() as $sort) {
                if (!in_array($sort, $ignoreColumns) && !isset($colsToFetch[$sort])) {
                    $colsToFetch[$sort] = (substr_count($sort, $this->_tableName) === 0) ? $this->_tableName . '.' .
                        $sort : $sort;
                }
            }
        }
        
        return array($colsToFetch, $getIdValuePair);
    }
    
    /**
     * add columns from filter
     * 
     * @param array $_colsToFetch
     * @param FilterGroup $_filter
     * @return array
     */
    protected function _addFilterColumns($_colsToFetch, FilterGroup $_filter)
    {
        // need to ask filter if it needs additional columns
        $filterCols = $_filter->getRequiredColumnsForSelect();
        foreach ($filterCols as $key => $filterCol) {
            if (! (isset($_colsToFetch[$key]) || array_key_exists($key, $_colsToFetch))) {
                $_colsToFetch[$key] = $filterCol;
            }
        }
        
        return $_colsToFetch;
    }
    
    /**
     * add default secondary sort criteria
     * 
     * @param Pagination $_pagination
     */
    protected function _addSecondarySort(Pagination $_pagination)
    {
        if (! empty($this->_defaultSecondarySort)) {
            if (! is_array($_pagination->sort) || ! in_array($this->_defaultSecondarySort, $_pagination->sort)) {
                $_pagination->sort = array_merge((array)$_pagination->sort, array($this->_defaultSecondarySort));
            }
        }
    }
    
    
    /**
     * adds 'id in (...)' where stmt
     * 
     * @param Select $_select
     * @param string|array $_ids
     * @return Select
     */
    protected function _addWhereIdIn(Select $_select, $_ids)
    {
        $_select->where($this->_db->quoteInto($this->_db->quoteIdentifier($this->_tableName . '.' . $this->_identifier) . ' in (?)', (array) $_ids));
        
        return $_select;
    }
    
    /**
     * Checks if backtrace and query should be logged
     * 
     * For enabling this feature, you must add a key in config.inc.php:
     * 
     *     'logger' => 
     *         array(
     *             // logger stuff
     *             'traceQueryOrigins' => true,
     *             'priority' => 8
     *         ),
     *
     * @param Select $select
     */
    protected function _checkTracing(Select $select)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $config = Tinebase_Config::getInstance();
        if (Core::isLogLevel(LogLevel::TRACE) && $config && isset($config->logger)) {
            if (isset($config->logger->traceQueryOrigins) && $config->logger->traceQueryOrigins) {
                $e = new Exception();
                Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . "\n" . 
                    "BACKTRACE: \n" . $e->getTraceAsString() . "\n" . 
                    "SQL QUERY: \n" . $select);
            }
        }
    }
    
    /**
     * fetch rows from db
     * 
     * @param Select $_select
     * @param string $_mode
     * @return array
     */
    protected function _fetch(Select $_select, $_mode = self::FETCH_MODE_SINGLE)
    {
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . $_select->__toString());
        
        AbstractSql::traitGroup($_select);
        
        $this->_checkTracing($_select);
        
        $stmt = $this->_db->query($_select);
        
        if ($_mode === self::FETCH_ALL) {
            $result = (array) $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
        } else {
            $result = array();
            while ($row = $stmt->fetch(Zend_Db::FETCH_NUM)) {
                if ($_mode === self::FETCH_MODE_SINGLE) {
                    $result[] = $row[0];
                } else if ($_mode === self::FETCH_MODE_PAIR) {
                    $result[$row[0]] = $row[1];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * get the basic select object to fetch records from the database
     *  
     * @param array|string $_cols columns to get, * per default
     * @param boolean $_getDeleted get deleted records (if modlog is active)
     * @return Select
     */
    protected function _getSelect($_cols = self::ALLCOL, $_getDeleted = FALSE)
    {
        if ($_cols !== self::ALLCOL ) {
            $cols = array();
            // make sure cols is an array, prepend tablename and fix keys
            foreach ((array) $_cols as $id => $col) {
                $key = (is_numeric($id)) ? ($col === self::IDCOL) ? $this->_identifier : $col : $id;
                $cols[$key] = ($col === self::IDCOL) ? $this->_tableName . '.' . $this->_identifier : $col;
            }
        } else {
            $cols = array(self::ALLCOL);
        }

        foreach ($this->_additionalColumns as $name => $column) {
            $cols[$name] = $column;
        }
        
        if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' ' . print_r($cols, TRUE));
        
        $select = $this->getAdapter()->select();
        $select->from(array($this->_tableName => $this->_tablePrefix . $this->_tableName), $cols);
        
        if (!$_getDeleted && $this->_modlogActive) {
            // don't fetch deleted objects
            $select->where($this->_db->quoteIdentifier($this->_tableName . '.is_deleted') . ' = 0');
        }
        
        $this->_addForeignTableJoins($select, $cols);
        
        return $select;
    }
    
    /**
     * add foreign table joins
     * 
     * @param Select $_select
     * @param array|string $_cols columns to get, * per default
     * @param string $_groupBy
     * 
     * @todo joining the same table twice with same name but different "on"'s is not possible currently
     */
    protected function _addForeignTableJoins(Select $_select, $_cols, $_groupBy = NULL)
    {
        if (! empty($this->_foreignTables)) {
            $groupBy = ($_groupBy !== NULL) ? $_groupBy : $this->_tableName . '.' . $this->_identifier;
            $_select->group($groupBy);
            
            $cols = (array) $_cols;
            foreach ($this->_foreignTables as $foreignColumn => $join) {
                // only join if field is in cols
                if (in_array(self::ALLCOL, $cols) || (isset($cols[$foreignColumn]) || array_key_exists($foreignColumn, $cols))) {
                    if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' foreign column: ' . $foreignColumn);
                    
                    $selectArray = ((isset($join['select']) || array_key_exists('select', $join)))
                        ? $join['select'] 
                        : (((isset($join['field']) || array_key_exists('field', $join)) && (! (isset($join['singleValue']) || array_key_exists('singleValue', $join)) || ! $join['singleValue']))
                            ? array($foreignColumn => $this->_dbCommand->getAggregate($join['table'] . '.' . $join['field']))
                            : array($foreignColumn => $join['table'] . '.id'));
                    $joinId = isset($join['joinId']) ? $join['joinId'] : $this->_identifier;
                    
                    // avoid duplicate columns => will be added again in the next few lines of code
                    $this->_removeColFromSelect($_select, $foreignColumn);
                    
                    $from = $_select->getPart(Select::FROM);
                    
                    if (!isset($from[$join['table']])) {
                        $_select->joinLeft(
                            /* table  */ array($join['table'] => $this->_tablePrefix . $join['table']), 
                            /* on     */ $this->_db->quoteIdentifier($this->_tableName . '.' . $joinId) . ' = ' . $this->_db->quoteIdentifier($join['table'] . '.' . $join['joinOn']),
                            /* select */ $selectArray
                        );
                    } else {
                        // join is defined already => just add the column
                        $_select->columns($selectArray, $join['table']);
                    }
                }
            }
        }
    }
    
    /**
     * returns true if joins are equal, false if not
     * 
     * @param array $finalCols
     * @param array $interimCols
     * @return boolean
     */
    protected function _compareRequiredJoins( $finalCols, $interimCols )
    {
        $ret = true;
        if (! empty($this->_foreignTables)) {
            $finalCols = (array) $finalCols;
            $finalColsJoins = array();
            $interimColsJoins = array();
            foreach ($this->_foreignTables as $foreignColumn => $join) {
                // only join if field is in cols
                if (in_array(self::ALLCOL, $finalCols) || (isset($finalCols[$foreignColumn]) || array_key_exists($foreignColumn, $finalCols))) {
                    $finalColsJoins[$join['table']] = 1;
                }
                if (in_array(self::ALLCOL, $interimCols) || (isset($interimCols[$foreignColumn]) || array_key_exists($foreignColumn, $interimCols))) {
                    $interimColsJoins[$join['table']] = 1;
                }
            }
            if (count(array_diff_key($finalColsJoins,$interimColsJoins)) > 0) {
                $ret = false;
            }
        }
        return $ret;
    }
    
    /**
     * remove column from select to avoid duplicates 
     * 
     * @param Select $_select
     * @param string $_column
     * @todo remove $_cols parameter
     */
    protected function _removeColFromSelect(Select $_select, $_column)
    {
        $columns = $_select->getPart(Select::COLUMNS);
        $from = $_select->getPart(Select::FROM);
        
        foreach ($columns as $id => $column) {
            if ($column[2] == $_column) {
                if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(
                    __METHOD__ . '::' . __LINE__ . ' Removing ' . $_column . ' from columns.');
                
                unset($columns[$id]);
                
                // reset all all columns and add as again
                $_select->reset(Select::COLUMNS);
                foreach ($columns as $newColumn) {
                    
                    if (isset($from[$newColumn[0]])) {
                        $_select->columns(!empty($newColumn[2]) ? array($newColumn[2] => $newColumn[1]) : $newColumn[1], $newColumn[0]);
                    }
                }
                
                break;
            }
        }
    }
    
    /*************************** create / update / delete ****************************/

    /**
     * Creates new entry
     *
     * @param   RecordInterface $_record
     * @return RecordInterface
     * @throws Exception
     * @todo    remove autoincremental ids later
     */
    public function create(RecordInterface $_record) 
    {
        $transactionId = TransactionManager::getInstance()->startTransaction($this->_db);
        try {
            $identifier = $_record->getIdProperty();
            
            if (!$_record instanceof $this->_modelName) {
                throw new InvalidArgument('invalid model type: $_record is instance of "' . get_class($_record) . '". but should be instance of ' . $this->_modelName);

            }

            /** @var RecordInterface $_record */
            // set uid if record has hash id and id is empty
            if (empty($_record->$identifier) && $this->_hasHashId()) {
                $_record->setId(AbstractRecord::generateUID());
            }
            
            $recordArray = $this->_recordToRawData($_record);
            
            // unset id if present and empty
            if (array_key_exists($identifier, $recordArray) && empty($recordArray[$identifier])) {
                unset($recordArray[$identifier]);
            }
            
            $recordArray = array_intersect_key($recordArray, $this->getSchema());
            
            $this->_prepareData($recordArray);
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__
                . " Prepared data for INSERT: " . print_r($recordArray, true)
            );
            
            $this->_db->insert($this->_tablePrefix . $this->_tableName, $recordArray);
            
            if (!isset($recordArray[$identifier]) && $this->_hasAutoIncrementId()) {
                $newId = $this->_db->lastInsertId($this->getTablePrefix() . $this->getTableName(), $identifier);
                if (!$newId) {
                    throw new Tinebase_Exception_UnexpectedValue("New record auto increment id is empty");
                }
                $_record->setId($newId);
            }
            
            // if we insert a record without an id, we need to get back one
            if (empty($_record->$identifier)) {
                throw new Tinebase_Exception_UnexpectedValue("Returned record id is empty.");
            }
            
            // add custom fields
            if ($_record->has('customfields') && !empty($_record->customfields)) {
                Tinebase_CustomField::getInstance()->saveRecordCustomFields($_record);
            }
            
            $this->_updateForeignKeys('create', $_record);
            
            $result = $this->get($_record->$identifier);
            
            $this->_inspectAfterCreate($result, $_record);
            
            TransactionManager::getInstance()->commitTransaction($transactionId);

            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(
                __METHOD__ . '::' . __LINE__
                . ' Created new record in ' . $this->_tableName . ' with id ' . $_record->getId()
            );

        } catch(Exception $e) {
            TransactionManager::getInstance()->rollBack();
            throw $e;
        }
        
        return $result;
    }
    
    /**
     * returns true if id is a hash value and false if integer
     *
     * @return  boolean
     * @todo    remove that when all tables use hash ids 
     */
    protected function _hasHashId()
    {
        $identifier = $this->_getRecordIdentifier();
        $schema     = $this->getSchema();
        
        if (!isset($schema[$identifier])) {
            // should never happen
            return false;
        }
        
        $column = $schema[$identifier];
        
        if (!in_array($column['DATA_TYPE'], array('varchar', 'VARCHAR2'))) {
            return false;
        }
        
        return ($column['LENGTH'] == 40);
    }
    
    /**
     * returns true if id is an autoincrementing column
     * 
     * IDENTITY is 1 for auto increment columns
     * 
     * MySQL
     * 
     * Array (
        [SCHEMA_NAME] => 
        [TABLE_NAME] => tine20_container
        [COLUMN_NAME] => id
        [COLUMN_POSITION] => 1
        [DATA_TYPE] => int
        [DEFAULT] => 
        [NULLABLE] => 
        [LENGTH] => 
        [SCALE] => 
        [PRECISION] => 
        [UNSIGNED] => 1
        [PRIMARY] => 1
        [PRIMARY_POSITION] => 1
        [IDENTITY] => 1
     * )
     * 
     * PostgreSQL
     * 
     * Array (
        [SCHEMA_NAME] => public
        [TABLE_NAME] => tine20_container
        [COLUMN_NAME] => id
        [COLUMN_POSITION] => 1
        [DATA_TYPE] => int4
        [DEFAULT] => nextval('tine20_container_id_seq'::regclass)
        [NULLABLE] => 
        [LENGTH] => 4
        [SCALE] => 
        [PRECISION] => 
        [UNSIGNED] => 
        [PRIMARY] => 1
        [PRIMARY_POSITION] => 1
        [IDENTITY] => 1
     * )
     *
     * Oracle
     *
     * Array (
        [SCHEMA_NAME] => public
        [TABLE_NAME] => tine20_container
        [COLUMN_NAME] => id
        [COLUMN_POSITION] => 1
        [DATA_TYPE] => NUMBER
        [DEFAULT] =>
        [NULLABLE] =>
        [LENGTH] => 0
        [SCALE] => 0
        [PRECISION] => 11
        [UNSIGNED] =>
        [PRIMARY] => 1
        [PRIMARY_POSITION] => 1
        [IDENTITY] =>
        )
     * 
     * @return boolean
     */
    protected function _hasAutoIncrementId()
    {
        $identifier = $this->_getRecordIdentifier();
        $schema     = $this->getSchema();

        if (!isset($schema[$identifier])) {
            // should never happen
            return false;
        }

        $column = $schema[$identifier];

        if (!in_array($column['DATA_TYPE'], array('int', 'int4', 'NUMBER'))) {
            return false;
        }

        if ($this->_db instanceof Zend_Db_Adapter_Oracle) {
            // @see https://forge.tine20.org/view.php?id=10820#c16318
            $result = (
                $column['PRIMARY'] == 1
                && empty($column['IDENTITY'])
                && empty($column['NULLABLE'])
                && empty($column['DEFAULT'])
            );
        } else {
            $result = !!$column['IDENTITY'];
        }
        
        return $result;
    }
    
    /**
     * converts record into raw data for adapter
     *
     * @param  RecordInterface $_record
     * @return array
     */
    protected function _recordToRawData(RecordInterface $_record)
    {
        $_record->runConvertToData();
        $readOnlyFields = $_record->getReadOnlyFields();
        $raw = $_record->toArray(FALSE);
        foreach ($raw as $key => $value) {
            if ($value instanceof RecordInterface) {
                $raw[$key] = $value->getId();
            }
            if (in_array($key, $readOnlyFields)) {
                unset($raw[$key]);
            }
        }
        $_record->runConvertToRecord();

        return $raw;
    }
    
    /**
     * prepare record data array
     * - replace int and bool values by Zend_Db_Expr
     *
     * @param array &$_recordArray
     */
    protected function _prepareData(&$_recordArray) 
    {
        
        foreach ($_recordArray as $key => $value) {
            if (is_bool($value)) {
                $_recordArray[$key] = ($value) ? new Zend_Db_Expr('1') : new Zend_Db_Expr('0');
            } elseif (is_null($value)) {
                $_recordArray[$key] = new Zend_Db_Expr('NULL');
            } elseif (is_int($value)) {
                $_recordArray[$key] = new Zend_Db_Expr((string) $value);
            }
        }
    }
    
    /**
     * update foreign key values
     * 
     * @param string $_mode create|update
     * @param RecordInterface $_record
     */
    protected function _updateForeignKeys($_mode, RecordInterface $_record)
    {
        if (! empty($this->_foreignTables)) {
            
            foreach ($this->_foreignTables as $modelName => $join) {
                
                if (! (isset($join['field']) || array_key_exists('field', $join))) {
                    continue;
                }
                
                $idsToAdd    = array();
                $idsToRemove = array();
                
                if (!empty($_record->$modelName)) {
                    $idsToAdd = RecordSet::getIdsFromMixed($_record->$modelName);
                }
                
                $transactionId = TransactionManager::getInstance()->startTransaction(Core::getDb());
                
                if ($_mode == 'update') {
                    $select = $this->_db->select();
        
                    $select->from(array($join['table'] => $this->_tablePrefix . $join['table']), array($join['field']))
                        ->where($this->_db->quoteIdentifier($join['table'] . '.' . $join['joinOn']) . ' = ?', $_record->getId());
                    
                    AbstractSql::traitGroup($select);
                    
                    $stmt = $this->_db->query($select);
                    $currentIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                    $stmt->closeCursor();
                    
                    $idsToRemove = array_diff($currentIds, $idsToAdd);
                    $idsToAdd    = array_diff($idsToAdd, $currentIds);
                }
                
                if (!empty($idsToRemove)) {
                    $where = '(' . 
                        $this->_db->quoteInto($this->_db->quoteIdentifier($this->_tablePrefix . $join['table'] . '.' . $join['joinOn']) . ' = ?', $_record->getId()) .
                        ' AND ' . 
                        $this->_db->quoteInto($this->_db->quoteIdentifier($this->_tablePrefix . $join['table'] . '.' . $join['field']) . ' IN (?)', $idsToRemove) .
                    ')';
                        
                    $this->_db->delete($this->_tablePrefix . $join['table'], $where);
                }
                
                foreach ($idsToAdd as $id) {
                    $recordArray = array (
                        $join['joinOn'] => $_record->getId(),
                        $join['field']  => $id
                    );
                    $this->_db->insert($this->_tablePrefix . $join['table'], $recordArray);
                }
                    
                
                TransactionManager::getInstance()->commitTransaction($transactionId);
            }
        }
    }
    
    /**
     * do something after creation of record
     * 
     * @param RecordInterface $_newRecord
     * @param RecordInterface $_recordToCreate
     * @return void
     */
    protected function _inspectAfterCreate(RecordInterface $_newRecord, RecordInterface $_recordToCreate)
    {
    }
    
    /**
     * Updates existing entry
     *
     * @param RecordInterface $_record
     * @throws Tinebase_Exception_Record_Validation|InvalidArgument
     * @return RecordInterface Record|NULL
     */
    public function update(RecordInterface $_record) 
    {
        $identifier = $_record->getIdProperty();
        
        if (!$_record instanceof $this->_modelName) {
            throw new InvalidArgument('invalid model type: $_record is instance of "' . get_class($_record) . '". but should be instance of ' . $this->_modelName);
        }

        /** @var RecordInterface $_record */
        $_record->isValid(TRUE);
        
        $id = $_record->getId();

        $recordArray = $this->_recordToRawData($_record);
        $recordArray = array_intersect_key($recordArray, $this->getSchema());
        
        $this->_prepareData($recordArray);
        
        $where  = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier($identifier) . ' = ?', $id),
        );
        
        $this->_db->update($this->_tablePrefix . $this->_tableName, $recordArray, $where);

        // update custom fields
        if ($_record->has('customfields')) {
            Tinebase_CustomField::getInstance()->saveRecordCustomFields($_record);
        }
        
        $this->_updateForeignKeys('update', $_record);
        
        $result = $this->get($id, true);
        
        return $result;
    }
    
    /**
     * Updates multiple entries
     *
     * @param array $_ids to update
     * @param array $_data
     * @return integer number of affected rows
     * @throws Tinebase_Exception_Record_Validation|InvalidArgument
     */
    public function updateMultiple($_ids, $_data) 
    {
        if (empty($_ids)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' No records updated.');
            return 0;
        }
        
        // separate CustomFields
        
        $myFields = array();
        $customFields = array();
        
        foreach($_data as $key => $value) {
            if(stristr($key, '#')) {
                $customFields[substr($key,1)] = $value;
            } else {
                $myFields[$key] = $value;
            }
        }
        
        // handle CustomFields
        
        if (count($customFields)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                . ' CustomFields found.');
            Tinebase_CustomField::getInstance()->saveMultipleCustomFields($this->_modelName, $_ids, $customFields);
        }
        
        // handle StdFields
        
        if (!count($myFields)) {
            return 0;
        }

        $identifier = $this->_getRecordIdentifier();
        
        $recordArray = $myFields;
        $recordArray = array_intersect_key($recordArray, $this->getSchema());
        
        $this->_prepareData($recordArray);
                
        $where  = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier($identifier) . ' IN (?)', $_ids),
        );

        if (empty($recordArray)) {
            throw new Tinebase_Exception_UnexpectedValue(
                'Nothing to update - maybe you tried to update fields that are not in the schema?'
            );
        }
        
        return $this->_db->update($this->_tablePrefix . $this->_tableName, $recordArray, $where);
    }

    /**
     * Soft deletes entries if modlog is active, otherwise executes hard delete
     *
     * @param string|integer|RecordInterface|array $_id
     * @return int The number of affected rows.
     */
    public function softDelete($_id)
    {
        if (empty($_id)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No records deleted.');
            return 0;
        }

        if (true !== $this->_modlogActive) {
            return $this->delete($_id);
        }

        $idArray = (! is_array($_id)) ? array(AbstractRecord::convertId($_id, $this->_modelName)) : $_id;
        $identifier = $this->_getRecordIdentifier();

        $this->_inspectBeforeSoftDelete($idArray);

        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier($identifier) . ' IN (?)', $idArray)
        );

        $data = array('is_deleted' => 1);
        $schema = Tinebase_Db_Table::getTableDescriptionFromCache($this->_tablePrefix . $this->_tableName, $this->_db);
        if (isset($schema['deleted_time'])) {
            $data['deleted_time'] = new Zend_Db_Expr('NOW()');
        }
        if (isset($schema['deleted_by'])) {
            $data['deleted_by'] = Core::getUser()->getId();
        }

        return $this->_db->update($this->_tablePrefix . $this->_tableName, $data, $where);
    }

    /**
     * @param array $_ids
     */
    protected function _inspectBeforeSoftDelete(array $_ids)
    {
    }
    
    /**
      * Deletes entries
      * 
      * @param string|integer|RecordInterface|array $_id
      * @return int The number of affected rows.
      */
    public function delete($_id)
    {
        if (empty($_id)) {
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' No records deleted.');
            return 0;
        }
        
        $idArray = (! is_array($_id)) ? array(AbstractRecord::convertId($_id, $this->_modelName)) : $_id;
        $identifier = $this->_getRecordIdentifier();
        
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier($identifier) . ' IN (?)', $idArray)
        );
        
        return $this->_db->delete($this->_tablePrefix . $this->_tableName, $where);
    }
    
    /**
     * delete rows by property
     * 
     * @param string|array $_value
     * @param string $_property
     * @param string $_operator (equals|in)
     * @return integer The number of affected rows.
     * @throws InvalidArgument
     */
    public function deleteByProperty($_value, $_property, $_operator = 'equals')
    {
        $schema = $this->getSchema();
        
        if (! (isset($schema[$_property]) || array_key_exists($_property, $schema))) {
            throw new InvalidArgument('Property ' . $_property . ' does not exist in table ' . $this->_tableName);
        }
        
        switch ($_operator) {
            case 'equals':
                $op = ' = ?';
                break;
            case 'in':
                $op = ' IN (?)';
                $_value = (array) $_value;
                break;
            default:
                throw new InvalidArgument('Invalid operator: ' . $_operator);
        }
        $where = array(
            $this->_db->quoteInto($this->_db->quoteIdentifier($_property) . $op, $_value)
        );
        
        return $this->_db->delete($this->_tablePrefix . $this->_tableName, $where);
    }
    
    /*************************** foreign record fetchers *******************************/
    
    /**
     * appends foreign record (1:1 relation) to given record
     *
     * @param RecordInterface     $_record            Record to append the foreign record to
     * @param string                        $_appendTo          Property in the record where to append the foreign record to
     * @param string                        $_recordKey         Property in the record where the foreign key value is in
     * @param string                        $_foreignKey        Key property in foreign table of the record to append
     * @param AbstractSql $_foreignBackend    Foreign table backend 
     */
    public function appendForeignRecordToRecord($_record, $_appendTo, $_recordKey, $_foreignKey, $_foreignBackend)
    {
        try {
            $_record->$_appendTo = $_foreignBackend->getByProperty($_record->$_recordKey, $_foreignKey);
        } catch (NotFound $e) {
            $_record->$_appendTo = NULL;
        }
    }
    
    /**
     * appends foreign recordSet (1:n relation) to given record
     *
     * @param RecordInterface     $_record            Record to append the foreign records to
     * @param string                        $_appendTo          Property in the record where to append the foreign records to
     * @param string                        $_recordKey         Property in the record where the foreign key value is in
     * @param string                        $_foreignKey        Key property in foreign table of the records to append
     * @param AbstractSql $_foreignBackend    Foreign table backend 
     */
    public function appendForeignRecordSetToRecord($_record, $_appendTo, $_recordKey, $_foreignKey, $_foreignBackend)
    {
        $_record->$_appendTo = $_foreignBackend->getMultipleByProperty($_record->$_recordKey, $_foreignKey);
    }
    
    /**
     * appends foreign record (1:1/n:1 relation) to given recordSet
     *
     * @param RecordSet     $_recordSet         Records to append the foreign record to
     * @param string                        $_appendTo          Property in the records where to append the foreign record to
     * @param string                        $_recordKey         Property in the records where the foreign key value is in
     * @param string                        $_foreignKey        Key property in foreign table of the record to append
     * @param AbstractSql $_foreignBackend    Foreign table backend 
     */
    public function appendForeignRecordToRecordSet($_recordSet, $_appendTo, $_recordKey, $_foreignKey, $_foreignBackend)
    {
        $allForeignRecords = $_foreignBackend->getMultipleByProperty($_recordSet->$_recordKey, $_foreignKey);
        foreach ($_recordSet as $record) {
            $record->$_appendTo = $allForeignRecords->filter($_foreignKey, $record->$_recordKey)->getFirstRecord();
        }
    }
    
    /**
     * appends foreign recordSet (1:n relation) to given recordSet
     *
     * @param RecordSet     $_recordSet         Records to append the foreign records to
     * @param string                        $_appendTo          Property in the records where to append the foreign records to
     * @param string                        $_recordKey         Property in the records where the foreign key value is in
     * @param string                        $_foreignKey        Key property in foreign table of the records to append
     * @param AbstractSql $_foreignBackend    Foreign table backend 
     */
    public function appendForeignRecordSetToRecordSet($_recordSet, $_appendTo, $_recordKey, $_foreignKey, $_foreignBackend)
    {
        $idxRecordKeyMap = $_recordSet->$_recordKey;
        $recordKeyIdxMap = array_flip($idxRecordKeyMap);
        $allForeignRecords = $_foreignBackend->getMultipleByProperty($idxRecordKeyMap, $_foreignKey);
        $foreignRecordsClassName = $allForeignRecords->getRecordClassName();

        foreach ($_recordSet as $record) {
            $record->$_appendTo = new RecordSet($foreignRecordsClassName);
        }

        foreach($allForeignRecords as $foreignRecord) {
            $record = $_recordSet->getByIndex($recordKeyIdxMap[$foreignRecord->$_foreignKey]);
            $foreignRecordSet = $record->$_appendTo;

            $foreignRecordSet->addRecord($foreignRecord);
        }
    }
    
    /*************************** other ************************************/
    
    /**
     * get table name
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->_tableName;
    }
    
    /**
     * get foreign table information
     *
     * @return array
     */
    public function getForeignTables()
    {
        return $this->_foreignTables;
    }
    
    /**
     * get table prefix
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->_tablePrefix;
    }
    
    /**
     * get table identifier
     * 
     * @return string
     */
    public function getIdentifier()
    {
        return $this->_identifier;
    }
    
    /**
     * get db adapter
     *
     * @return AdapterInterface
     * @throws Database
     */
    public function getAdapter()
    {
        if (! $this->_db instanceof AdapterInterface) {
            throw new Database('Could not fetch database adapter');
        }
        
        return $this->_db;
    }
    
    /**
     * get dbCommand class
     *
     * @return CommandInterface
     * @throws Database
     */
    public function getDbCommand()
    {
        if (! $this->_dbCommand instanceof CommandInterface) {
            throw new Database('Could not fetch database command class');
        }
        
        return $this->_dbCommand;
    }
    
    /**
     * Public service for grouping treatment
     * 
     * @param Select $select
     */
    public static function traitGroup(Select $select)
    {
        // not needed for MySQL backends
        if ($select->getAdapter() instanceof Zend_Db_Adapter_Pdo_Mysql) {
            return;
        }
        
        $group = $select->getPart(Select::GROUP);
        
        if (empty($group)) {
            return;
        }
        
        $columns        = $select->getPart(Select::COLUMNS);
        $updatedColumns = array();
        
        //$column is an array where 0 is table, 1 is field and 2 is alias
        foreach ($columns as $key => $column) {
            if ($column[1] instanceof Zend_Db_Expr) {
                if (preg_match('/^\(.*\)/', $column[1])) {
                    $updatedColumns[] = array($column[0], new Zend_Db_Expr("MIN(" . $column[1] . ")"), $column[2]);
                } else {
                    $updatedColumns[] = $column;
                }
                
                continue;
            }
            
            if (preg_match('/^\(.*\)/', $column[1])) {
                $updatedColumns[] = array($column[0], new Zend_Db_Expr("MIN(" . $column[1] . ")"), $column[2]);
                
                continue;
            }
            
            // resolve * to single columns
            if ($column[1] == self::ALLCOL) {

                $tableFields = Tinebase_Db_Table::getTableDescriptionFromCache(SQL_TABLE_PREFIX . $column[0], $select->getAdapter());
                foreach ($tableFields as $columnName => $schema) {
                    
                    // adds columns into group by clause (table.field)
                    // checks if field has a function (that must be an aggregation)
                    $fieldName = "{$column[0]}.$columnName";
                    
                    if (in_array($fieldName, $group)) {
                        $updatedColumns[] = array($column[0], $fieldName, $columnName);
                    } else {
                        // any selected field which is not in the group by clause must have an aggregate function
                        // we choose MIN() as default. In practice the affected columns will have only one value anyways.
                        $updatedColumns[] = array($column[0], new Zend_Db_Expr("MIN(" . $select->getAdapter()->quoteIdentifier($fieldName) . ")"), $columnName);
                    }
                }
                
                continue;
            }
            
            $fieldName = $column[0] . '.' . $column[1];
            
            if (in_array($fieldName, $group)) {
                $updatedColumns[] = $column;
            } else {
                // any selected field which is not in the group by clause must have an aggregate function
                // we choose MIN() as default. In practice the affected columns will have only one value anyways.
                $updatedColumns[] = array($column[0], new Zend_Db_Expr("MIN(" . $select->getAdapter()->quoteIdentifier($fieldName) . ")"), $column[2] ? $column[2] : $column[1]);
            }
        }
        
        $select->reset(Select::COLUMNS);
        
        foreach ($updatedColumns as $column) {
            $select->columns(!empty($column[2]) ? array($column[2] => $column[1]) : $column[1], $column[0]);
        }

        // add order by columns to group by
        $order = $select->getPart(Select::ORDER);
        
        foreach($order as $column) {
            $field = $column[0];
            
            if (preg_match('/.*\..*/',$field) && !in_array($field,$group)) {
                // adds column into group by clause (table.field)
                $group[] = $field;
            }
        }
        
        $select->reset(Select::GROUP);
        
        $select->group($group);
    }

    /**
     * sets etags, expects ids as keys and etags as value
     *
     * @param array $etags
     * 
     * @todo maybe we should find a better place for the etag functions as this is currently only used in Calendar + Tasks
     */
    public function setETags(array $etags)
    {
        foreach ($etags as $id => $etag) {
            $where  = array(
                $this->_db->quoteInto($this->_db->quoteIdentifier($this->_identifier) . ' = ?', $id),
            );
            $this->_db->update($this->_tablePrefix . $this->_tableName, array('etag' => $etag), $where);
        }
    }
    
    /**
     * checks if there is an event with this id and etag, or an event with the same id
     *
     * @param string $id
     * @param string $etag
     * @return boolean
     * @throws NotFound
     */
    public function checkETag($id, $etag)
    {
        $select = $this->_db->select();
        $select->from(array($this->_tableName => $this->_tablePrefix . $this->_tableName), $this->_identifier);
        $select->where($this->_db->quoteIdentifier($this->_identifier) . ' = ?', $id);
        $select->orWhere($this->_db->quoteIdentifier('uid') . ' = ?', $id);
    
        $stmt = $select->query();
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();
    
        if ($queryResult === false) {
            throw new NotFound('no record with id ' . $id .' found');
        }
    
        $select->where($this->_db->quoteIdentifier('etag') . ' = ?', $etag);
        $stmt = $select->query();
        $queryResult = $stmt->fetch();
        $stmt->closeCursor();
    
        return ($queryResult !== false);
    }
    
    /**
     * return etag set for given container
     * 
     * @param string $containerId
     * @return mixed
     */
    public function getEtagsForContainerId($containerId)
    {
        $select = $this->_db->select();
        $select->from(array($this->_tableName => $this->_tablePrefix . $this->_tableName), array($this->_identifier, 'etag', 'uid'));
        $select->where($this->_db->quoteIdentifier('container_id') . ' = ?', $containerId);
        $select->where($this->_db->quoteIdentifier('is_deleted') . ' = ?', 0);
    
        $stmt = $select->query();
        $queryResult = $stmt->fetchAll();
    
        $result = array();
        foreach ($queryResult as $row) {
            $result[$row['id']] = $row;
        }
        return $result;
    }

    /**
     * increases seq by one for all records for given container
     *
     * @param string $containerId
     * @return void
     */
    public function increaseSeqsForContainerId($containerId)
    {
        $seq = $this->_db->quoteIdentifier('seq');
        $where = $this->_db->quoteInto($this->_db->quoteIdentifier('container_id') . ' = ?', $containerId);

        $this->_db->query("UPDATE {$this->_tablePrefix}{$this->_tableName} SET $seq = $seq +1 WHERE $where");
    }

    /**
     * save value in in-class cache
     * 
     * @param  string   $method
     * @param  string   $cacheId
     * @param  string   $value
     * @param  boolean  $usePersistentCache
     * @param  boolean  $persistantCacheTTL
     * @return PerRequest
     */
    public function saveInClassCache($method, $cacheId, $value, $usePersistentCache = false, $persistantCacheTTL = false)
    {
        return PerRequest::getInstance()->save($this->_getInClassCacheIdentifier(), $method, $cacheId, $value, $usePersistentCache, $persistantCacheTTL);
    }
    
    /**
     * load value from in-class cache
     * 
     * @param string  $method
     * @param string  $cacheId
     * @param boolean $usePersistentCache
     * @return mixed
     */
    public function loadFromClassCache($method, $cacheId, $usePersistentCache = false)
    {
        return PerRequest::getInstance()->load($this->_getInClassCacheIdentifier(), $method, $cacheId, $usePersistentCache);
    }
    
    /**
     * reset class cache
     *
     * @param string $method
     * @return AbstractSql
     */
    public function resetClassCache($method = null)
    {
        PerRequest::getInstance()->reset($this->_getInClassCacheIdentifier(), $method);
        
        return $this;
    }
    
    /**
     * return class cache identifier
     * 
     * if class extend parent class, you can define the name of the parent class here
     * 
     * @return string
     */
    public function _getInClassCacheIdentifier()
    {
        if (isset($this->_classCacheIdentifier)) {
            return $this->_classCacheIdentifier;
        }
        
        return get_class($this);
    }

    /**
     * clear table
     *
     * @return integer
     */
    public function clearTable()
    {
        $table = $this->getTablePrefix() . $this->getTableName();
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Removing all records from table ' . $table);

        $deletedRows = $this->_db->delete($table);
        if (Core::isLogLevel(LogLevel::INFO)) Core::getLogger()->info(__METHOD__ . '::' . __LINE__
            . ' Removed ' . $deletedRows . ' rows.');

        return $deletedRows;
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
        $select = $this->getAdapter()->select();
        $select->from([$this->_tableName => $this->_tablePrefix . $this->_tableName],
            [$this->_tableName . '.' . $this->_identifier]);

        if (!$_getDeleted && $this->_modlogActive) {
            // don't fetch deleted objects
            $select->where($this->_db->quoteIdentifier($this->_tableName . '.is_deleted') . ' = 0');
        }

        $select->where($this->_db->quoteInto($this->_db->quoteIdentifier($this->_tableName . '.' . $this->_identifier)
            . ' IN (?)', $_ids));

        return $select->query()->fetchAll(Zend_Db::FETCH_COLUMN);
    }
}
